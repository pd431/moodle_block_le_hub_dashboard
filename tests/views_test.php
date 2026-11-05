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

use block_le_hub_dashboard\local\config;
use block_le_hub_dashboard\local\views;

/**
 * Saved views (P4, P5, P6): their life cycle, and the SQL and PHP forms of "is this assessment in the view" agreeing
 * for every kind of line.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_le_hub_dashboard\local\views
 */
final class views_test extends \advanced_testcase {
    /** @var \stdClass[] Courses by short name. */
    protected array $courses = [];

    /** @var \stdClass[] Categories by name. */
    protected array $cats = [];

    /** @var int[] An assessment per course, courseid => cmid. */
    protected array $cms = [];

    /**
     * Categories, courses and one indexed assessment per course.
     */
    protected function fixtures(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $this->cats['Science'] = $gen->create_category(['name' => 'Science']);
        $this->cats['Biology'] = $gen->create_category(['name' => 'Biology', 'parent' => $this->cats['Science']->id]);
        $this->cats['Arts'] = $gen->create_category(['name' => 'Arts']);
        $this->cats['History'] = $gen->create_category(['name' => 'History', 'parent' => $this->cats['Arts']->id]);
        $where = ['BIO1' => 'Biology', 'PHY1' => 'Science', 'HIS1' => 'History', 'MTH1001' => 'Arts', 'MTH2003' => 'Arts',
            'ARTBIO' => 'Arts'];
        foreach ($where as $code => $cat) {
            $this->courses[$code] = $gen->create_course(['shortname' => $code, 'category' => $this->cats[$cat]->id]);
            $cmid = 900000 + count($this->cms);
            $DB->insert_record('block_le_dashboard_cm', (object)['cmid' => $cmid, 'courseid' => $this->courses[$code]->id,
                'modname' => 'assign', 'adapter' => 'assign', 'name' => "$code work", 'assesstype' => 'summative',
                'stages' => 'marked']);
            $this->cms[(int)$this->courses[$code]->id] = $cmid;
        }
    }

    /**
     * Course ids by short name.
     *
     * @param string ...$codes
     * @return int[]
     */
    protected function ids(string ...$codes): array {
        return array_map(fn($c) => (int)$this->courses[$c]->id, $codes);
    }

    /**
     * Which assessments the SQL form picks out, as course short names.
     *
     * @param \stdClass $view
     * @return string[]
     */
    protected function sql_picks(\stdClass $view): array {
        global $DB;
        [$sql, $params] = views::in_view_sql($view);
        $courseids = $DB->get_fieldset_sql("SELECT c.courseid FROM {block_le_dashboard_cm} c WHERE $sql", $params);
        $out = [];
        foreach ($this->courses as $code => $course) {
            if (in_array((int)$course->id, array_map('intval', $courseids))) {
                $out[] = $code;
            }
        }
        return $out;
    }

    /**
     * Which assessments the PHP form picks out, as course short names.
     *
     * @param \stdClass $view
     * @return string[]
     */
    protected function php_picks(\stdClass $view): array {
        global $DB;
        $out = [];
        foreach ($this->courses as $code => $course) {
            $path = $DB->get_field('course_categories', 'path', ['id' => $course->category]);
            if (views::cm_in_view($view, $course, $path, $this->cms[(int)$course->id])) {
                $out[] = $code;
            }
        }
        return $out;
    }

    /**
     * A user's view shaped by rules, read back as all() gives it.
     *
     * @param int $userid
     * @param array $rules
     * @param array $exceptions kind => values
     * @return \stdClass
     */
    protected function view(int $userid, array $rules, array $exceptions = []): \stdClass {
        $id = views::create($userid, 'Test');
        views::set_shape($id, $userid, $rules);
        foreach ($exceptions as $kind => $values) {
            foreach ($values as $value) {
                views::add_exception($id, $userid, $kind, $value);
            }
        }
        return views::get($id, $userid);
    }

