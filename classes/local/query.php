<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_le_hub_dashboard\local;

use block_le_dashboard\local\config as maconfig;
use block_le_dashboard\local\view\base;
use block_le_dashboard\local\view\marking_rules;

/**
 * The index aggregates the hub reads for a set of assessments, in one pass each over the cm, student and mark
 * tables. Counting rules come from My assessments' marking_rules (ready, counted, expected, who a mark counts for);
 * the send-step rules from rules. Nothing here decides a group: that is rules::classify().
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class query {
    /**
     * Everything the rows need for the assessments a condition on the index (alias c) picks.
     *
     * @param string $where SQL on c.
     * @param array $params
     * @param int $now
     * @return \stdClass[] Keyed by cmid: the index record with the aggregates on it (see aggregate()).
     */
    public static function aggregates(string $where, array $params, int $now): array {
        global $DB;
        $cms = $DB->get_records_sql("SELECT c.*, co.shortname AS courseshortname, co.fullname AS coursefullname,
                                            co.category AS categoryid
                                       FROM {block_le_dashboard_cm} c
                                       JOIN {course} co ON co.id = c.courseid
                                       " . base::LIVE . "
                                      WHERE $where
                                   ORDER BY c.due ASC, c.name ASC", $params);
        if (!$cms) {
            return [];
        }
        $cms = array_column(array_values($cms), null, 'cmid');

        // Students: who should hand in, who did, who has no marker, whose deadline is their own, who is exempt.
        $expected = marking_rules::EXPECTED;
        $students = $DB->get_records_sql("SELECT s.cmid,
                                                  SUM(CASE WHEN $expected THEN 1 ELSE 0 END) AS total,
                                                  SUM(CASE WHEN $expected AND s.submitted = 1 THEN 1 ELSE 0 END) AS submitted,
                                                  SUM(CASE WHEN $expected AND s.allocatedto IS NULL
                                                           THEN 1 ELSE 0 END) AS unallocated,
                                                  SUM(CASE WHEN $expected AND s.individual = 1 THEN 1 ELSE 0 END) AS individual,
                                                  SUM(CASE WHEN s.canaccess = 1 AND s.active = 1 AND s.expected = 0
                                                           THEN 1 ELSE 0 END) AS exempt,
                                                  MIN(CASE WHEN s.timereleased > 0
                                                           THEN s.timereleased ELSE NULL END) AS firstreleased,
                                                  MAX(s.timereleased) AS lastreleased
                                             FROM {block_le_dashboard_stu} s
                                             JOIN {block_le_dashboard_cm} c ON c.cmid = s.cmid
                                            WHERE $where
                                         GROUP BY s.cmid", $params);

        // Marking units per stage, as the convenor view counts them, plus the send step (D10).
        $ready = marking_rules::ready_sql($now);
        $early = rules::EARLY_SQL;
        $changed = rules::CHANGED_SENT_SQL;
        $marking = [];
        $rs = $DB->get_recordset_sql("SELECT m.cmid, m.stage,
                                             COUNT(1) AS units,
                                             SUM(CASE WHEN $ready THEN 1 ELSE 0 END) AS ready,
                                             SUM(CASE WHEN $ready AND m.marked = 1 THEN 1 ELSE 0 END) AS marked,
                                             SUM(CASE WHEN m.submitted = 1 THEN 1 ELSE 0 END) AS submittedunits,
                                             SUM(CASE WHEN $ready AND m.released = 1 THEN 1 ELSE 0 END) AS released,
                                             SUM(CASE WHEN m.timesent IS NOT NULL THEN 1 ELSE 0 END) AS sent,
                                             SUM(CASE WHEN $early THEN 1 ELSE 0 END) AS early,
                                             SUM(CASE WHEN $changed THEN 1 ELSE 0 END) AS changedsent,
                                             MAX(CASE WHEN m.unittype = 'group' THEN 1 ELSE 0 END) AS isgroup,
                                             MIN(CASE WHEN $ready AND m.marked = 0 THEN m.timeready ELSE NULL END) AS firstready,
                                             MAX(m.timemarked) AS lastmarked
                                        FROM {block_le_dashboard_mark} m
                                        JOIN {block_le_dashboard_cm} c ON c.cmid = m.cmid
                                       WHERE $where AND " . marking_rules::COUNTED . "
                                    GROUP BY m.cmid, m.stage", $params);
        foreach ($rs as $r) {
            $marking[(int)$r->cmid][$r->stage] = $r;
        }
        $rs->close();

        // Units with marking outstanding at any stage.
        $unit = $DB->sql_concat('m.unittype', "':'", 'm.unitid');
        $unmarked = $DB->get_records_sql_menu("SELECT m.cmid, COUNT(DISTINCT $unit)
                                                 FROM {block_le_dashboard_mark} m
                                                 JOIN {block_le_dashboard_cm} c ON c.cmid = m.cmid
                                                WHERE $where AND $ready AND m.marked = 0 AND " . marking_rules::COUNTED . "
                                             GROUP BY m.cmid", $params);

        // Marks changed after release (D10): a student's unit against their own release time.
        $changedrel = $DB->get_records_sql_menu("SELECT m.cmid, COUNT(1)
                                                   FROM {block_le_dashboard_mark} m
                                                   JOIN {block_le_dashboard_cm} c ON c.cmid = m.cmid
                                                   JOIN {block_le_dashboard_stu} s ON s.cmid = m.cmid AND s.userid = m.unitid
                                                        AND m.unittype = 'user'
                                                  WHERE $where AND " . rules::CHANGED_RELEASED_SQL . "
                                                        AND " . marking_rules::COUNTED . "
                                               GROUP BY m.cmid", $params);

        // Per marker, per stage: who has what to do (the convenor view's breakdown).
        $marker = marking_rules::MARKER_SQL;
        $markers = [];
        $rs = $DB->get_recordset_sql("SELECT m.cmid, $marker AS marker, m.stage,
                                             SUM(CASE WHEN $ready THEN 1 ELSE 0 END) AS ready,
                                             SUM(CASE WHEN $ready AND m.marked = 1 THEN 1 ELSE 0 END) AS marked,
                                             SUM(CASE WHEN m.allocatedto IS NOT NULL THEN 1 ELSE 0 END) AS allocated
                                        FROM {block_le_dashboard_mark} m
                                        JOIN {block_le_dashboard_cm} c ON c.cmid = m.cmid
                                       WHERE $where AND $marker IS NOT NULL AND " . marking_rules::COUNTED . "
                                    GROUP BY m.cmid, $marker, m.stage", $params);
        foreach ($rs as $r) {
            $markers[(int)$r->cmid][(int)$r->marker][$r->stage] = $r;
        }
        $rs->close();

        // Academic conduct flags and extensions still to come, with My assessments' switches.
        $flagged = maconfig::shown('hint_plagiarism') ? marking_rules::flagged($where, $params) : [];
        $pending = maconfig::shown('hint_extensions') ? marking_rules::pending_extensions($where, $params, $now) : [];

        foreach ($cms as $cmid => $cm) {
            self::aggregate($cm, $students[$cmid] ?? null, $marking[$cmid] ?? [], (int)($unmarked[$cmid] ?? 0),
                (int)($changedrel[$cmid] ?? 0), $markers[$cmid] ?? [], $flagged[$cmid] ?? null, $pending[$cmid] ?? null);
        }
        return $cms;
    }

    /**
     * Puts the aggregates on one index record, as rules::classify() and row::build() read them.
     *
     * @param \stdClass $cm Index record; gains the fields below.
     * @param \stdClass|null $students total, submitted, unallocated, individual, exempt, firstreleased, lastreleased.
     * @param \stdClass[] $marking Per stage.
     * @param int $unmarked
     * @param int $changedrel
     * @param array $markers markerid => stage => ready, marked, allocated
     * @param \stdClass|null $flagged open, withheld
     * @param \stdClass|null $pending students, latest
     */
    public static function aggregate(
        \stdClass $cm,
        ?\stdClass $students,
        array $marking,
        int $unmarked,
        int $changedrel,
        array $markers,
        ?\stdClass $flagged,
        ?\stdClass $pending
    ): void {
        $stages = array_values(array_filter(explode(',', (string)$cm->stages)));
        $first = $stages ? ($marking[reset($stages)] ?? null) : null;
        $final = $stages ? ($marking[end($stages)] ?? null) : null;
        $cm->stagelist = $stages;
        $cm->marking = $marking;
        $cm->total = (int)($students->total ?? 0);
        $cm->submitted = (int)($students->submitted ?? 0);
        $cm->unallocated = $cm->hasallocation ? (int)($students->unallocated ?? 0) : 0;
        $cm->individual = (int)($students->individual ?? 0);
        $cm->exempt = (int)($students->exempt ?? 0);
        $cm->firstreleased = $students && $students->firstreleased ? (int)$students->firstreleased : null;
        $cm->timereleased = $students && $students->lastreleased ? (int)$students->lastreleased : null;
        $cm->units = $first ? (int)$first->ready : 0;
        $cm->released = $first ? (int)$first->released : 0;
        $cm->isgroup = $first && !empty($first->isgroup);
        $cm->unmarked = $unmarked;
        // The send step covers the units the final stage should have marked (D10).
        $cm->tosend = $final ? (int)$final->ready : 0;
        $cm->sent = $final ? (int)$final->sent : 0;
        $cm->early = $final ? (int)$final->early : 0;
        $cm->changedsent = $final ? (int)$final->changedsent : 0;
        $cm->changedrel = $changedrel;
        $cm->markingfinished = $cm->units > 0 && $unmarked === 0 && $final ? (int)$final->lastmarked : null;
        // The marking deadline the rules judge by: the assessment's own, or the default marking period's.
        [$cm->markingdue, $cm->defaultmarkingdue] = maconfig::markingdue($cm);
        $cm->markers = $markers;
        $cm->flagged = $flagged;
        $cm->pending = $pending;
        $cm->returned = marking_rules::returned($marking, $cm->due === null ? null : (int)$cm->due);
    }
}
