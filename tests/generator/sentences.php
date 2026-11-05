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

/**
 * The hub scopes' sentences that write to the site, with the arguments Behat passes: the Behat context and the
 * PHPUnit scenario tests both run these, so the vocabulary has one owner. Each is named as the context's method.
 *
 * @package    block_le_hub_dashboard
 * @category   test
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_le_hub_dashboard_sentences {
    /**
     * The hub generator.
     *
     * @return block_le_hub_dashboard_generator
     */
    public static function gen(): block_le_hub_dashboard_generator {
        return testing_util::get_data_generator()->get_plugin_generator('block_le_hub_dashboard');
    }

    /**
     * The hub fixtures from hub-view.feature exist.
     */
    public static function hub_fixtures_exist(): void {
        self::gen()->hub_fixtures();
    }

    /**
     * A role has a capability.
     *
     * @param string $role
     * @param string $capability
     */
    public static function role_has_capability(string $role, string $capability): void {
        self::gen()->role_has_capability($role, $capability);
    }

    /**
     * A user has a role at system level.
     *
     * @param string $username
     * @param string $role
     */
    public static function has_role_at_system(string $username, string $role): void {
        self::gen()->has_role_at_system($username, $role);
    }

    /**
     * A user has a role in a category.
     *
     * @param string $username
     * @param string $role
     * @param string $category
     */
    public static function has_role_in_category(string $username, string $role, string $category): void {
        self::gen()->has_role_in_category($username, $role, $category);
    }

    /**
     * The Hub dashboard block is on the default Dashboard.
     */
    public static function block_on_dashboard(): void {
        self::gen()->block_on_dashboard();
    }

    /**
     * A user picked hub categories (a saved view with those departments).
     *
     * @param string $username
     * @param string $paths
     */
    public static function picked_categories(string $username, string $paths): void {
        self::gen()->picked_categories($username, $paths);
    }

    /**
     * A user has views and last used one.
     *
     * @param string $username
     * @param string $first
     * @param string $second
     * @param string $last
     */
    public static function has_views(string $username, string $first, string $second, string $last): void {
        self::gen()->has_views($username, [$first, $second], $last);
    }

    /**
     * A user last chose a type.
     *
     * @param string $username
     * @param string $type
     */
    public static function last_chose_type(string $username, string $type): void {
        self::gen()->last_chose_type($username, $type);
    }

    /**
     * A user has a view shaped by module codes, opened as their current one.
     *
     * @param string $username
     * @param string $name
     * @param string $first Codes start with this ...
     * @param string $second ... or this.
     */
    public static function has_code_view(string $username, string $name, string $first, string $second): void {
        self::gen()->create_view($username, $name, [], [$first, $second]);
    }

    /**
     * A user added a module by its code to one of their views (P5: a module outside the shape).
     *
     * @param string $username
     * @param string $code
     * @param string $name The view.
     */
    public static function added_module(string $username, string $code, string $name): void {
        global $DB;
        $userid = steps::userid($username);
        $view = block_le_hub_dashboard_generator::view($username, $name);
        $rules = [];
        foreach ($DB->get_records_select(views::RULES, 'viewid = :viewid', ['viewid' => $view->id]) as $rule) {
            $rules[$rule->kind][] = $rule->value;
        }
        $rules['course'][] = $code;
        views::set_shape((int)$view->id, $userid, $rules);
    }

    /**
     * A user added a hub note.
     *
     * @param string $username
     * @param string $text
     * @param string $name
     * @param string $escalation " as an escalation" or ''.
     */
    public static function added_note(string $username, string $text, string $name, string $escalation = ''): void {
        self::gen()->add_note($username, $text, $name, $escalation !== '');
    }

    /**
     * A user ignored an assessment in a view.
     *
     * @param string $username
     * @param string $name
     * @param string $view
     */
    public static function ignored_assessment(string $username, string $name, string $view): void {
        self::gen()->ignored_assessment($username, $name, $view);
    }

    /**
     * An assessment with a marking deadline.
     *
     * @param string $tag
     * @param string $modname
     * @param string $name
     * @param string $course
     * @param string $opts
     * @param string $due
     * @param string $markingdue
     */
    public static function assessment_with_marking_due(
        string $tag,
        string $modname,
        string $name,
        string $course,
        string $opts,
        string $due,
        string $markingdue
    ): void {
        self::gen()->assessment_with_marking_due($tag, $modname, $name, $course, $opts, $due, $markingdue);
    }

    /**
     * Many assessments.
     *
     * @param string $count
     * @param string $course
     * @param string $due
     * @param string $markingdue
     */
    public static function many_assessments(string $count, string $course, string $due, string $markingdue): void {
        self::gen()->many_assessments((int)$count, $course, $due, $markingdue);
    }

    /**
     * A student has been marked.
     *
     * @param string $student
     * @param string $name
     */
    public static function has_been_marked(string $student, string $name): void {
        self::gen()->marked($student, $name);
    }

    /**
     * Both submissions have been marked, or marked and sent to hub.
     *
     * @param string $name
     * @param string $sent " and sent to hub" or ''.
     */
    public static function both_marked(string $name, string $sent = ''): void {
        self::gen()->both_marked($name);
        if ($sent !== '') {
            self::gen()->sent_to_hub($name);
        }
    }

    /**
     * Both submissions have been released.
     *
     * @param string $name
     */
    public static function both_released(string $name): void {
        self::gen()->both_released($name);
    }

    /**
     * One student has been marked and marks were sent to hub.
     *
     * @param string $student
     * @param string $name
     */
    public static function marked_and_sent(string $student, string $name): void {
        self::gen()->marked($student, $name);
        self::gen()->sent_to_hub($name);
    }

    /**
     * Both marked, sent, then one mark changed.
     *
     * @param string $student
     * @param string $name
     */
    public static function both_sent_then_changed(string $student, string $name = ''): void {
        $name = $name !== '' ? $name : block_le_hub_dashboard_generator::latest_assessment();
        self::gen()->both_marked($name);
        self::gen()->sent_to_hub($name);
        self::gen()->mark_changed($student, $name);
    }

    /**
     * Both released, then one mark changed.
     *
     * @param string $student
     * @param string $name
     */
    public static function both_released_then_changed(string $student, string $name = ''): void {
        $name = $name !== '' ? $name : block_le_hub_dashboard_generator::latest_assessment();
        $cm = block_le_hub_dashboard_generator::steps()->cm($name);
        if ($cm->modname === 'assign') {
            // The gradebook's release time is the grade's last change, so for assign a mark changed after
            // release looks like a later release, not a change: nothing to test until the index keeps the
            // first release time (flagged in the delivery notes).
            throw new unavailable("A change after release is not visible for $cm->modname");
        }
        self::gen()->both_released($name);
        self::gen()->mark_changed($student, $name);
    }

    /**
     * Students are allocated to a marker.
     *
     * @param string $student
     * @param string $second
     * @param string $name
     * @param string $marker
     */
    public static function allocated(string $student, string $second, string $name, string $marker): void {
        self::gen()->allocated(array_filter([$student, $second]), $name, $marker);
    }

    /**
     * Marks were sent to hub, for everyone marked or for one student.
     *
     * @param string $name
     * @param string $student
     */
    public static function sent_to_hub(string $name, string $student = ''): void {
        self::gen()->sent_to_hub($name, $student === '' ? null : [$student]);
    }

    /**
     * A mark is changed.
     *
     * @param string $student
     * @param string $name
     */
    public static function mark_changed(string $student, string $name): void {
        self::gen()->mark_changed($student, $name);
    }

    /**
     * A student is exempt.
     *
     * @param string $student
     * @param string $name
     */
    public static function exempt(string $student, string $name): void {
        self::gen()->exempt($student, $name);
    }

    /**
     * Results withheld after an academic conduct check.
     *
     * @param string $student
     * @param string $name
     */
    public static function withheld(string $student, string $name): void {
        self::gen()->withheld($student, $name);
    }

    /**
     * Flagged for an academic conduct check: My assessments' plagiarism flag.
     *
     * @param string $student
     * @param string $name
     */
    public static function flagged_for_conduct(string $student, string $name): void {
        block_le_hub_dashboard_generator::steps()->the_submission_is_flagged($student, $name, '0');
    }

    /**
     * Students are allocated to a marker, on the assessment named or the latest one that allocates markers.
     *
     * @param string $student
     * @param string $second
     * @param string $marker
     * @param string $name
     */
    public static function students_allocated(string $student, string $second, string $marker, string $name = ''): void {
        self::gen()->allocated(array_filter([$student, $second]), $name !== '' ? $name
            : block_le_hub_dashboard_generator::latest_allocating_assessment(), $marker);
    }
}
