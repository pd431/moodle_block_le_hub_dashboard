@block @block_le_hub_dashboard
Feature: Assessments are grouped by whose turn it is, with the reason as a tag
  In order to see what needs me today
  As education support (hub) staff
  I need four groups, Ready to go, Chase, In progress and Done, each row tagged with why it is there

  Background:
    Given the hub fixtures from hub-view.feature exist
    And "hub1" picked the hub categories "Faculty of Science"
    And a summative "assign" "Problem sheet 4" in "MTH2003" was due "20 days ago" with marking due "2 days ago"
    And a summative "assign" "Problem sheet 5" in "MTH2003" is due "in 6 days"
    And "student1" and "student2" have submitted "Problem sheet 4"
    And "student1" has been marked on "Problem sheet 4"

  Scenario Outline: Each assessment goes in the group that says whose turn it is, with the reason as a tag
    Given a summative "<type>" "Lab report" in "BIO1"<allocation> was due "<due>" with marking due "<markingdue>"
    And "student1" and "student2" have submitted "Lab report"
    And <state>
    When I log in as "hub1" and pick the hub categories "Faculty of Science / Biosciences"
    Then "Lab report" should be in the "<group>" group with the reason "<reason>"

    Examples:
      | type       | allocation              | due         | markingdue | state                                                                  | group       | reason                 |
      | assign     |                         | in 5 days   | in 25 days | nothing happens                                                        | In progress | Upcoming               |
      | assign     |  with marker allocation | in 20 days  | in 40 days | "student1" is allocated to "marker1"                                   | Ready to go | Allocate               |
      | assign     |  with marker allocation | in 40 days  | in 60 days | "student1" is allocated to "marker1"                                   | In progress | Upcoming               |
      | assign     |                         | 10 days ago | in 10 days | nothing happens                                                        | In progress | Marking on track       |
      | assign     |                         | 15 days ago | in 3 days  | "student1" has been marked on "Lab report"                             | Chase       | Marking at risk        |
      | assign     |                         | 25 days ago | 2 days ago | "student1" has been marked on "Lab report"                             | Chase       | Marking late           |
      | assign     |  with marking workflow  | 20 days ago | in 2 days  | both submissions of "Lab report" have been marked                      | Chase       | Not sent to hub        |
      | assign     |  with marking workflow  | 20 days ago | in 2 days  | both submissions of "Lab report" have been marked and sent to hub      | Ready to go | Release                |
      | assign     |  with marking workflow  | 20 days ago | in 10 days | "student1" has been marked and marks for "Lab report" were sent to hub | Chase       | Sent before marked     |
      | coursework |                         | 20 days ago | in 2 days  | both submissions of "Lab report" have been marked                      | Chase       | Not sent to hub        |
      | coursework |                         | 20 days ago | in 2 days  | both submissions of "Lab report" have been marked and sent to hub      | Ready to go | Release                |
      | coursework |                         | 20 days ago | in 2 days  | both marked, sent to hub, then the mark of "student1" is changed       | Chase       | Changed since sent     |
      | assign     |                         | 25 days ago | 2 days ago | both submissions of "Lab report" have been released                    | Done        | Released               |
      | coursework |                         | 25 days ago | 2 days ago | both released, then the mark of "student1" is changed                  | Done        | Changed since released |

  @javascript
  Scenario: Each view says how far ahead unallocated work becomes the hub's job
    Given a summative "assign" "Lab report" in "BIO1" with marker allocation is due "in 20 days"
    And "student1" is allocated to "marker1"
    When I log in as "hub1" and pick the hub categories "Faculty of Science / Biosciences"
    Then "Lab report" should be in the "Ready to go" group with the reason "Allocate"
    When I click on "This view" "button" in the "Hub dashboard" "block"
    And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"
    And I set the view's allocation window to "14" days
    Then "Lab report" should be in the "In progress" group with the reason "Upcoming"
    And the collapsed row "Lab report" should show a "1 unallocated" flag
    When I log in as "hubhist" and view the "Hub dashboard"
    And I search the hub dashboard for the person "Sam Student"
    Then "Lab report" should be in the "Ready to go" group with the reason "Allocate"

  @javascript
  Scenario: An assessment with more than one reason shows all of them and sits in the earlier group
    Given a summative "assign" "Lab report" in "BIO1" with marking workflow was due "25 days ago" with marking due "2 days ago"
    And "student1" and "student2" have submitted "Lab report"
    And "student1" has been marked on "Lab report"
    And marks for "Lab report" were sent to hub
    When I log in as "hub1" and pick the hub categories "Faculty of Science / Biosciences"
    Then "Lab report" should be in the "Chase" group with the reason "Marking late"
    And "Lab report" should show the reason tag "Sent before marked"
    And the collapsed row "Lab report" should show a "1 sent before marking finished" flag
    When I click on the "1 sent before marking finished" flag of "Lab report"
    Then I should see "1 sent to hub before marking finished: don't release"

  @javascript
  Scenario: Groups run Ready to go, Chase, In progress, Done, and the header counts each reason
    Given a summative "assign" "Late one" in "BIO1" was due "25 days ago" with marking due "2 days ago"
    And a summative "assign" "Risky one" in "BIO1" was due "15 days ago" with marking due "in 3 days"
    And "student1" and "student2" have submitted "Late one"
    And "student1" and "student2" have submitted "Risky one"
    And "student1" has been marked on "Late one"
    And "student1" has been marked on "Risky one"
    When I log in as "hub1" and view the "Hub dashboard"
    Then the groups should be in the order "Ready to go, Chase, In progress, Done"
    And the "Chase" group header should read "Chase 3 · Marking late 2 · Marking at risk 1"
    And the status strip should read "Ready to go 0, Chase 3, In progress 1, Done 0"

  @javascript
  Scenario: Clicking a reason in a group header shows only those rows, and the reason is also a Filter
    Given a summative "assign" "Risky one" in "BIO1" was due "15 days ago" with marking due "in 3 days"
    And "student1" and "student2" have submitted "Risky one"
    And "student1" has been marked on "Risky one"
    When I log in as "hub1" and view the "Hub dashboard"
    And I click on the reason "Marking at risk" in the "Chase" group header
    Then I should see "Risky one" in the "Hub dashboard" block
    And I should not see "Problem sheet 4" in the "Hub dashboard" block
    And I should see the filter chip "Marking at risk"
    When I click on "Filter" "button" in the "Hub dashboard" "block"
    Then "Marking at risk" should be ticked under "Reason" in the Filter list

  @javascript
  Scenario: Chase rows run late first, then by days left
    Given a summative "assign" "Risky one" in "BIO1" was due "15 days ago" with marking due "in 3 days"
    And a summative "assign" "Riskier one" in "BIO1" was due "15 days ago" with marking due "in 1 day"
    And "student1" and "student2" have submitted "Risky one"
    And "student1" and "student2" have submitted "Riskier one"
    And "student1" has been marked on "Risky one"
    And "student1" has been marked on "Riskier one"
    When I log in as "hub1" and view the "Hub dashboard"
    Then the "Chase" group should list "Problem sheet 4, Riskier one, Risky one" in that order

  @javascript
  Scenario: Done is collapsed by default and the status strip jumps to a group
    When I log in as "hub1" and view the "Hub dashboard"
    Then the "Done" group should be collapsed
    When I click on the status pill "In progress"
    Then the "In progress" group should be open and in view

  @javascript
  Scenario: Group headers stay in view while scrolling their rows
    Given 30 summative assessments in "MTH2003" were due "20 days ago" with marking due "2 days ago"
    When I log in as "hub1" and view the "Hub dashboard"
    And I scroll to the 20th row of the "Chase" group
    Then the "Chase" group header should be stuck to the top of the viewport
