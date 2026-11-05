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

use block_le_dashboard\fixture\steps;
use block_le_dashboard\fixture\unavailable;
use block_le_hub_dashboard\local\access;
use block_le_hub_dashboard\local\config;
use block_le_hub_dashboard\local\notes;
use block_le_hub_dashboard\local\views;

defined('MOODLE_INTERNAL') || die();

// The sentences class lives beside the generator; both are loaded together.
require_once(__DIR__ . '/sentences.php');

/**
 * The hub's test fixtures: the sentences of the hub scopes that write to the site, run the same way by the Behat
 * context and by the PHPUnit scenario tests (hub_testcase). Assessments, submissions and marks come from My
 * assessments' fixtures (block_le_dashboard\fixture\steps), so the two plugins' tests build the same data.
 *
 * @package    block_le_hub_dashboard
 * @category   test
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_le_hub_dashboard_generator extends component_generator_base {
    /** The hub fixture's students, in the order "both submissions" means. */
    const STUDENTS = ['student1', 'student2'];

    /** Who marks when a sentence names no marker. */
    const MARKER = 'marker1';

    /**
     * Loads My assessments' generator, and with it the fixture classes this one writes through.
     *
     * @param testing_data_generator $datagenerator
     */
    public function __construct(testing_data_generator $datagenerator) {
        parent::__construct($datagenerator);
        $datagenerator->get_plugin_generator('block_le_dashboard');
    }

    /**
     * My assessments' steps, with its fixtures loaded.
     *
     * @return steps
     */
    public static function steps(): steps {
        testing_util::get_data_generator()->get_plugin_generator('block_le_dashboard');
        return steps::instance();
    }

    /**
     * The hub fixtures of hub-view.feature: categories, courses, users, enrolments, roles, the two blocks and the
     * academic year, as its Background lists them.
     */
    public function hub_fixtures(): void {
        global $DB;
        $gen = testing_util::get_data_generator();
        $cats = [];
        $categories = [
            ['Faculty of Science', 0, 'SCI'], ['Biosciences', 'SCI', 'BIO'], ['Physics', 'SCI', 'PHY'],
            ['Faculty of Arts', 0, 'ART'], ['History', 'ART', 'HIS'],
        ];
        foreach ($categories as [$name, $parent, $idnumber]) {
            $cats[$idnumber] = $gen->create_category(['name' => $name, 'idnumber' => $idnumber,
                'parent' => $parent ? $cats[$parent]->id : 0]);
        }
        $start = strtotime('2026-09-28');
        $courses = [
            ['Cell Biology', 'BIO1', 'BIO'], ['Quantum Physics', 'PHY1', 'PHY'], ['Medieval Europe', 'HIS1', 'HIS'],
            ['Calculus', 'MTH1001', 'BIO'], ['Linear Algebra', 'MTH2003', 'BIO'], ['Topology', 'MTH3005', 'BIO'],
        ];
        foreach ($courses as [$full, $short, $cat]) {
            $gen->create_course([
                'fullname' => $full,
                'shortname' => $short,
                'category' => $cats[$cat]->id,
                'startdate' => $start,
            ]);
        }
        $users = [
            ['student1', 'Sam', 'Student'], ['student2', 'Sue', 'Student'], ['marker1', 'Mo', 'Marker'],
            ['marker2', 'Max', 'Marker'], ['convenor', 'Iris', 'Novak'], ['hub1', 'Hana', 'Hub'], ['hubhist', 'Hugo', 'Hub'],
        ];
        foreach ($users as [$u, $f, $l]) {
            $gen->create_user(['username' => $u, 'password' => $u, 'firstname' => $f, 'lastname' => $l,
                'email' => "$u@example.com"]);
        }
        if (!$DB->record_exists('role', ['shortname' => 'moduleconvenor'])) {
            $roleid = create_role('Module convenor', 'moduleconvenor', '', 'editingteacher');
            set_role_contextlevels($roleid, [CONTEXT_COURSE]);
            role_cap_duplicate($DB->get_record('role', ['shortname' => 'editingteacher']), $roleid);
        }
        $enrolments = [
            ['student1', 'BIO1', 'student'], ['student2', 'BIO1', 'student'], ['student1', 'PHY1', 'student'],
            ['student1', 'HIS1', 'student'], ['student1', 'MTH2003', 'student'], ['student2', 'MTH2003', 'student'],
            ['student1', 'MTH1001', 'student'], ['student1', 'MTH3005', 'student'],
            ['marker1', 'BIO1', 'editingteacher'], ['marker2', 'BIO1', 'editingteacher'],
            ['marker1', 'PHY1', 'editingteacher'], ['marker1', 'HIS1', 'editingteacher'],
            ['marker1', 'MTH2003', 'editingteacher'], ['marker1', 'MTH1001', 'editingteacher'],
            ['marker1', 'MTH3005', 'editingteacher'],
            ['convenor', 'BIO1', 'moduleconvenor'], ['convenor', 'MTH2003', 'moduleconvenor'],
        ];
        foreach ($enrolments as [$u, $c, $r]) {
            $gen->enrol_user(steps::userid($u), steps::course($c)->id, $r);
        }
        self::steps()->the_role_exists('Education support', 'manager');
        $this->role_has_capability('Education support', access::CAPABILITY);
        $this->has_role_at_system('hub1', 'Education support');
        $this->has_role_in_category('hubhist', 'Education support', 'History');
        self::steps()->the_block_is_configured('summative', 'formative');
        self::steps()->treats_as_role('Module convenor', 'module convenor');
        self::steps()->the_academic_year_starts_on('2026/27', '2026-09-01');
        $this->block_on_dashboard();
    }

    /**
     * A role gains a capability.
     *
     * @param string $role Name or short name.
     * @param string $capability
     */
    public function role_has_capability(string $role, string $capability): void {
        assign_capability($capability, CAP_ALLOW, self::roleid($role), context_system::instance()->id, true);
        accesslib_clear_role_cache(self::roleid($role));
    }

    /**
     * A role id by name or short name.
     *
     * @param string $role
     * @return int
     */
    public static function roleid(string $role): int {
        global $DB;
        $id = $DB->get_field('role', 'id', ['shortname' => $role]) ?: $DB->get_field('role', 'id', ['name' => $role]);
        if (!$id) {
            throw new coding_exception("No role '$role'");
        }
        return (int)$id;
    }

    /**
     * A user holds a role at system level.
     *
     * @param string $username
     * @param string $role
     */
    public function has_role_at_system(string $username, string $role): void {
        role_assign(self::roleid($role), steps::userid($username), context_system::instance()->id);
    }

    /**
     * A user holds a role in a category.
     *
     * @param string $username
     * @param string $role
     * @param string $category The category's name.
     */
    public function has_role_in_category(string $username, string $role, string $category): void {
        role_assign(self::roleid($role), steps::userid($username), context_coursecat::instance(self::category($category))->id);
    }

    /**
     * A category id by name, or by the last name of a "Faculty / Department" path.
     *
     * @param string $path
     * @return int
     */
    public static function category(string $path): int {
        global $DB;
        $parts = array_map('trim', explode('/', $path));
        $name = end($parts);
        return (int)$DB->get_field('course_categories', 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * The Hub dashboard block is on the default Dashboard.
     */
    public function block_on_dashboard(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/my/lib.php');
        $page = my_get_page(null, MY_PAGE_PRIVATE);
        if (!$DB->record_exists('block_instances', ['blockname' => 'le_hub_dashboard', 'subpagepattern' => $page->id])) {
            $DB->insert_record('block_instances', ['blockname' => 'le_hub_dashboard',
                'parentcontextid' => context_system::instance()->id, 'showinsubcontexts' => 0, 'pagetypepattern' => 'my-index',
                'subpagepattern' => $page->id, 'defaultregion' => 'content', 'defaultweight' => -11, 'configdata' => '',
                'timecreated' => time(), 'timemodified' => time()]);
        }
        my_reset_page_for_all_users(MY_PAGE_PRIVATE, 'my-index');
    }

    /**
     * A saved view with departments and code rules, opened as the user's current one.
     *
     * @param string $username
     * @param string $name
     * @param string[] $categories Category names or paths.
     * @param string[] $starts
     * @param string[] $contains
     * @return int The view id.
     */
    public function create_view(
        string $username,
        string $name,
        array $categories = [],
        array $starts = [],
        array $contains = []
    ): int {
        $userid = steps::userid($username);
        $id = views::create($userid, $name);
        views::set_shape($id, $userid, ['cat' => array_map([self::class, 'category'], $categories), 'starts' => $starts,
            'contains' => $contains, 'course' => []]);
        config::set_preference('view', $id, $userid);
        return $id;
    }

    /**
     * "picked the hub categories X": a view named after the categories, with them as its departments.
     *
     * @param string $username
     * @param string $paths Comma-separated names or "Faculty / Department" paths.
     * @return int
     */
    public function picked_categories(string $username, string $paths): int {
        global $DB;
        $userid = steps::userid($username);
        $name = 'Science team';
        if ($view = $DB->get_record(views::TABLE, ['userid' => $userid, 'name' => $name])) {
            views::set_shape((int)$view->id, $userid, ['cat' => array_map([self::class, 'category'], explode(',', $paths))]);
            config::set_preference('view', $view->id, $userid);
            return (int)$view->id;
        }
        return $this->create_view($username, $name, array_map('trim', explode(',', $paths)));
    }

    /**
     * A view by name for a user.
     *
     * @param string $username
     * @param string $name
     * @return stdClass
     */
    public static function view(string $username, string $name): stdClass {
        global $DB;
        return $DB->get_record(views::TABLE, ['userid' => steps::userid($username), 'name' => $name], '*', MUST_EXIST);
    }

    /**
     * Several views exist and one was last used.
     *
     * @param string $username
     * @param string[] $names
     * @param string $last
     */
    public function has_views(string $username, array $names, string $last): void {
        $lastid = 0;
        foreach ($names as $name) {
            $id = $this->create_view($username, $name);
            if ($name === $last) {
                $lastid = $id;
            }
        }
        if ($lastid) {
            config::set_preference('view', $lastid, steps::userid($username));
        }
    }

    /**
     * The user last chose a type.
     *
     * @param string $username
     * @param string $type Summative, Formative or Both.
     */
    public function last_chose_type(string $username, string $type): void {
        config::set_preference('type', strtolower($type), steps::userid($username));
    }

    /**
     * A hub note.
     *
     * @param string $username
     * @param string $text
     * @param string $name The assessment.
     * @param bool $escalated
     */
    public function add_note(string $username, string $text, string $name, bool $escalated = false): void {
        notes::add((int)self::steps()->cm($name)->id, steps::userid($username), $text, $escalated);
    }

    /**
     * An assessment ignored in a view.
     *
     * @param string $username
     * @param string $name
     * @param string $view
     */
    public function ignored_assessment(string $username, string $name, string $view): void {
        $v = self::view($username, $view);
        views::add_exception((int)$v->id, (int)$v->userid, 'outcm', (int)self::steps()->cm($name)->id);
    }

    /**
     * An assessment is created with a marking deadline: My assessments' sentence with the option spelt as its
     * fixtures take it.
     *
     * @param string $tag
     * @param string $modname
     * @param string $name
     * @param string $course
     * @param string $opts
     * @param string $due
     * @param string $markingdue
     */
    public function assessment_with_marking_due(
        string $tag,
        string $modname,
        string $name,
        string $course,
        string $opts,
        string $due,
        string $markingdue
    ): void {
        self::steps()->an_assessment_is_due($tag, $modname, $name, $course, $opts . ' with marking deadline "'
            . $markingdue . '"', $due);
    }

    /**
     * Several assessments, for scrolling scenarios.
     *
     * @param int $count
     * @param string $course
     * @param string $due
     * @param string $markingdue
     */
    public function many_assessments(int $count, string $course, string $due, string $markingdue): void {
        for ($i = 1; $i <= $count; $i++) {
            $this->assessment_with_marking_due('summative', 'assign', "Assessment $i", $course, '', $due, $markingdue);
            foreach (self::STUDENTS as $student) {
                self::steps()->have_submitted($student, '', "Assessment $i");
            }
        }
    }

    /**
     * A student's work is marked by marker1.
     *
     * @param string $student
     * @param string $name
     * @param float $grade
     */
    public function marked(string $student, string $name, float $grade = 60): void {
        $cm = self::steps()->cm($name);
        steps::fixture($cm)->mark($cm, steps::userid($student), steps::userid(self::MARKER), $grade);
    }

    /**
     * Both submissions are marked.
     *
     * @param string $name
     */
    public function both_marked(string $name): void {
        foreach (self::STUDENTS as $student) {
            $this->marked($student, $name);
        }
    }

    /**
     * Both submissions are marked and released.
     *
     * @param string $name
     */
    public function both_released(string $name): void {
        $cm = self::steps()->cm($name);
        foreach (self::STUDENTS as $student) {
            $this->marked($student, $name);
            steps::fixture($cm)->release($cm, steps::userid($student));
        }
    }

    /**
     * Students are allocated to a marker.
     *
     * @param string[] $students
     * @param string $name
     * @param string $marker
     */
    public function allocated(array $students, string $name, string $marker): void {
        $cm = self::steps()->cm($name);
        foreach ($students as $student) {
            steps::fixture($cm)->allocate($cm, steps::userid($student), [steps::userid($marker)]);
        }
    }

    /**
     * The name of the newest assessment, for sentences that name none ("both marked, sent to hub, then the mark of
     * "student1" is changed").
     *
     * @return string
     */
    public static function latest_assessment(): string {
        global $DB;
        $latest = null;
        foreach (steps::MODULES as $modname) {
            if (!core_component::get_component_directory('mod_' . $modname)) {
                continue;
            }
            $record = $DB->get_record_sql("SELECT cm.id, m.name FROM {course_modules} cm JOIN {modules} md ON md.id = cm.module
                                             JOIN {{$modname}} m ON m.id = cm.instance WHERE md.name = :modname
                                         ORDER BY cm.id DESC", ['modname' => $modname], IGNORE_MULTIPLE);
            if ($record && ($latest === null || $record->id > $latest->id)) {
                $latest = $record;
            }
        }
        if (!$latest) {
            throw new coding_exception('No assessment exists');
        }
        return $latest->name;
    }

    /**
     * The name of the newest assignment that allocates markers, for sentences that name none.
     *
     * @return string
     */
    public static function latest_allocating_assessment(): string {
        global $DB;
        $name = $DB->get_field_sql(
            "SELECT a.name FROM {assign} a WHERE a.markingallocation = 1 ORDER BY a.id DESC",
            null,
            IGNORE_MULTIPLE
        );
        if (!$name) {
            throw new coding_exception('No assignment allocates markers');
        }
        return $name;
    }

    /**
     * Marks were sent to the hub (HB2): an assignment's marking workflow goes to "ready for release" for the
     * students named, or for everyone with a mark. Coursework has no identified send step yet.
     *
     * @param string $name
     * @param string[]|null $students Null for everyone marked.
     */
    public function sent_to_hub(string $name, ?array $students = null): void {
        global $DB;
        $cm = self::steps()->cm($name);
        if ($cm->modname !== 'assign') {
            throw new unavailable("No send step is known for $cm->modname (HB2)");
        }
        $assign = new assign(context_module::instance($cm->id), $cm, $cm->get_course());
        if (!$assign->get_instance()->markingworkflow) {
            $DB->set_field('assign', 'markingworkflow', 1, ['id' => $cm->instance]);
            $assign = new assign(context_module::instance($cm->id), $cm, $cm->get_course());
        }
        // Everyone who handed in, marked or not: the state can be set before marking, which is what the hub chases.
        if ($students === null) {
            $userids = $DB->get_fieldset_select(
                'assign_submission',
                'userid',
                'assignment = :a AND status = :s AND latest = 1',
                ['a' => $cm->instance, 's' => 'submitted']
            );
        } else {
            $userids = array_map([steps::class, 'userid'], $students);
        }
        foreach ($userids as $userid) {
            $flags = $assign->get_user_flags((int)$userid, true);
            $flags->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_READYFORRELEASE;
            $assign->update_user_flags($flags);
        }
        steps::queue($cm);
    }

    /**
     * A mark is changed after it was given.
     *
     * @param string $student
     * @param string $name
     */
    public function mark_changed(string $student, string $name): void {
        $this->marked($student, $name, 70);
    }

    /**
     * A student is exempt (My assessments' exempt mitigation).
     *
     * @param string $student
     * @param string $name
     */
    public function exempt(string $student, string $name): void {
        self::steps()->has_a_mitigation_on($student, 'exempt', $name);
    }

    /**
     * A student's results are withheld after an academic conduct check (CS3 status 3).
     *
     * @param string $student
     * @param string $name
     */
    public function withheld(string $student, string $name): void {
        self::steps()->the_submission_is_flagged($student, $name, '3');
    }
}
