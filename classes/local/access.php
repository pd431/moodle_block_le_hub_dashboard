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
 * Who can open the Hub dashboard (D1): anyone holding block/le_hub_dashboard:view at system level or on any course
 * category. Every hub user then sees the whole institution, shaped by their own saved views (D6): the category a
 * role was given on grants access, it does not fence the list.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access {
    /** The capability. */
    const CAPABILITY = 'block/le_hub_dashboard:view';

    /**
     * Whether a user may open the dashboard.
     *
     * @param int $userid
     * @return bool
     */
    public static function allowed(int $userid): bool {
        if (has_capability(self::CAPABILITY, \context_system::instance(), $userid)) {
            return true;
        }
        // A role on a category: the capability is checked on each category the user holds a role in, so a
        // prohibit higher up still wins.
        return (bool)self::categories_with_role($userid);
    }

    /**
     * Throws unless the user may open the dashboard.
     *
     * @param int $userid
     */
    public static function require_allowed(int $userid): void {
        if (!self::allowed($userid)) {
            throw new \required_capability_exception(\context_system::instance(), self::CAPABILITY, 'nopermissions', '');
        }
    }

    /**
     * The categories the user holds the capability on through a role assigned there.
     *
     * @param int $userid
     * @return int[] Category ids.
     */
    protected static function categories_with_role(int $userid): array {
        global $DB;
        $contexts = $DB->get_records_sql("SELECT DISTINCT ctx.id, ctx.instanceid
                                             FROM {role_assignments} ra
                                             JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :level
                                            WHERE ra.userid = :userid", ['level' => CONTEXT_COURSECAT, 'userid' => $userid]);
        $ids = [];
        foreach ($contexts as $ctx) {
            if (has_capability(self::CAPABILITY, \context::instance_by_id($ctx->id), $userid)) {
                $ids[] = (int)$ctx->instanceid;
            }
        }
        return $ids;
    }
}