    /**
     * The SQL and PHP forms agree, and each kind of line does what it says.
     */
    public function test_in_view_sql_and_cm_in_view_agree(): void {
        $this->resetAfterTest();
        $this->fixtures();
        $user = $this->getDataGenerator()->create_user();
        $u = (int)$user->id;
        $cases = [
            'a department and everything under it' => [['cat' => [$this->cats['Science']->id]], [], ['BIO1', 'PHY1']],
            'a sub-department alone' => [['cat' => [$this->cats['Biology']->id]], [], ['BIO1']],
            'codes starting with' => [['starts' => ['mth']], [], ['MTH1001', 'MTH2003']],
            'codes containing' => [['contains' => ['BIO']], [], ['BIO1', 'ARTBIO']],
            'a module added by id' => [['course' => $this->ids('HIS1')], [], ['HIS1']],
            'a module added by code' => [['course' => ['his1']], [], ['HIS1']],
            'lines add up' => [['cat' => [$this->cats['History']->id], 'starts' => ['MTH2'], 'course' => $this->ids('PHY1')], [],
                ['PHY1', 'HIS1', 'MTH2003']],
            'a module taken out' => [['cat' => [$this->cats['Arts']->id]], ['outcourse' => $this->ids('ARTBIO')],
                ['HIS1', 'MTH1001', 'MTH2003']],
            'an assessment ignored' => [['cat' => [$this->cats['Arts']->id]],
                ['outcm' => [$this->cms[(int)$this->courses['HIS1']->id]]], ['MTH1001', 'MTH2003', 'ARTBIO']],
            'no lines match nothing' => [[], [], []],
        ];
        foreach ($cases as $label => [$rules, $exceptions, $expected]) {
            $view = $this->view($u, $rules, $exceptions);
            $this->assertSame($expected, $this->sql_picks($view), "SQL: $label");
            $this->assertSame($expected, $this->php_picks($view), "PHP: $label");
        }
    }

    /**
     * A module code nobody has is an error the editor shows (P5).
     */
    public function test_an_unknown_module_code_is_refused(): void {
        $this->resetAfterTest();
        $this->fixtures();
        $u = (int)$this->getDataGenerator()->create_user()->id;
        $id = views::create($u, 'Test');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('NOPE');
        views::set_shape($id, $u, ['course' => ['NOPE']]);
    }

    /**
     * The editor's count: modules the shape matches out of every module on the site.
     */
    public function test_module_counts(): void {
        $this->resetAfterTest();
        $this->fixtures();
        $u = (int)$this->getDataGenerator()->create_user()->id;
        $view = $this->view($u, ['starts' => ['MTH']], ['outcourse' => $this->ids('MTH1001')]);
        $this->assertSame([1, 6], views::module_counts($view));
    }

    /**
     * Views are the user's own: the last used opens, a copy carries the lines and the window, a delete forgets it.
     */
    public function test_life_cycle(): void {
        $this->resetAfterTest();
        $this->fixtures();
        $gen = $this->getDataGenerator();
        $u = (int)$gen->create_user()->id;
        $other = (int)$gen->create_user()->id;
        $this->assertNull(views::current($u));

        $a = views::create($u, 'Alpha');
        $b = views::create($u, 'Beta');
        views::set_shape($b, $u, ['cat' => [$this->cats['Arts']->id], 'starts' => ['MTH']]);
        views::set_alloc_days($b, $u, 21);
        views::add_exception($b, $u, 'outcourse', (int)$this->courses['ARTBIO']->id);
        $this->assertSame(['Alpha', 'Beta'], array_values(array_map(fn($v) => $v->name, views::all($u))));
        $this->assertSame($a, (int)views::current($u)->id, 'the first view until one is used');
        config::set_preference('view', $b, $u);
        $this->assertSame($b, (int)views::current($u)->id, 'the last used');
        $this->assertNull(views::get($b, $other), "another user's view is not theirs");

        $copy = views::create($u, 'Beta (copy)', $b);
        $view = views::get($copy, $u);
        $this->assertSame(21, (int)$view->allocdays);
        $this->assertSame([(int)$this->cats['Arts']->id], $view->cat);
        $this->assertSame(['MTH'], $view->starts);
        $this->assertSame($this->ids('ARTBIO'), $view->outcourse);

        views::rename($copy, $u, '  Gamma ');
        $this->assertSame('Gamma', views::get($copy, $u)->name);
        views::remove_exception($copy, $u, 'outcourse', (int)$this->courses['ARTBIO']->id);
        $this->assertSame([], views::get($copy, $u)->outcourse);

        views::delete($b, $u);
        $this->assertNull(views::get($b, $u));
        $this->assertSame(0, (int)config::preference('view', $u, 0), 'the deleted view is no longer the last used');
        $this->assertSame($a, (int)views::current($u)->id);

        $this->expectException(\moodle_exception::class);
        views::rename($a, $other, 'Mine now');
    }
}
