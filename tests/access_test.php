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

namespace block_le_hub_dashboard;

use block_le_hub_dashboard\local\access;

/**
 * Who can open the hub (D1): the capability at system level or on a category, and nothing else; without it the
 * block renders nothing even when it is on the page.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_le_hub_dashboard\local\access
 * @covers     \block_le_hub_dashboard
 */
final class access_test extends \advanced_testcase {
    /**
     * A role with the capability, assigned at system level or on a category, opens the hub; a teacher's role on a
     * course does not.
     */
    public function test_capability_at_system_or_category(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $roleid = $gen->create_role(['shortname' => 'hub']);
        assign_capability(access::CAPABILITY, CAP_ALLOW, $roleid, \context_system::instance()->id);
        $cat = $gen->create_category();
        $course = $gen->create_course(['category' => $cat->id]);

        $atsystem = $gen->create_user();
        role_assign($roleid, $atsystem->id, \context_system::instance()->id);
        $oncategory = $gen->create_user();
        role_assign($roleid, $oncategory->id, \context_coursecat::instance($cat->id)->id);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $nobody = $gen->create_user();

        $this->assertTrue(access::allowed((int)$atsystem->id));
        $this->assertTrue(access::allowed((int)$oncategory->id));
        $this->assertFalse(access::allowed((int)$teacher->id), 'a course role is not hub access');
        $this->assertFalse(access::allowed((int)$nobody->id));
        $this->assertFalse(access::allowed((int)$DB->get_field('user', 'id', ['username' => 'guest'])));

        // A prohibit higher up wins over an allow on a sub-category.
        $sub = $gen->create_category(['parent' => $cat->id]);
        $denied = $gen->create_user();
        $prohibit = $gen->create_role(['shortname' => 'nohub']);
        assign_capability(access::CAPABILITY, CAP_PROHIBIT, $prohibit, \context_system::instance()->id);
        role_assign($prohibit, $denied->id, \context_system::instance()->id);
        role_assign($roleid, $denied->id, \context_coursecat::instance($sub->id)->id);
        $this->assertFalse(access::allowed((int)$denied->id));

        $this->expectException(\required_capability_exception::class);
        access::require_allowed((int)$nobody->id);
    }

    /**
     * The block is empty for anyone without the capability, and has content for a hub user.
     */
    public function test_block_content_follows_access(): void {
        global $PAGE;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $roleid = $gen->create_role(['shortname' => 'hub']);
        assign_capability(access::CAPABILITY, CAP_ALLOW, $roleid, \context_system::instance()->id);
        $hub = $gen->create_user();
        role_assign($roleid, $hub->id, \context_system::instance()->id);
        $other = $gen->create_user();

        $PAGE->set_url('/my/index.php');
        $PAGE->set_context(\context_system::instance());
        foreach ([[$other, true], [$hub, false]] as [$user, $empty]) {
            $this->setUser($user);
            $block = block_instance('le_hub_dashboard');
            $block->page = $PAGE;
            $this->assertSame($empty, $block->is_empty(), "empty for {$user->username}: " . var_export($empty, true));
        }
    }
}
