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

/**
 * Renders the dashboard for a view, year, type or person.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_view extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(self::state_parameters());
    }

    /**
     * Renders.
     *
     * @param int $viewid
     * @param int $yearid
     * @param string $type
     * @param int $personid
     * @return array
     */
    public static function execute(int $viewid = -1, int $yearid = -1, string $type = '', int $personid = 0): array {
        $state = self::validate_parameters(self::execute_parameters(), ['viewid' => $viewid, 'yearid' => $yearid, 'type' => $type,
            'personid' => $personid]);
        $userid = self::user();
        return self::render(self::dashboard($userid, $state));
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
