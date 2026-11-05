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

namespace block_le_hub_dashboard\rules;

use block_le_hub_dashboard\hub_testcase;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../hub_testcase.php');

/**
 * Two-line rows: the collapsed line of aligned columns, and the detail that opens under it (P5, P6).
 *
 * From tests/behat/hub-rows.feature: each test is one of its scenarios, run by hub_testcase one sentence at a time;
 * the sentences that read the page are checked against the rendered Hub dashboard.
 *
 * @package    block_le_hub_dashboard
 * @category   test
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_le_hub_dashboard\local\rules
 * @covers     \block_le_hub_dashboard\local\query
 * @covers     \block_le_hub_dashboard\local\row
 * @covers     \block_le_hub_dashboard\local\dashboard
 * @covers     \block_le_hub_dashboard\local\views
 * @covers     \block_le_hub_dashboard\local\people
 * @covers     \block_le_hub_dashboard\local\notes
 * @covers     \block_le_hub_dashboard\local\config
 * @covers     \block_le_hub_dashboard\local\access
 */
final class rows_test extends hub_testcase {
    /**
     * The Background of the hub scopes.
     */
    protected function background(): void {
        $this->step('Given the hub fixtures from hub-view.feature exist');
        $this->step('And "hub1" picked the hub categories "Faculty of Science"');
        $this->step('And a summative "assign" "Problem sheet 4" in "MTH2003" was due "20 days ago" with marking due "2 days ago"');
        $this->step('And a summative "assign" "Problem sheet 5" in "MTH2003" is due "in 6 days"');
        $this->step('And "student1" and "student2" have submitted "Problem sheet 4"');
        $this->step('And "student1" has been marked on "Problem sheet 4"');
    }

    /**
     * The controls fit one bar.
     */
    public function test_the_controls_fit_one_bar(): void {
        $this->background();
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the "Hub dashboard" controls should be "View, Year, Type, Find, Filter, Refresh, Export"');
        $this->step('And I should not see "Words" in the "Hub dashboard" block');
        $this->step('And I should not see "Only with" in the "Hub dashboard" block');
    }

    /**
     * Choosing a person shows a scope banner because the list now covers the institution.
     */
    public function test_choosing_a_person_shows_a_scope_banner(): void {
        $this->background();
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('And I search the hub dashboard for the person "Mo Marker"');
        $this->step('Then I should see "Showing Mo Marker\'s assessments across the whole institution" in the "Hub ' .
            'dashboard" block');
        $this->step('And the collapsed row "Problem sheet 4" should show the status "Mo Marker: allocated 1, marked 1, 0 to do"');
        $this->step('When I click on "Clear" "button" in the "Hub dashboard" "block"');
        $this->step('Then I should not see "across the whole institution"');
    }

    /**
     * The Only with list sits behind one Filter button, grouped.
     */
    public function test_the_filter_list_is_grouped(): void {
        $this->background();
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the Filter list should have the groups "Reason, Work, Allocation, Hub notes, Set-up"');
    }

    /**
     * Each view says how far ahead unallocated work becomes the hub's job.
     */
    public function test_each_view_says_how_far_ahead_unallocated_work_becomes_the_hubs_job(): void {
        $this->background();
        $this->step('Given a summative "assign" "Lab report" in "BIO1" with marker allocation is due "in 20 days"');
        $this->step('And "student1" is allocated to "marker1"');
        $this->step('When I log in as "hub1" and pick the hub categories "Faculty of Science / Biosciences"');
        $this->step('Then "Lab report" should be in the "Ready to go" group with the reason "Allocate"');
        $this->step('When I click on "This view" "button" in the "Hub dashboard" "block"');
        $this->step('And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"');
        $this->step('And I set the view\'s allocation window to "14" days');
        $this->step('Then "Lab report" should be in the "In progress" group with the reason "Upcoming"');
        $this->step('And the collapsed row "Lab report" should show a "1 unallocated" flag');
        $this->step('When I log in as "hubhist" and view the "Hub dashboard"');
        $this->step('And I search the hub dashboard for the person "Sam Student"');
        $this->step('Then "Lab report" should be in the "Ready to go" group with the reason "Allocate"');
    }

