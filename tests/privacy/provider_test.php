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

use block_le_hub_dashboard\local\config;
use block_le_hub_dashboard\local\notes;
use block_le_hub_dashboard\local\views;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Privacy: views and notes are the user's data in the system context; a deletion removes their views and keeps
 * their notes as the hub's record with the author made anonymous.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_le_hub_dashboard\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * Metadata names every table and preference.
     */
    public function test_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('block_le_hub_dashboard'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('block_le_hub_dashboard_view', $names);
        $this->assertContains('block_le_hub_dashboard_note', $names);
        foreach (config::PREFERENCES as $name) {
            $this->assertContains('block_le_hub_dashboard_' . $name, $names);
        }
    }

    /**
     * Export, then delete for one user and for everyone.
     */
    public function test_export_and_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $hana = $gen->create_user();
        $hugo = $gen->create_user();
        $outsider = $gen->create_user();
        $system = \context_system::instance();

        $view = views::create((int)$hana->id, 'Science team');
        views::set_shape($view, (int)$hana->id, ['starts' => ['MTH']]);
        views::create((int)$hugo->id, 'History');
        notes::add(42, (int)$hana->id, 'Mo off sick', true);
        notes::add(42, (int)$hugo->id, 'Chased again');
        config::set_preference('type', 'both', (int)$hana->id);

        $this->assertSame([$system->id], array_map('intval', provider::get_contexts_for_userid((int)$hana->id)->get_contextids()));
        $this->assertEmpty(provider::get_contexts_for_userid((int)$outsider->id)->get_contextids());
        $userlist = new userlist($system, 'block_le_hub_dashboard');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([(int)$hana->id, (int)$hugo->id], array_map('intval', $userlist->get_userids()));

        $this->export_context_data_for_user((int)$hana->id, $system, 'block_le_hub_dashboard');
        $data = writer::with_context($system)->get_data([get_string('pluginname', 'block_le_hub_dashboard')]);
        $this->assertSame('Science team', $data->views[0]->name);
        $this->assertSame('Mo off sick', $data->notes[0]->note);
        provider::export_user_preferences((int)$hana->id);
        $prefs = writer::with_context($system)->get_user_preferences('block_le_hub_dashboard');
        $this->assertSame('both', $prefs->block_le_hub_dashboard_type->value);

        provider::delete_data_for_user(new approved_contextlist($hana, 'block_le_hub_dashboard', [$system->id]));
        $this->assertFalse($DB->record_exists('block_le_hub_dashboard_view', ['userid' => $hana->id]));
        $this->assertFalse($DB->record_exists('block_le_hub_dashboard_rule', ['viewid' => $view]));
        $this->assertTrue($DB->record_exists('block_le_hub_dashboard_view', ['userid' => $hugo->id]));
        $this->assertSame(2, $DB->count_records('block_le_hub_dashboard_note', ['cmid' => 42]), 'the hub keeps its log');
        $this->assertSame(0, $DB->count_records('block_le_hub_dashboard_note', ['userid' => $hana->id]), 'without the author');

        provider::delete_data_for_users(new approved_userlist($system, 'block_le_hub_dashboard', [(int)$hugo->id]));
        $this->assertFalse($DB->record_exists('block_le_hub_dashboard_view', ['userid' => $hugo->id]));
        $this->assertSame(0, $DB->count_records('block_le_hub_dashboard_note', ['userid' => $hugo->id]));

        provider::delete_data_for_all_users_in_context($system);
        $this->assertSame(0, $DB->count_records('block_le_hub_dashboard_note'));
        $this->assertSame(0, $DB->count_records('block_le_hub_dashboard_view'));
    }
}
