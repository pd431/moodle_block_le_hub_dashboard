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

namespace block_le_hub_dashboard\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use block_le_hub_dashboard\local\notes;

/**
 * Adds a hub note to an assessment (D11), then renders.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_note extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The assessment'),
            'note' => new external_value(PARAM_TEXT, 'The note'),
            'escalated' => new external_value(PARAM_BOOL, 'Whether it records that late marks were escalated',
                VALUE_DEFAULT, false),
        ] + self::state_parameters());
    }

    /**
     * Adds and renders.
     *
     * @param int $cmid
     * @param string $note
     * @param bool $escalated
     * @param int $viewid
     * @param int $yearid
     * @param string $type
     * @param int $personid
     * @return array
     */
    public static function execute(int $cmid, string $note, bool $escalated = false, int $viewid = -1, int $yearid = -1,
            string $type = '', int $personid = 0): array {
        global $DB;
        $p = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'note' => $note, 'escalated' => $escalated,
            'viewid' => $viewid, 'yearid' => $yearid, 'type' => $type, 'personid' => $personid]);
        $userid = self::user();
        if (!$DB->record_exists('block_le_dashboard_cm', ['cmid' => $p['cmid']])) {
            throw new \invalid_parameter_exception('Not an indexed assessment');
        }
        notes::add((int)$p['cmid'], $userid, $p['note'], (bool)$p['escalated']);
        return self::render(self::dashboard($userid, $p));
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return self::render_returns();
    }
}
