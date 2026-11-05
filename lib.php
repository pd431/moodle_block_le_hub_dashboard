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

/**
 * Hub dashboard library callbacks.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The user preference the page's script saves from the browser (core_user_update_user_preferences): the type
 * filter, which is applied without reloading the view. The rest are saved by the services.
 *
 * @return array
 */
function block_le_hub_dashboard_user_preferences(): array {
    return [
        'block_le_hub_dashboard_type' => [
            'type' => PARAM_ALPHA,
            'null' => NULL_NOT_ALLOWED,
            'default' => 'summative',
            'choices' => \block_le_hub_dashboard\local\dashboard::TYPES,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}
