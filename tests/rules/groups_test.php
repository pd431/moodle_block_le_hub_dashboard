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
 * Four groups by whose turn it is, with the reason as a tag (P9, P10).
 *
 * From tests/behat/hub-groups.feature: each test is one of its scenarios, run by hub_testcase one sentence at a time;
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
 * @covers     \block_le_hub_dashboard\output\dashboard
 */
final class groups_test extends hub_testcase {
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
     * Each assessment goes in the group that says whose turn it is, with the reason as a tag.
     *
     * @return array[]
     */
    public static function group_cases(): array {
        return [
            ['assign', '', 'in 5 days', 'in 25 days', 'nothing happens', 'In progress', 'Upcoming'],
            ['assign', ' with marker allocation', 'in 20 days', 'in 40 days', '"student1" is allocated to "marker1"',
                'Ready to go', 'Allocate'],
            ['assign', ' with marker allocation', 'in 40 days', 'in 60 days', '"student1" is allocated to "marker1"',
                'In progress', 'Upcoming'],
            ['assign', '', '10 days ago', 'in 10 days', 'nothing happens', 'In progress', 'Marking on track'],
            ['assign', '', '15 days ago', 'in 3 days', '"student1" has been marked on "Lab report"', 'Chase', 'Marking at risk'],
            ['assign', '', '25 days ago', '2 days ago', '"student1" has been marked on "Lab report"', 'Chase', 'Marking late'],
            // An assignment without marking workflow releases as it is marked; with it, the workflow's ready-for-release
            // state is the send step (HB2), so fully marked work in review is Not sent to hub until it reaches that state.
            ['assign', ' with marking workflow', '20 days ago', 'in 2 days',
                'both submissions of "Lab report" have been marked', 'Chase', 'Not sent to hub'],
            ['assign', ' with marking workflow', '20 days ago', 'in 2 days',
                'both submissions of "Lab report" have been marked and sent to hub', 'Ready to go', 'Release'],
            ['coursework', '', '20 days ago', 'in 2 days', 'both submissions of "Lab report" have been marked', 'Chase',
                'Not sent to hub'],
            ['coursework', '', '20 days ago', 'in 2 days',
                'both submissions of "Lab report" have been marked and sent to hub', 'Ready to go', 'Release'],
            // Marking due in 10 days, not the scope's 2: inside the risk window "Marking at risk" leads (P9 order).
            ['assign', ' with marking workflow', '20 days ago', 'in 10 days',
                '"student1" has been marked and marks for "Lab report" were sent to hub', 'Chase', 'Sent before marked'],
            ['coursework', '', '20 days ago', 'in 10 days',
                '"student1" has been marked and marks for "Lab report" were sent to hub', 'Chase', 'Sent before marked'],
            ['coursework', '', '20 days ago', 'in 2 days',
                'both marked, sent to hub, then the mark of "student1" is changed', 'Chase', 'Changed since sent'],
            ['assign', '', '25 days ago', '2 days ago', 'both submissions of "Lab report" have been released', 'Done', 'Released'],
            ['assign', '', '25 days ago', '2 days ago', 'both released, then the mark of "student1" is changed', 'Done',
                'Changed since released'],
        ];
    }

    /**
     * Each assessment goes in the group that says whose turn it is, with the reason as a tag.
     *
     * @dataProvider group_cases
     * @param string $type
     * @param string $allocation
     * @param string $due
     * @param string $markingdue
     * @param string $state
     * @param string $group
     * @param string $reason
     */
    public function test_each_assessment_goes_in_the_group_that_says_whose_turn_it_is(string $type, string $allocation, string $due,
            string $markingdue, string $state, string $group, string $reason): void {
        $this->background();
        $this->step("Given a summative \"$type\" \"Lab report\" in \"BIO1\"$allocation was due \"$due\" " .
            "with marking due \"$markingdue\"");
        $this->step('And "student1" and "student2" have submitted "Lab report"');
        $this->step("And $state");
        $this->step('When I log in as "hub1" and pick the hub categories "Faculty of Science / Biosciences"');
        $this->step("Then \"Lab report\" should be in the \"$group\" group with the reason \"$reason\"");
    }

    /**
     * Groups run Ready to go, Chase, In progress, Done, and the header counts each reason.
     */
    public function test_groups_run_in_order_and_the_header_counts_each_reason(): void {
        $this->background();
        $this->step('Given a summative "assign" "Late one" in "BIO1" was due "25 days ago" with marking due "2 days ago"');
        $this->step('And a summative "assign" "Risky one" in "BIO1" was due "15 days ago" with marking due "in 3 days"');
        $this->step('And "student1" and "student2" have submitted "Late one"');
        $this->step('And "student1" and "student2" have submitted "Risky one"');
        $this->step('And "student1" has been marked on "Late one"');
        $this->step('And "student1" has been marked on "Risky one"');
        $this->step('When I log in as "hub1" and view the "Hub dashboard"');
        $this->step('Then the groups should be in the order "Ready to go, Chase, In progress, Done"');
        $this->step('And the "Chase" group header should read "Chase 3 · Marking late 2 · Marking at risk 1"');
        $this->step('And the status strip should read "Ready to go 0, Chase 3, In progress 1, Done 0"');
    }
}
