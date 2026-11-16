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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use block_le_hub_dashboard\local\testing;

/**
 * One batch of a view's rows (L2): the frame lists what to fetch, and the page asks for it a few assessments at a
 * time, so each call stays short and the groups fill as the rows arrive.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_rows extends base {
    /** The most assessments one call builds. */
    const MAX = 200;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmids' => new external_multiple_structure(new external_value(PARAM_INT, 'A course module id'), 'The assessments'),
        ] + self::state_parameters());
    }

    /**
     * Renders the rows.
     *
     * @param int[] $cmids
     * @param int $viewid
     * @param int $yearid
     * @param string $type
     * @param int $personid
     * @param bool $witheditor Unused here: the editor comes with the frame.
     * @return array
     */
    public static function execute(
        array $cmids,
        int $viewid = -1,
        int $yearid = -1,
        string $type = '',
        int $personid = 0,
        bool $witheditor = false
    ): array {
        $p = self::validate_parameters(self::execute_parameters(), ['cmids' => $cmids, 'viewid' => $viewid, 'yearid' => $yearid,
            'type' => $type, 'personid' => $personid, 'witheditor' => $witheditor]);
        if (count($p['cmids']) > self::MAX) {
            throw new \invalid_parameter_exception('Too many assessments in one call');
        }
        $userid = self::user();
        testing::slow_rows();
        return self::render_rows(self::dashboard($userid, $p), $p['cmids']);
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'The rows, each a list item carrying its group'),
            'count' => new external_value(PARAM_INT, 'How many rows'),
        ]);
    }
}
