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

use block_le_hub_dashboard\external\add_note;
use block_le_hub_dashboard\external\find;
use block_le_hub_dashboard\external\get_view;
use block_le_hub_dashboard\external\ignore;
use block_le_hub_dashboard\external\refresh;
use block_le_hub_dashboard\external\save_view;
use block_le_hub_dashboard\local\notes;
use block_le_hub_dashboard\local\views;
use core_external\external_api;

/**
 * The web services the page calls: each one needs hub access, does what it says, and hands back the rendered view
 * with its state.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_le_hub_dashboard\external\base
 * @covers     \block_le_hub_dashboard\external\get_view
 * @covers     \block_le_hub_dashboard\external\refresh
 * @covers     \block_le_hub_dashboard\external\save_view
 * @covers     \block_le_hub_dashboard\external\ignore
 * @covers     \block_le_hub_dashboard\external\add_note
 * @covers     \block_le_hub_dashboard\external\find
 * @covers     \block_le_hub_dashboard\observer
 */
final class external_test extends \advanced_testcase {
    /** @var \block_le_hub_dashboard_generator */
    protected $gen;

    /**
     * The hub fixtures, one assessment in MTH2003 and hub1 logged in with a view of the Faculty of Science.
     */
    protected function hub(): void {
        $this->resetAfterTest();
        // Index writers observe events after commit, so no test transaction (as My assessments' tests run).
        $this->preventResetByRollback();
        $this->setAdminUser();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('block_le_hub_dashboard');
        \block_le_dashboard\fixture\steps::reset();
        $this->gen->hub_fixtures();
        $this->gen->assessment_with_marking_due(
            'summative',
            'assign',
            'Problem sheet 4',
            'MTH2003',
            '',
            '20 days ago',
            '2 days ago'
        );
        $this->gen->picked_categories('hub1', 'Faculty of Science');
        \block_le_dashboard\fixture\steps::instance()->catch_up();
        $this->setUser(\block_le_dashboard\fixture\steps::userid('hub1'));
    }

    /**
     * A reply: the view's HTML and the state it shows, decoded.
     *
     * @param array $reply
     * @return array [html, state]
     */
    protected static function reply(array $reply): array {
        return [$reply['html'], json_decode($reply['state'], true)];
    }

    /**
     * The view renders with its state; a person's search narrows it; the type is remembered.
     */
    public function test_get_view(): void {
        $this->hub();
        [$html, $state] = self::reply(external_api::clean_returnvalue(get_view::execute_returns(), get_view::execute()));
        $this->assertStringContainsString('Problem sheet 4', $html);
        $this->assertSame('summative', $state['type']);
        $this->assertSame((int)views::current((int)$this->user()->id)->id, $state['viewid']);

        $marker = \block_le_dashboard\fixture\steps::userid('marker1');
        [$html, $state] = self::reply(external_api::clean_returnvalue(
            get_view::execute_returns(),
            get_view::execute(-1, -1, 'both', $marker)
        ));
        $this->assertSame($marker, $state['personid']);
        $this->assertSame('both', $state['type']);
        $this->assertStringContainsString('Mo Marker', $html);
    }

    /**
     * Refresh re-reads what is on screen and answers with the view.
     */
    public function test_refresh(): void {
        $this->hub();
        [$html] = self::reply(external_api::clean_returnvalue(refresh::execute_returns(), refresh::execute()));
        $this->assertStringContainsString('Problem sheet 4', $html);
    }