    /**
     * An assessment with more than one reason shows all of them and sits in the earlier group.
     */
    public function test_an_assessment_with_more_than_one_reason_shows_all_of_them(): void {
        $this->background();
        $this->step('Given a summative "assign" "Lab report" in "BIO1" with marking workflow was due "25 days ago" with ' .
            'marking due "2 days ago"');
        $this->step('And "student1" and "student2" have submitted "Lab report"');
        $this->step('And "student1" has been marked on "Lab report"');
        $this->step('And marks for "Lab report" were sent to hub');
        $this->step('When I log in as "hub1" and pick the hub categories "Faculty of Science / Biosciences"');
        $this->step('Then "Lab report" should be in the "Chase" group with the reason "Marking late"');
        $this->step('And "Lab report" should show the reason tag "Sent before marked"');
        $this->step('And the collapsed row "Lab report" should show a "1 sent before marking finished" flag');
        $this->step('When I click on the "1 sent before marking finished" flag of "Lab report"');
        $this->step('Then I should see "1 sent to hub before marking finished: don\'t release"');
    }

    /**
     * Chase rows run late first, then by days left.
     */
    public function test_chase_rows_run_late_first_then_by_days_left(): void {
        $this->background();
        $this->step('Given a summative "assign" "Risky one" in "BIO1" was due "15 days ago" with marking due "in 3 days"');
        $this->step('And a summative "assign" "Riskier one" in "BIO1" was due "15 days ago" with marking due "in 1 day"');
        $this->step('And "student1" and "student2" have submitted "Risky one"');
        $this->step('And "student1" and "student2" have submitted "Riskier one"');
        $this->step('And "student1" has been marked on "Risky one"');
        $this->step('And "student1" has been marked on "Riskier one"');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the "Chase" group should list "Problem sheet 4, Riskier one, Risky one" in that order');
    }

    /**
     * Done is collapsed by default.
     */
    public function test_done_is_collapsed_by_default(): void {
        $this->background();
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the "Done" group should be collapsed');
    }

    /**
     * A collapsed row is one line of aligned columns under one column header.
     */
    public function test_a_collapsed_row_is_one_line_of_aligned_columns(): void {
        $this->background();
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the row columns should be "Assessment, Module, Reason, Status, Progress, Flags"');
        $this->step('And the collapsed row "Problem sheet 4" should show "Problem sheet 4", "MTH2003"');
        $this->step('And the collapsed row "Problem sheet 4" should show the reason tag "Marking late"');
        $this->step('And the collapsed row "Problem sheet 4" should show the status "1 unmarked, 2 days past the marking ' .
            'deadline"');
        $this->step('And the collapsed row "Problem sheet 4" should show one stacked bar with segments "Submitted 2, ' .
            'Marked 1, Released 1"');
        $this->step('And the collapsed row "Problem sheet 4" should not show "Linear Algebra"');
        $this->step('And the collapsed row "Problem sheet 4" should not show "Iris Novak"');
        $this->step('And the collapsed row "Problem sheet 4" should not show "Online submission"');
        $this->step('And the collapsed row "Problem sheet 4" should not show "Show people"');
        $this->step('And the collapsed row "Problem sheet 5" should show the status "0 of 2 submitted, due in 6 days"');
    }

    /**
     * Collapsed-row flags are icons with counts, and the words are in the popover.
     */
    public function test_collapsed_row_flags_are_icons_with_counts(): void {
        $this->background();
        $this->step('Given "student1" has an extension on "Problem sheet 4" to "in 3 days"');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the collapsed row "Problem sheet 4" should show the icon flags "1 individual deadline, No allocation"');
        $this->step('And the collapsed row "Problem sheet 4" should not show the text "individual deadline"');
    }

    /**
     * An academic conduct flag is a coursework's, and its words are in the popover too.
     */
    public function test_an_academic_conduct_flag_is_a_courseworks(): void {
        $this->background();
        $this->step('Given a summative "coursework" "Lab report" in "BIO1" was due "10 days ago" with marking due "in 10 days"');
        $this->step('And "student1" has submitted "Lab report"');
        $this->step('And the submission of "student1" for "Lab report" is flagged for an academic conduct check');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the collapsed row "Lab report" should show the icon flags "1 flagged, No allocation"');
        $this->step('And the collapsed row "Lab report" should not show the text "flagged"');
    }

