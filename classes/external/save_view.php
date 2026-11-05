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
use block_le_hub_dashboard\local\config;
use block_le_hub_dashboard\local\views;

/**
 * Creates, copies, renames, deletes or reshapes one of the user's saved views, then renders the dashboard on it.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_view extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_ALPHA, 'create, copy, rename, delete, shape or allocdays'),
            'viewid' => new external_value(PARAM_INT, 'The view (the source, for copy)', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'The name, for create, copy and rename', VALUE_DEFAULT, ''),
            'rules' => new external_value(PARAM_RAW,
                'JSON {cat: [ids], starts: [codes], contains: [codes], course: [ids]}, for shape',
                VALUE_DEFAULT, ''),
            'allocdays' => new external_value(PARAM_INT, 'The allocation window, for allocdays', VALUE_DEFAULT, -1),
        ] + self::state_parameters());
    }

    /**
     * Saves and renders.
     *
     * @param string $action
     * @param int $viewid
     * @param string $name
     * @param string $rules
     * @param int $allocdays
     * @param int $stateviewid
     * @param int $yearid
     * @param string $type
     * @param int $personid
     * @return array
     */
    public static function execute(string $action, int $viewid = 0, string $name = '', string $rules = '', int $allocdays = -1,
            int $stateviewid = -1, int $yearid = -1, string $type = '', int $personid = 0): array {
        $p = self::validate_parameters(self::execute_parameters(), ['action' => $action, 'viewid' => $viewid, 'name' => $name,
            'rules' => $rules, 'allocdays' => $allocdays, 'yearid' => $yearid, 'type' => $type, 'personid' => $personid]);
        $userid = self::user();
        $open = (int)$p['viewid'];
        switch ($p['action']) {
            case 'create':
                $open = views::create($userid, $p['name'] !== '' ? $p['name'] : get_string('newview_name',
                    'block_le_hub_dashboard'));
                break;
            case 'copy':
                $open = views::create($userid, $p['name'], (int)$p['viewid']);
                break;
            case 'rename':
                views::rename((int)$p['viewid'], $userid, $p['name']);
                break;
            case 'delete':
                views::delete((int)$p['viewid'], $userid);
                $open = -1;
                break;
            case 'shape':
                $decoded = json_decode($p['rules'], true);
                if (!is_array($decoded)) {
                    throw new \invalid_parameter_exception('rules must be a JSON object');
                }
                views::set_shape((int)$p['viewid'], $userid, $decoded);
                break;
            case 'allocdays':
                views::set_alloc_days((int)$p['viewid'], $userid, (int)$p['allocdays']);
                break;
            default:
                throw new \invalid_parameter_exception('Unknown action');
        }
        if ($open === -1) {
            unset_user_preference('block_le_hub_dashboard_view', $userid);
        }
        return self::render(self::dashboard($userid, ['viewid' => $open, 'yearid' => $p['yearid'], 'type' => $p['type'],
            'personid' => 0]));
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
