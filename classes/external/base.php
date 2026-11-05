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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use block_le_hub_dashboard\local\access;
use block_le_hub_dashboard\local\dashboard;

/**
 * What every hub service shares: the state parameters (view, year, type, person), the access check and the
 * rendered reply.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends external_api {
    /**
     * The state parameters.
     *
     * @return array
     */
    protected static function state_parameters(): array {
        return [
            'viewid' => new external_value(PARAM_INT, 'Saved view id, 0 for none, -1 for the last used', VALUE_DEFAULT, -1),
            'yearid' => new external_value(
                PARAM_INT,
                'Academic year id, 0 for all, -1 for the saved or default year',
                VALUE_DEFAULT,
                -1
            ),
            'type' => new external_value(
                PARAM_ALPHA,
                'summative, formative, both or empty for the saved choice',
                VALUE_DEFAULT,
                ''
            ),
            'personid' => new external_value(PARAM_INT, 'A person whose assessments to show, 0 for none', VALUE_DEFAULT, 0),
        ];
    }

    /**
     * Checks the user may use the dashboard.
     *
     * @return int The user id.
     */
    protected static function user(): int {
        global $USER;
        self::validate_context(\context_user::instance($USER->id));
        access::require_allowed((int)$USER->id);
        return (int)$USER->id;
    }

    /**
     * The dashboard for a state.
     *
     * @param int $userid
     * @param array $state viewid, yearid, type, personid
     * @return dashboard
     */
    protected static function dashboard(int $userid, array $state): dashboard {
        return new dashboard(
            $userid,
            $state['viewid'] < 0 ? null : (int)$state['viewid'],
            $state['yearid'] < 0 ? null : (int)$state['yearid'],
            $state['type'] === '' ? null : $state['type'],
            (int)$state['personid']
        );
    }

    /**
     * The rendered dashboard and its state.
     *
     * @param dashboard $dashboard
     * @return array
     */
    protected static function render(dashboard $dashboard): array {
        global $PAGE;
        $output = $PAGE->get_renderer('core');
        $context = $dashboard->export();
        return ['html' => $output->render_from_template('block_le_hub_dashboard/view', $context), 'state' => $context['state']];
    }

    /**
     * The rendered reply's structure.
     *
     * @return external_single_structure
     */
    protected static function render_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered dashboard'),
            'state' => new external_value(PARAM_RAW, 'The state shown, as JSON'),
        ]);
    }
}