    /**
     * Set-up is in the expanded row only, except the allocation state which stays as an icon.
     *
     * @return array[]
     */
    public static function setup_cases(): array {
        return [
            ['a summative "assign" "Lab report" in "BIO1" was due "10 days ago"', 'No allocation',
                'Online submission, Single marking, No allocation'],
            ['a summative "assign" "Lab report" in "BIO1" with marker allocation was due "10 days ago"', 'Allocated',
                'Online submission, Single marking, Allocated',
                '"student1" and "student2" are allocated to "marker1" on "Lab report"'],
            ['a summative "assign" "Lab report" in "BIO1" with 2 markers was due "10 days ago"', 'No allocation',
                'Online submission, Double marking, No allocation'],
            ['a summative "coursework" "Lab report" in "BIO1" with marks entry only was due "10 days ago"', 'No allocation',
                'Attendance, Single marking, No allocation'],
            ['a summative "assign" "Lab report" in "BIO1" with anonymous marking was due "10 days ago"', 'No allocation',
                'Online submission, Single marking, Anonymous, No allocation'],
        ];
    }

    /**
     * Set-up is in the expanded row only, except the allocation state which stays as an icon.
     *
     * @dataProvider setup_cases
     * @param string $setup
     * @param string $collapsed
     * @param string $expanded
     * @param string $also
     */
    public function test_setup_is_in_the_expanded_row_only(
        string $setup,
        string $collapsed,
        string $expanded,
        string $also = ''
    ): void {
        $this->background();
        $this->step("Given $setup");
        if ($also !== '') {
            $this->step("And $also");
        }
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step("Then the collapsed row \"Lab report\" should show the icon flags \"$collapsed\"");
        $this->step('When I expand the row "Lab report"');
        $this->step("Then the expanded row \"Lab report\" should show the set-up \"$expanded\"");
    }

    /**
     * The expanded row holds the tracker dates, individual bars, people, notes and the Ignore menu.
     */
    public function test_the_expanded_row_holds_the_dates_bars_people_notes_and_ignore(): void {
        $this->background();
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('And I expand the row "Problem sheet 4"');
        $this->step('Then the expanded row "Problem sheet 4" should show the dates "Submission deadline, Marking ' .
            'deadline, Marks due to students" in that order');
        $this->step('And the expanded row "Problem sheet 4" should show the bars "Submitted, Marked, Released"');
        $this->step('And the expanded row "Problem sheet 4" should list the convenor "Iris Novak" linked to their profile');
        $this->step('And the expanded row "Problem sheet 4" should not show "convenor@example.com"');
        $this->step('And the expanded row "Problem sheet 4" should offer "+ Note"');
        $this->step('And the expanded row "Problem sheet 4" should not show "Open in Moodle"');
        $this->step('And the expanded row "Problem sheet 4" should offer "Ignore in Science team"');
    }

    /**
     * Hub notes live in the expanded row and the collapsed row carries only their flags.
     */
    public function test_hub_notes_live_in_the_expanded_row(): void {
        $this->background();
        $this->step('Given "hub1" added the hub note "Mo off sick" to "Problem sheet 4" as an escalation');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the collapsed row "Problem sheet 4" should show a "Escalated" flag');
        $this->step('And the collapsed row "Problem sheet 4" should show a "1 hub note" flag');
        $this->step('And the collapsed row "Problem sheet 4" should not show "Mo off sick"');
        $this->step('When I expand the row "Problem sheet 4"');
        $this->step('Then the latest hub note on "Problem sheet 4" should be "Mo off sick" by "Hana Hub" today');
    }

    /**
     * The row's name links to the activity and its module code to the course.
     */
    public function test_the_name_links_to_the_activity_and_the_code_to_the_course(): void {
        $this->background();
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the collapsed row "Problem sheet 4" should link "Problem sheet 4" to the activity page');
        $this->step('And the collapsed row "Problem sheet 4" should link "MTH2003" to the course page');
    }

    /**
     * Ignored assessments are listed in the view panel and can be put back.
     */
    public function test_ignored_assessments_are_listed_in_the_view_panel_and_can_be_put_back(): void {
        $this->background();
        $this->step('Given "hub1" ignored the assessment "Problem sheet 4" in the hub view "Science team"');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then I should not see "Problem sheet 4" in the "Hub dashboard" block');
        $this->step('When I click on "This view" "button" in the "Hub dashboard" "block"');
        $this->step('And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"');
        $this->step('Then I should see "Problem sheet 4" in the view editor under "Exceptions"');
        $this->step('When I click on "Put back" "link" in the view editor');
        $this->step('Then I should see "Problem sheet 4" in the "Hub dashboard" block');
    }

    /**
     * The view menu and new view button are labelled.
     */
    public function test_the_view_menu_and_new_view_button_are_labelled(): void {
        $this->background();
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then I should see "This view" in the "Hub dashboard" block');
        $this->step('And I should see "New view" in the "Hub dashboard" block');
        $this->step('And I should not see "⋯" in the "Hub dashboard" block');
    }

