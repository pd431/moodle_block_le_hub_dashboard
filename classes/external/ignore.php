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
use block_le_hub_dashboard\local\views;

/**
 * Takes a module or an assessment out of a view, or puts it back (P6).
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ignore extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'kind' => new external_value(PARAM_ALPHA, 'outcourse (a module) or outcm (an assessment)'),
            'value' => new external_value(PARAM_INT, 'The course id or course module id'),
            'undo' => new external_value(PARAM_BOOL, 'Put it back instead', VALUE_DEFAULT, false),
        ] + self::state_parameters());
    }

    /**
     * Ignores or puts back, then renders.
     *
     * @param string $kind
     * @param int $value
     * @param bool $undo
     * @param int $viewid
     * @param int $yearid
     * @param string $type
     * @param int $personid
     * @return array
     */
    public static function execute(string $kind, int $value, bool $undo = false, int $viewid = -1, int $yearid = -1,
            string $type = '', int $personid = 0): array {
        $p = self::validate_parameters(self::execute_parameters(), ['kind' => $kind, 'value' => $value, 'undo' => $undo,
            'viewid' => $viewid, 'yearid' => $yearid, 'type' => $type, 'personid' => $personid]);
        $userid = self::user();
        $dashboard = self::dashboard($userid, $p);
        $open = $dashboard->state()['viewid'];
        if ($open) {
            if ($p['undo']) {
                views::remove_exception($open, $userid, $p['kind'], (int)$p['value']);
            } else {
                views::add_exception($open, $userid, $p['kind'], (int)$p['value']);
            }
        }
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
