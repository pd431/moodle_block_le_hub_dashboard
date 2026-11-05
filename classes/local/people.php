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
use block_le_dashboard\local\index\indexer;
use block_le_dashboard\local\output\format;

/**
 * The people on a row (module leads, markers) and the Find box's people (P2, P6): who they are, where their
 * profile is, what they owe. Names link to profiles; no email is shown (Pierre's ruling), though markers with work
 * outstanding can be emailed in one go where Moodle would show the hub their address.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class people {
    /** How many people the Find box offers. */
    const FIND_LIMIT = 6;

    /**
     * People whose name matches the Find box, staff (anyone the index knows as a marker) and students (anyone it
     * knows as a student), institution-wide (D6).
     *
     * @param string $text
     * @return array staff => [[id, name]], students => [[id, name]]
     */
    public static function find(string $text): array {
        global $DB;
        $text = trim($text);
        if (\core_text::strlen($text) < 2) {
            return ['staff' => [], 'students' => []];
        }
        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $like = $DB->sql_like($fullname, ':name', false, false);
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', true)->selects;
        $out = [];
        $sources = [
            'staff' => "SELECT DISTINCT m.allocatedto AS id FROM {block_le_dashboard_mark} m WHERE m.allocatedto IS NOT NULL
                        UNION SELECT DISTINCT m.markedby AS id FROM {block_le_dashboard_mark} m WHERE m.markedby IS NOT NULL",
            'students' => "SELECT DISTINCT s.userid AS id FROM {block_le_dashboard_stu} s",
        ];
        foreach ($sources as $kind => $ids) {
            $records = $DB->get_records_sql("SELECT u.id $fields
                                               FROM {user} u
                                              WHERE u.deleted = 0 AND u.id IN ($ids) AND $like
                                           ORDER BY u.lastname, u.firstname", ['name' => '%' . $DB->sql_like_escape($text) . '%'],
                0, self::FIND_LIMIT);
            $out[$kind] = array_values(array_map(fn($u) => ['id' => (int)$u->id, 'name' => fullname($u)], $records));
        }
        return $out;
    }

    /**
     * SQL on the index (alias c) for the assessments one person has a part in: as a marker (allocated or marked) or
     * as a student. The whole institution, whatever the view (D6).
     *
     * @param int $userid
     * @return array [sql, params]
     */
    public static function scope_sql(int $userid): array {
        return ["(c.cmid IN (SELECT pm.cmid FROM {block_le_dashboard_mark} pm WHERE pm.allocatedto = :pu1 OR pm.markedby = :pu2)
                  OR c.cmid IN (SELECT ps.cmid FROM {block_le_dashboard_stu} ps WHERE ps.userid = :pu3))",
            ['pu1' => $userid, 'pu2' => $userid, 'pu3' => $userid]];
    }

    /**
     * Where one person stands on each of a set of assessments: a marker's allocated, marked and to-do counts, or a
     * student's state. For the status column when the list is one person's.
     *
     * @param int $userid
     * @param \stdClass[] $cms Aggregated index records (query::aggregates()), keyed by cmid.
     * @param int $now
     * @return string[] cmid => sentence
     */
    public static function statuses(int $userid, array $cms, int $now): array {
        global $DB;
        if (!$cms) {
            return [];
        }
        $person = \core_user::get_user($userid);
        $name = $person ? fullname($person) : get_string('unknownuser', 'block_le_hub_dashboard');
        [$insql, $params] = $DB->get_in_or_equal(array_keys($cms), SQL_PARAMS_NAMED);
        $students = $DB->get_records_sql("SELECT s.cmid, s.submitted, s.timereleased
                                            FROM {block_le_dashboard_stu} s
                                           WHERE s.userid = :userid AND s.cmid $insql", $params + ['userid' => $userid]);
        $out = [];
        foreach ($cms as $cmid => $cm) {
            if (isset($cm->markers[$userid])) {
                $allocated = 0;
                $marked = 0;
                $ready = 0;
                foreach ($cm->markers[$userid] as $stage) {
                    $allocated = max($allocated, (int)$stage->allocated);
                    $marked += (int)$stage->marked;
                    $ready += (int)$stage->ready;
                }
                $out[$cmid] = get_string('marker_status', 'block_le_hub_dashboard', ['name' => $name,
                    'allocated' => $allocated ?: $ready, 'marked' => $marked, 'todo' => max(0, $ready - $marked)]);
            } else if (isset($students[$cmid])) {
                $s = $students[$cmid];
                $key = $s->timereleased ? 'student_released' : ($s->submitted ? 'student_submitted' : 'student_notsubmitted');
                $out[$cmid] = get_string($key, 'block_le_hub_dashboard');
            }
        }
        return $out;
    }

    /**
     * The module leads of a set of courses: users with My assessments' module convenor role on the course.
     *
     * @param int[] $courseids
     * @return array courseid => [[name, url]]
     */
    public static function convenors(array $courseids): array {
        global $DB;
        $roleids = maconfig::role_ids('moduleconvenorrole');
        if (!$courseids || !$roleids) {
            return [];
        }
        [$csql, $cparams] = $DB->get_in_or_equal(array_unique($courseids), SQL_PARAMS_NAMED, 'co');
        [$rsql, $rparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'ro');
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', true)->selects;
        $rs = $DB->get_recordset_sql("SELECT DISTINCT ctx.instanceid AS courseid, u.id $fields
                                        FROM {role_assignments} ra
                                        JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :level
                                        JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
                                       WHERE ra.roleid $rsql AND ctx.instanceid $csql
                                    ORDER BY u.lastname, u.firstname", $cparams + $rparams + ['level' => CONTEXT_COURSE]);
        $out = [];
        foreach ($rs as $r) {
            $out[(int)$r->courseid][] = ['name' => fullname($r), 'url' => self::profile((int)$r->id)];
        }
        $rs->close();
        return $out;
    }

    /**
     * The markers of an assessment, with their bars per stage and what they still owe, sorted by name.
     *
     * @param \stdClass $cm Aggregated index record, with markers (markerid => stage => ready, marked, allocated).
     * @param int $viewerid Who is looking, for whether an email may be offered.
     * @return array markers => [[name, url, bars, todo, email]], outstanding => mailto of those with work to do, or null
     */
    public static function markers(\stdClass $cm, int $viewerid): array {
        global $DB;
        if (empty($cm->markers)) {
            return ['markers' => [], 'outstanding' => null];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($cm->markers), SQL_PARAMS_NAMED);
        $fields = implode(', ', \core_user\fields::get_name_fields());
        $users = $DB->get_records_select('user', "id $insql", $params, '', "id, email, maildisplay, deleted, $fields");
        $active = array_flip(indexer::markers((int)$cm->courseid));
        $context = \context_course::instance((int)$cm->courseid);
        $canseeemail = has_capability('moodle/course:useremail', $context, $viewerid);
        $markers = [];
        $emails = [];
        foreach ($cm->markers as $markerid => $bystage) {
            $user = $users[$markerid] ?? null;
            $name = $user ? fullname($user) : get_string('unknownuser', 'block_le_hub_dashboard');
            if ($user && !isset($active[$markerid])) {
                $name = get_string('formermarker', 'block_le_hub_dashboard', $name);
            }
            $bars = [];
            $todo = 0;
            foreach ($bystage as $stage => $r) {
                $bars[] = format::bar($stage, format::stage($stage), (int)$r->marked, (int)$r->ready);
                $todo += max(0, (int)$r->ready - (int)$r->marked);
            }
            if ($todo > 0 && $user && empty($user->deleted) && validate_email($user->email)
                    && ($canseeemail || (int)$user->maildisplay !== \core_user::MAILDISPLAY_HIDE)) {
                $emails[] = $user->email;
            }
            $markers[] = ['name' => $name, 'url' => $user ? self::profile((int)$markerid) : null, 'bars' => $bars,
                'todo' => $todo, 'hastodo' => $todo > 0];
        }
        \core_collator::asort_array_of_arrays_by_key($markers, 'name');
        return ['markers' => array_values($markers), 'outstanding' => $emails ? 'mailto:' . implode(',', $emails) : null];
    }

    /**
     * A user's profile page.
     *
     * @param int $userid
     * @return string
     */
    public static function profile(int $userid): string {
        return (new \moodle_url('/user/profile.php', ['id' => $userid]))->out(false);
    }
}