    /**
     * Views: create, shape, allocation window, copy, rename, delete, each answering with the view now open.
     */
    public function test_save_view(): void {
        global $DB;
        $this->hub();
        $userid = (int)$this->user()->id;
        $mth = $DB->get_field('course', 'id', ['shortname' => 'MTH2003']);
        $arts = $DB->get_field('course_categories', 'id', ['idnumber' => 'ART']);

        [, $state] = self::reply(external_api::clean_returnvalue(
            save_view::execute_returns(),
            save_view::execute('create', 0, 'Arts team')
        ));
        $artsid = $state['viewid'];
        $this->assertSame('Arts team', views::get($artsid, $userid)->name);

        save_view::execute('shape', $artsid, '', json_encode(['cat' => [$arts], 'starts' => ['MTH2'], 'contains' => [],
            'course' => ['his1']]));
        $view = views::get($artsid, $userid);
        $this->assertSame([(int)$arts], $view->cat);
        $this->assertSame(['MTH2'], $view->starts);
        $this->assertSame([(int)$DB->get_field('course', 'id', ['shortname' => 'HIS1'])], $view->course);

        save_view::execute('allocdays', $artsid, '', '', 14);
        $this->assertSame(14, (int)views::get($artsid, $userid)->allocdays);

        [, $state] = self::reply(external_api::clean_returnvalue(
            save_view::execute_returns(),
            save_view::execute('copy', $artsid, 'Arts team (copy)')
        ));
        $this->assertNotSame($artsid, $state['viewid']);
        $this->assertSame(['MTH2'], views::get($state['viewid'], $userid)->starts);

        save_view::execute('rename', $state['viewid'], 'Humanities');
        $this->assertSame('Humanities', views::get($state['viewid'], $userid)->name);

        [, $after] = self::reply(external_api::clean_returnvalue(
            save_view::execute_returns(),
            save_view::execute('delete', $state['viewid'])
        ));
        $this->assertNull(views::get($state['viewid'], $userid));
        $this->assertNotSame($state['viewid'], $after['viewid']);

        $this->expectException(\moodle_exception::class);
        save_view::execute('shape', $artsid, '', json_encode(['course' => ['NOPE']]));
    }

    /**
     * Ignore takes an assessment or a module out of the view open; undo puts it back.
     */
    public function test_ignore_and_undo(): void {
        $this->hub();
        $userid = (int)$this->user()->id;
        $cmid = (int)\block_le_dashboard\fixture\steps::instance()->cm('Problem sheet 4')->id;
        [$html] = self::reply(external_api::clean_returnvalue(ignore::execute_returns(), ignore::execute('outcm', $cmid)));
        $this->assertStringNotContainsString('hub-row-name">Problem sheet 4', $html);
        $this->assertSame([$cmid], views::current($userid)->outcm);
        [$html] = self::reply(external_api::clean_returnvalue(ignore::execute_returns(), ignore::execute('outcm', $cmid, true)));
        $this->assertStringContainsString('Problem sheet 4', $html);
        $this->assertSame([], views::current($userid)->outcm);
    }

    /**
     * A note is kept against the assessment, and goes with it when the activity is deleted.
     */
    public function test_add_note(): void {
        $this->hub();
        $cm = \block_le_dashboard\fixture\steps::instance()->cm('Problem sheet 4');
        [$html] = self::reply(external_api::clean_returnvalue(
            add_note::execute_returns(),
            add_note::execute((int)$cm->id, 'Mo off sick', true)
        ));
        $this->assertStringContainsString('Mo off sick', $html);
        $notes = notes::for_cms([(int)$cm->id])[(int)$cm->id];
        $this->assertCount(1, $notes);
        $this->assertTrue($notes[0]['escalated']);
        $this->assertSame('Hana Hub', $notes[0]['author']);

        course_delete_module($cm->id);
        $this->assertSame([], notes::for_cms([(int)$cm->id]));

        $this->expectException(\invalid_parameter_exception::class);
        add_note::execute(999999, 'Nowhere');
    }

    /**
     * Find names staff and students the index knows, from two letters on.
     */
    public function test_find(): void {
        $this->hub();
        \block_le_dashboard\fixture\steps::instance()->have_submitted('student1', 'student2', 'Problem sheet 4');
        $this->gen->marked('student1', 'Problem sheet 4');
        \block_le_dashboard\fixture\steps::instance()->catch_up();
        $found = external_api::clean_returnvalue(find::execute_returns(), find::execute('m'));
        $this->assertSame(['staff' => [], 'students' => []], $found);
        $found = external_api::clean_returnvalue(find::execute_returns(), find::execute('Mo'));
        $this->assertSame(['Mo Marker'], array_column($found['staff'], 'name'));
        $found = external_api::clean_returnvalue(find::execute_returns(), find::execute('stud'));
        $this->assertSame(['Sam Student', 'Sue Student'], array_column($found['students'], 'name'));
        $this->assertSame([], $found['staff']);
    }

    /**
     * Without hub access every service refuses.
     */
    public function test_services_need_hub_access(): void {
        $this->hub();
        $this->setUser(\block_le_dashboard\fixture\steps::userid('marker1'));
        $this->expectException(\required_capability_exception::class);
        get_view::execute();
    }

    /**
     * The current user.
     *
     * @return \stdClass
     */
    protected function user(): \stdClass {
        global $USER;
        return $USER;
    }
}
