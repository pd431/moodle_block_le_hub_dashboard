@block @block_le_hub_dashboard
Feature: One control bar holds the view, year, type, Find and Filter
  In order to narrow the list without a second row of boxes
  As education support (hub) staff
  I need one bar, a Find box that offers people and words, and a Filter button holding the flags

  Background:
    Given the hub fixtures from hub-view.feature exist
    And "hub1" picked the hub categories "Faculty of Science"
    And a summative "assign" "Problem sheet 4" in "MTH2003" was due "20 days ago" with marking due "2 days ago"
    And a summative "assign" "Problem sheet 5" in "MTH2003" is due "in 6 days"
    And "student1" and "student2" have submitted "Problem sheet 4"
    And "student1" has been marked on "Problem sheet 4"

  @javascript
  Scenario: The controls fit one bar and the first row is above the fold
    When I log in as "hub1" and view the "Hub dashboard"
    Then the "Hub dashboard" controls should be "View, Year, Type, Find, Filter, Refresh"
    And I should not see "Words" in the "Hub dashboard" block
    And I should not see "Only with" in the "Hub dashboard" block
    And the first assessment row should be within "600" pixels of the top of the block at "1300" pixels wide

  @javascript
  Scenario: Find offers people and words from one box
    When I log in as "hub1" and view the "Hub dashboard"
    And I type "mo" in the hub "Find" box
    Then the Find suggestions should list "Mo Marker" under "Staff"
    And the Find suggestions should offer "Only mo" and "Not mo"
    When I choose "Not mo" from the Find suggestions
    Then I should see the word chip "Not mo" in the "Hub dashboard" block
    And I should not see "Problem sheet 4" in the "Hub dashboard" block

  @javascript
  Scenario: A word chip shows its Only or Not mode and can be switched in place
    Given a summative "assign" "Referral: Problem sheet 4" in "MTH2003" was due "3 days ago"
    When I log in as "hub1" and view the "Hub dashboard"
    And I add the word filter "referral" as "Not"
    Then the word chip "referral" should show the mode "Not"
    And I should not see "Referral: Problem sheet 4" in the "Hub dashboard" block
    When I switch the word chip "referral" to "Only"
    Then I should see "Referral: Problem sheet 4" in the "Hub dashboard" block
    And I should not see "Problem sheet 5" in the "Hub dashboard" block

  @javascript
  Scenario: Choosing a person shows a scope banner because the list now covers the institution
    When I log in as "hub1" and view the "Hub dashboard"
    And I search the hub dashboard for the person "Mo Marker"
    Then I should see "Showing Mo Marker's assessments across the whole institution" in the "Hub dashboard" block
    And the collapsed row "Problem sheet 4" should show the status "Mo Marker: allocated 1, marked 1, 0 to do"
    When I click on "Clear" "button" in the "Hub dashboard" "block"
    Then I should not see "across the whole institution"

  @javascript
  Scenario: Searching for a student shows only what they take, with where they stand
    Given a summative "assign" "Lab report" in "BIO1" was due "10 days ago"
    And a summative "assign" "Problem set" in "PHY1" was due "10 days ago"
    And "student2" has submitted "Lab report"
    When I log in as "hub1" and view the "Hub dashboard"
    And I search the hub dashboard for the person "Sue Student"
    Then I should see "Lab report" in the "Hub dashboard" block
    And I should not see "Problem set" in the "Hub dashboard" block
    And the collapsed row "Lab report" should show the status "Submitted"

  Scenario: Word filters start empty on every visit
    When I log in as "hub1" and view the "Hub dashboard"
    Then I should not see "Not referral" in the "Hub dashboard" block
    And I should not see "Not deferral" in the "Hub dashboard" block

  @javascript
  Scenario: The Only with list sits behind one Filter button, grouped, and active filters show as chips
    Given "student1" has an extension on "Problem sheet 4" to "in 3 days"
    When I log in as "hub1" and view the "Hub dashboard"
    Then I should not see "Plagiarism flag" in the "Hub dashboard" block
    When I click on "Filter" "button" in the "Hub dashboard" "block"
    Then the Filter list should have the groups "Reason, Work, Allocation, Hub notes, Set-up"
    When I tick "Mitigation or extension" in the Filter list
    Then I should see "Filter 1" in the "Hub dashboard" block
    And I should see the filter chip "Mitigation or extension"
    And I should see "Problem sheet 4" in the "Hub dashboard" block
    And I should not see "Problem sheet 5" in the "Hub dashboard" block
    When I remove the filter chip "Mitigation or extension"
    Then I should see "Problem sheet 5" in the "Hub dashboard" block

  @javascript
  Scenario: Summative work is shown by default, and the type choice is remembered
    Given a formative "assign" "Practice quiz" in "MTH2003" was due "3 days ago"
    When I log in as "hub1" and view the "Hub dashboard"
    Then I should see "Problem sheet 4" in the "Hub dashboard" block
    And I should not see "Practice quiz" in the "Hub dashboard" block
    When I click on "Both" "button" in the "Hub dashboard" "block"
    Then I should see "Practice quiz" in the "Hub dashboard" block
    When I log in as "hub1" and view the "Hub dashboard"
    Then I should see "Practice quiz" in the "Hub dashboard" block
    And I should see "Problem sheet 5" in the "Hub dashboard" block

  @javascript
  Scenario: At phone width the first row is one screen down and the status strip scrolls sideways
    Given I change the viewport size to "390x844"
    When I log in as "hub1" and view the "Hub dashboard"
    Then the status strip should fit one line
    And the first assessment row should be within "700" pixels of the top of the block
    And the collapsed row "Problem sheet 4" should be at most "160" pixels tall
