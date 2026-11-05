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
 * Settings: the two numbers the rules need (D4, P10).
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_le_hub_dashboard/riskdays',
        new lang_string('riskdays', 'block_le_hub_dashboard'),
        new lang_string('riskdays_desc', 'block_le_hub_dashboard'),
        \block_le_hub_dashboard\local\config::DEFAULT_RISK_DAYS,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'block_le_hub_dashboard/allocdays',
        new lang_string('allocdays', 'block_le_hub_dashboard'),
        new lang_string('allocdays_desc', 'block_le_hub_dashboard'),
        \block_le_hub_dashboard\local\config::DEFAULT_ALLOC_DAYS,
        PARAM_INT
    ));
}