    /**
     * Summative work is shown by default, and the type choice is remembered.
     */
    public function test_summative_by_default_and_the_type_choice_is_remembered(): void {
        $this->background();
        $this->step('Given a formative "assign" "Practice quiz" in "MTH2003" was due "3 days ago"');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then I should see "Problem sheet 4" in the "Hub dashboard" block');
        $this->step('And I should not see "Practice quiz" in the "Hub dashboard" block');
        $this->step('When I click on "Both" "button" in the "Hub dashboard" "block"');
        $this->step('Then I should see "Practice quiz" in the "Hub dashboard" block');
        $this->step('Given "hub1" last chose "Formative" in the Hub dashboard');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then I should see "Practice quiz" in the "Hub dashboard" block');
        $this->step('And I should not see "Problem sheet 5" in the "Hub dashboard" block');
    }

    /**
     * The last view used opens next time.
     */
    public function test_the_last_view_used_opens_next_time(): void {
        $this->step('Given the hub fixtures from hub-view.feature exist');
        $this->step('And "hub1" has the hub views "Science team" and "Arts team", and last used "Arts team"');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the hub view "Arts team" should be open');
    }

    /**
     * Searching for a student shows only what they take, with where they stand.
     */
    public function test_searching_for_a_student_shows_only_what_they_take(): void {
        $this->step('Given the hub fixtures from hub-view.feature exist');
        $this->step('And a summative "assign" "Lab report" in "BIO1" was due "10 days ago"');
        $this->step('And a summative "assign" "Problem set" in "PHY1" was due "10 days ago"');
        $this->step('And "student2" has submitted "Lab report"');
        $this->step('And "hub1" picked the hub categories "Faculty of Science"');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('And I search the hub dashboard for the person "Sue Student"');
        $this->step('Then I should see "Lab report" in the "Hub dashboard" block');
        $this->step('And I should not see "Problem set" in the "Hub dashboard" block');
        $this->step('And the collapsed row "Lab report" should show the status "Submitted"');
    }

    /**
     * A view can be limited to modules whose code starts with given text, and a module added by code.
     */
    public function test_a_view_can_be_limited_by_module_code(): void {
        $this->step('Given the hub fixtures from hub-view.feature exist');
        $this->step('And a summative "assign" "Sheet 1" in "MTH1001" was due "3 days ago"');
        $this->step('And a summative "assign" "Sheet 2" in "MTH2003" was due "3 days ago"');
        $this->step('And a summative "assign" "Sheet 3" in "MTH3005" was due "3 days ago"');
        $this->step('And a summative "assign" "Essay" in "HIS1" was due "3 days ago"');
        $this->step('And "hub1" has the hub view "My modules" with module codes starting with "MTH2" or "MTH3"');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then I should see "Sheet 2" in the "Hub dashboard" block');
        $this->step('And I should see "Sheet 3" in the "Hub dashboard" block');
        $this->step('And I should not see "Sheet 1" in the "Hub dashboard" block');
        $this->step('And I should not see "Essay" in the "Hub dashboard" block');
        $this->step('And the view editor should say "2 of 6 modules"');
        $this->step('Given "hub1" added the module "HIS1" to the hub view "My modules"');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then I should see "Essay" in the "Hub dashboard" block');
    }

    /**
     * Every assessment says whether markers are allocated, before the deadline too.
     *
     * @return array[]
     */
    public static function allocation_cases(): array {
        return [
            [' with marker allocation', '"student1" and "student2" are allocated to "marker1"', 'Allocated'],
            [' with marker allocation', '"student1" is allocated to "marker1"', '1 unallocated'],
            ['', 'nothing happens', 'No allocation'],
        ];
    }

    /**
     * Every assessment says whether markers are allocated, before the deadline too.
     *
     * @dataProvider allocation_cases
     * @param string $allocation
     * @param string $allocated
     * @param string $flag
     */
    public function test_every_assessment_says_whether_markers_are_allocated(
        string $allocation,
        string $allocated,
        string $flag
    ): void {
        $this->step('Given the hub fixtures from hub-view.feature exist');
        $this->step("And a summative \"assign\" \"Lab report\" in \"BIO1\"$allocation is due \"in 5 days\"");
        $this->step("And $allocated");
        $this->step('And "hub1" picked the hub categories "Faculty of Science / Biosciences"');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step("Then \"Lab report\" should show a \"$flag\" flag");
    }
}
