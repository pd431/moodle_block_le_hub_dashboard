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
 * Web services, all AJAX-only and acting on the logged-in hub user.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_le_hub_dashboard_get_view' => [
        'classname' => '\block_le_hub_dashboard\external\get_view',
        'description' => 'Render the Hub dashboard for one saved view, year and type, or for one person.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'readonlysession' => true,
    ],
    'block_le_hub_dashboard_refresh' => [
        'classname' => '\block_le_hub_dashboard\external\refresh',
        'description' => 'Re-index what the Hub dashboard shows, inline or queued, then render it again.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'block_le_hub_dashboard_save_view' => [
        'classname' => '\block_le_hub_dashboard\external\save_view',
        'description' => 'Create, change, copy, rename or delete one of the current user\'s saved views.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'block_le_hub_dashboard_ignore' => [
        'classname' => '\block_le_hub_dashboard\external\ignore',
        'description' => 'Take a module or an assessment out of a view, or put it back.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'block_le_hub_dashboard_add_note' => [
        'classname' => '\block_le_hub_dashboard\external\add_note',
        'description' => 'Add a hub note to an assessment.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'block_le_hub_dashboard_find' => [
        'classname' => '\block_le_hub_dashboard\external\find',
        'description' => 'People matching the Find box.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'readonlysession' => true,
    ],
];
