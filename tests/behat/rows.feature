@block @block_le_hub_dashboard
Feature: A collapsed row is one line of aligned columns, and opens for the detail
  In order to glance at many assessments without reading set-up I rarely touch
  As education support (hub) staff
  I need each row to show its name, module, reason, status, progress and flags, and the rest when I open it

  Background:
    Given the hub fixtures from hub-view.feature exist
    And "hub1" picked the hub categories "Faculty of Science"
    And a summative "assign" "Problem sheet 4" in "MTH2003" was due "20 days ago" with marking due "2 days ago"
    And a summative "assign" "Problem sheet 5" in "MTH2003" is due "in 6 days"
    And "student1" and "student2" have submitted "Problem sheet 4"
    And "student1" has been marked on "Problem sheet 4"

  @javascript
  Scenario: A collapsed row is one line of aligned columns under one column header
    When I log in as "hub1" and view the "Hub dashboard"
    And the page is shown at its full width
    Then the row columns should be "Assessment, Module, Reason, Status, Progress, Flags"
    And the "Module" column of every collapsed row should start at the same x position
    And the collapsed row "Problem sheet 4" should show "Problem sheet 4", "MTH2003"
    And the collapsed row "Problem sheet 4" should show the reason tag "Marking late"
    And the collapsed row "Problem sheet 4" should show the status "1 unmarked, 2 days past the marking deadline"
    And the collapsed row "Problem sheet 4" should show one stacked bar with segments "Submitted 2, Marked 1, Released 1"
    And the collapsed row "Problem sheet 4" should not show "Linear Algebra"
    And the collapsed row "Problem sheet 4" should not show "Iris Novak"
    And the collapsed row "Problem sheet 4" should not show "Online submission"
    And the collapsed row "Problem sheet 4" should not show "Show people"
    And the collapsed row "Problem sheet 5" should show the status "0 of 2 submitted, due in 6 days"

  @javascript
  Scenario: At the Dashboard's width the status and progress drop under the name and the column header goes
    When I log in as "hub1" and view the "Hub dashboard"
    Then the column header should not be shown
    And the collapsed row "Problem sheet 4" should show "Problem sheet 4", "MTH2003"
    And the collapsed row "Problem sheet 4" should show the status "1 unmarked, 2 days past the marking deadline"
    And the "Module" column of every collapsed row should start at the same x position
    And the collapsed row "Problem sheet 4" should be at most "80" pixels tall

  @javascript
  Scenario: Collapsed-row flags are icons with counts, and the words are in the popover
    Given "student1" has an extension on "Problem sheet 4" to "in 3 days"
    When I log in as "hub1" and view the "Hub dashboard"
    Then the collapsed row "Problem sheet 4" should show the icon flags "1 individual deadline, No allocation"
    And the collapsed row "Problem sheet 4" should not show the text "individual deadline"
    When I click on the "1 individual deadline" flag of "Problem sheet 4"
    Then I should see "Students whose own deadline differs from the default"

  @javascript
  Scenario: An academic conduct flag is a coursework's, and its words are in the popover too
    Given a summative "coursework" "Lab report" in "BIO1" was due "10 days ago" with marking due "in 10 days"
    And "student1" has submitted "Lab report"
    And the submission of "student1" for "Lab report" is flagged for an academic conduct check
    When I log in as "hub1" and view the "Hub dashboard"
    Then the collapsed row "Lab report" should show the icon flags "1 flagged, No allocation"
    And the collapsed row "Lab report" should not show the text "flagged"
    When I click on the "1 flagged" flag of "Lab report"
    Then I should see "1 under investigation for academic conduct"

  @javascript
  Scenario Outline: Set-up is in the expanded row only, except the allocation state which stays as an icon
    Given <setup>
    And <also>
    When I log in as "hub1" and view the "Hub dashboard"
    Then the collapsed row "Lab report" should show the icon flags "<collapsed>"
    When I expand the row "Lab report"
    Then the expanded row "Lab report" should show the set-up "<expanded>"

    Examples:
      | setup                                                                                                            | also                                                               | collapsed     | expanded                                                    |
      | a summative "assign" "Lab report" in "BIO1" was due "10 days ago" with marking due "in 10 days"                  | nothing happens                                                    | No allocation | Online submission, Single marking, No allocation            |
      | a summative "assign" "Lab report" in "BIO1" with marker allocation was due "10 days ago" with marking due "in 10 days" | "student1" and "student2" are allocated to "marker1" on "Lab report" | Allocated     | Online submission, Single marking, Allocated                |
      | a summative "assign" "Lab report" in "BIO1" with 2 markers was due "10 days ago" with marking due "in 10 days"   | nothing happens                                                    | No allocation | Online submission, Double marking, No allocation            |
      | a summative "coursework" "Lab report" in "BIO1" with marks entry only was due "10 days ago" with marking due "in 10 days" | nothing happens                                             | No allocation | Attendance, Single marking, No allocation                   |
      | a summative "assign" "Lab report" in "BIO1" with anonymous marking was due "10 days ago" with marking due "in 10 days" | nothing happens                                                | No allocation | Online submission, Single marking, Anonymous, No allocation |

  Scenario Outline: Every assessment says whether markers are allocated, before the deadline too
    Given a summative "assign" "Lab report" in "BIO1"<allocation> is due "in 5 days"
    And <allocated>
    When I log in as "hub1" and pick the hub categories "Faculty of Science / Biosciences"
    Then "Lab report" should show a "<flag>" flag

    Examples:
      | allocation              | allocated                                                              | flag          |
      |  with marker allocation | "student1" and "student2" are allocated to "marker1" on "Lab report"   | Allocated     |
      |  with marker allocation | "student1" is allocated to "marker1" on "Lab report"                   | 1 unallocated |
      |                         | nothing happens                                                        | No allocation |

  @javascript
  Scenario: The expanded row holds the tracker dates, individual bars, people, notes and the Ignore menu
    When I log in as "hub1" and view the "Hub dashboard"
    And I expand the row "Problem sheet 4"
    Then the expanded row "Problem sheet 4" should show the dates "Submission deadline, Marking deadline, Marks due to students" in that order
    And the expanded row "Problem sheet 4" should show the bars "Submitted, Marked, Released"
    And the expanded row "Problem sheet 4" should list the convenor "Iris Novak" linked to their profile
    And the expanded row "Problem sheet 4" should not show "convenor@example.com"
    And the expanded row "Problem sheet 4" should offer "+ Note"
    And the expanded row "Problem sheet 4" should not show "Open in Moodle"
    And the expanded row "Problem sheet 4" should offer "Ignore in Science team"
    When I collapse the row "Problem sheet 4"
    Then I should not see "Marks due to students" in the "Hub dashboard" block

  @javascript
  Scenario: Rows expand from the keyboard and remember which are open across a refresh
    When I log in as "hub1" and view the "Hub dashboard"
    And I focus the row "Problem sheet 4" and press "Enter"
    Then the expanded row "Problem sheet 4" should show the dates "Submission deadline, Marking deadline, Marks due to students" in that order
    When I click on "Refresh" "button" in the "Hub dashboard" "block"
    Then the expanded row "Problem sheet 4" should show the dates "Submission deadline, Marking deadline, Marks due to students" in that order

  @javascript
  Scenario: Hub notes live in the expanded row and the collapsed row carries only their flags
    Given "hub1" added the hub note "Mo off sick" to "Problem sheet 4" as an escalation
    When I log in as "hub1" and view the "Hub dashboard"
    Then the collapsed row "Problem sheet 4" should show a "Escalated" flag
    And the collapsed row "Problem sheet 4" should show a "1 hub note" flag
    And I should not see "Mo off sick" in the "Hub dashboard" block
    When I expand the row "Problem sheet 4"
    Then the latest hub note on "Problem sheet 4" should be "Mo off sick" by "Hana Hub" today

  @javascript
  Scenario: Hubs add dated notes at the end of an assessment, newest first
    When I log in as "hub1" and view the "Hub dashboard"
    And I add the hub note "Changed second marker from Mo Marker to Max Marker: Mo off sick" to "Problem sheet 4"
    And I add the hub note "Late marks escalated to the Head of Department" to "Problem sheet 4" as an escalation
    Then the latest hub note on "Problem sheet 4" should be "Late marks escalated to the Head of Department" by "Hana Hub" today
    And "Problem sheet 4" should show a "Escalated" flag
    And "Problem sheet 4" should show a "2 hub notes" flag

  @javascript
  Scenario: Hub notes are seen by hub staff only
    Given "hub1" added the hub note "Mo off sick" to "Problem sheet 4"
    When I log in as "convenor" and view the "Convenor view"
    Then I should not see "Mo off sick"
    When I log in as "hubhist" and view the "Hub dashboard"
    And I search the hub dashboard for the person "Mo Marker"
    And I expand the row "Problem sheet 4"
    Then I should see "Mo off sick" in the "Hub dashboard" block

  Scenario: The row's name links to the activity and its module code to the course
    When I log in as "hub1" and view the "Hub dashboard"
    Then the collapsed row "Problem sheet 4" should link "Problem sheet 4" to the activity page
    And the collapsed row "Problem sheet 4" should link "MTH2003" to the course page
