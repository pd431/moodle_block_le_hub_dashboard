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

/**
 * Hub notes (D11): a dated log per assessment, newest first, with the author; a note can record that late marks
 * were escalated. Hub staff only; notes are not edited after saving.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notes {
    /** The notes table. */
    const TABLE = 'block_le_hub_dashboard_note';

    /**
     * Adds a note.
     *
     * @param int $cmid
     * @param int $userid The author.
     * @param string $text
     * @param bool $escalated Whether the note records an escalation.
     * @return int The note id.
     */
    public static function add(int $cmid, int $userid, string $text, bool $escalated = false): int {
        global $DB;
        $text = trim($text);
        if ($text === '') {
            throw new \invalid_parameter_exception('An empty note');
        }
        return (int)$DB->insert_record(self::TABLE, (object)['cmid' => $cmid, 'userid' => $userid, 'note' => $text,
            'escalated' => $escalated ? 1 : 0, 'timecreated' => time()]);
    }

    /**
     * The notes of a set of assessments, newest first, with their authors' names.
     *
     * @param int[] $cmids
     * @return array cmid => list of (id, text, escalated, timecreated, author, authorurl)
     */
    public static function for_cms(array $cmids): array {
        global $DB;
        if (!$cmids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $cmids), SQL_PARAMS_NAMED);
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', true)->selects;
        $records = $DB->get_records_sql("SELECT n.id, n.cmid, n.note, n.escalated, n.timecreated, n.userid $namefields
                                           FROM {" . self::TABLE . "} n
                                           LEFT JOIN {user} u ON u.id = n.userid
                                          WHERE n.cmid $insql
                                       ORDER BY n.timecreated DESC, n.id DESC", $params);
        $out = [];
        foreach ($records as $r) {
            $out[(int)$r->cmid][] = [
                'id' => (int)$r->id,
                'text' => $r->note,
                'escalated' => !empty($r->escalated),
                'time' => (int)$r->timecreated,
                'author' => $r->userid && !empty($r->lastname) ? fullname($r) : get_string('unknownuser', 'block_le_hub_dashboard'),
                'authorurl' => $r->userid ? (new \moodle_url('/user/profile.php', ['id' => $r->userid]))->out(false) : null,
            ];
        }
        return $out;
    }

    /**
     * Deletes every note of an assessment (when the activity goes).
     *
     * @param int $cmid
     */
    public static function remove_cm(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }
}
