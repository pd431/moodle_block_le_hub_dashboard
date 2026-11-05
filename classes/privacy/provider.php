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

namespace block_le_hub_dashboard\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy: saved views are a user's own; hub notes name their author. Both live in the system context.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_le_hub_dashboard_view', [
            'userid' => 'privacy:metadata:view:userid',
            'name' => 'privacy:metadata:view:name',
        ], 'privacy:metadata:view');
        $collection->add_database_table('block_le_hub_dashboard_note', [
            'userid' => 'privacy:metadata:note:userid',
            'note' => 'privacy:metadata:note:note',
            'timecreated' => 'privacy:metadata:note:timecreated',
        ], 'privacy:metadata:note');
        foreach (\block_le_hub_dashboard\local\config::PREFERENCES as $name) {
            $collection->add_user_preference('block_le_hub_dashboard_' . $name, 'privacy:metadata:preference:' . $name);
        }
        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if (
            $DB->record_exists('block_le_hub_dashboard_view', ['userid' => $userid])
                || $DB->record_exists('block_le_hub_dashboard_note', ['userid' => $userid])
        ) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {block_le_hub_dashboard_view}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {block_le_hub_dashboard_note}', []);
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $views = array_values($DB->get_records(
                'block_le_hub_dashboard_view',
                ['userid' => $userid],
                'name',
                'id, name, allocdays, timecreated'
            ));
            $notes = array_values($DB->get_records(
                'block_le_hub_dashboard_note',
                ['userid' => $userid],
                'timecreated',
                'id, cmid, note, escalated, timecreated'
            ));
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_le_hub_dashboard')],
                (object)['views' => $views, 'notes' => $notes]
            );
        }
    }

    #[\Override]
    public static function export_user_preferences(int $userid): void {
        foreach (\block_le_hub_dashboard\local\config::PREFERENCES as $name) {
            $value = get_user_preferences('block_le_hub_dashboard_' . $name, null, $userid);
            if ($value !== null) {
                writer::export_user_preference(
                    'block_le_hub_dashboard',
                    'block_le_hub_dashboard_' . $name,
                    $value,
                    get_string('privacy:metadata:preference:' . $name, 'block_le_hub_dashboard')
                );
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('block_le_hub_dashboard_rule');
            $DB->delete_records('block_le_hub_dashboard_view');
            $DB->delete_records('block_le_hub_dashboard_note');
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::delete_user((int)$contextlist->get_user()->id);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user((int)$userid);
        }
    }

    /**
     * Deletes one user's views and rules; their notes stay as the hub's record, with the author made anonymous.
     *
     * @param int $userid
     */
    protected static function delete_user(int $userid): void {
        global $DB;
        $views = $DB->get_fieldset_select('block_le_hub_dashboard_view', 'id', 'userid = :u', ['u' => $userid]);
        if ($views) {
            [$insql, $params] = $DB->get_in_or_equal($views, SQL_PARAMS_NAMED);
            $DB->delete_records_select('block_le_hub_dashboard_rule', "viewid $insql", $params);
        }
        $DB->delete_records('block_le_hub_dashboard_view', ['userid' => $userid]);
        $DB->set_field('block_le_hub_dashboard_note', 'userid', 0, ['userid' => $userid]);
    }
}
