@block @block_le_hub_dashboard
Feature: Hub staff shape what they see with saved views, and ignore what is not theirs
  In order to look after my own modules and nobody else's
  As education support (hub) staff
  I need personal views by department or module code, an Ignore menu on each row, and a panel to edit them

  Background:
    Given the hub fixtures from hub-view.feature exist
    And "hub1" picked the hub categories "Faculty of Science"
    And a summative "assign" "Problem sheet 4" in "MTH2003" was due "20 days ago" with marking due "2 days ago"
    And a summative "assign" "Problem sheet 5" in "MTH2003" is due "in 6 days"
    And "student1" and "student2" have submitted "Problem sheet 4"
    And "student1" has been marked on "Problem sheet 4"

  Scenario: A hub user with no course enrolment sees the Hub dashboard block
    When I log in as "hub1" and view my dashboard
    Then I should see "Hub dashboard" in the "Hub dashboard" block

  Scenario: People without the hub capability never see the block, even when it is on their page
    When I log in as "convenor" and view my dashboard
    Then I should not see "Hub dashboard"
    And I should see "Problem sheet 4" in the "My assessments" block

  Scenario: A hub user who also convenes a module gets both blocks
    Given the following "course enrolments" exist:
      | user | course  | role           |
      | hub1 | MTH2003 | moduleconvenor |
    When I log in as "hub1" and view my dashboard
    Then I should see "Problem sheet 4" in the "My assessments" block
    And I should see "Hub dashboard" in the "Hub dashboard" block

  Scenario: The view menu and new view button are labelled
    When I log in as "hub1" and view the "Hub dashboard"
    Then I should see "This view" in the "Hub dashboard" block
    And I should see "New view" in the "Hub dashboard" block
    And I should not see "⋯" in the "Hub dashboard" block

  @javascript
  Scenario: A view can be limited to modules whose code starts with given text
    Given a summative "assign" "Sheet 1" in "MTH1001" was due "3 days ago"
    And a summative "assign" "Sheet 3" in "MTH3005" was due "3 days ago"
    When I log in as "hub1" and view the "Hub dashboard"
    And I create a hub view "My modules" with module codes starting with "MTH2" or "MTH3"
    Then I should see "Problem sheet 4" in the "Hub dashboard" block
    And I should see "Sheet 3" in the "Hub dashboard" block
    And I should not see "Sheet 1" in the "Hub dashboard" block

  @javascript
  Scenario: A module outside the view's rules can be added by its code
    Given a summative "assign" "Essay" in "HIS1" was due "3 days ago"
    When I log in as "hub1" and view the "Hub dashboard"
    Then I should not see "Essay" in the "Hub dashboard" block
    When I add the module "HIS1" to the hub view
    Then I should see "Essay" in the "Hub dashboard" block

  @javascript
  Scenario: Views can be added, copied, renamed and deleted, and are personal
    When I log in as "hub1" and view the "Hub dashboard"
    And I create a hub view "Arts team" with the departments "Faculty of Arts"
    And I copy the hub view "Arts team" as "History only"
    And I rename the hub view "History only" to "History cover"
    Then I should see "Arts team" in the "Hub dashboard" block
    And I should see "History cover" in the "Hub dashboard" block
    When I delete the hub view "History cover"
    Then I should not see "History cover" in the "Hub dashboard" block
    When I log in as "hubhist" and view the "Hub dashboard"
    Then I should not see "Arts team" in the "Hub dashboard" block

  @javascript
  Scenario: The last view used opens next time
    Given "hub1" has the hub views "Arts team" and "Everything", and last used "Arts team"
    When I log in as "hub1" and view the "Hub dashboard"
    Then the hub view "Arts team" should be open

  @javascript
  Scenario: Ignoring a module or an assessment takes a second click to confirm and can be undone
    When I log in as "hub1" and view the "Hub dashboard"
    And I expand the row "Problem sheet 4"
    And I click on "Ignore in Science team" "button" in the "Hub dashboard" "block"
    And I click on "This assessment only" "link" in the "Hub dashboard" "block"
    Then I should see "Click again to confirm" in the "Hub dashboard" block
    And I should see "Problem sheet 4" in the "Hub dashboard" block
    When I click on "Click again to confirm" "link" in the "Hub dashboard" "block"
    Then there should not be a hub row "Problem sheet 4"
    And I should see "Problem sheet 5" in the "Hub dashboard" block
    And I should see "Problem sheet 4 taken out of Science team" in the "Hub dashboard" block
    When I click on "Undo" "button" in the "Hub dashboard" "block"
    Then I should see "Problem sheet 4" in the "Hub dashboard" block
    When I expand the row "Problem sheet 4"
    And I click on "Ignore in Science team" "button" in the "Hub dashboard" "block"
    And I click on "This module, MTH2003" "link" in the "Hub dashboard" "block"
    And I click on "Click again to confirm" "link" in the "Hub dashboard" "block"
    Then I should not see "Problem sheet 4" in the "Hub dashboard" block
    And I should not see "Problem sheet 5" in the "Hub dashboard" block
    When I put "MTH2003" back in the hub view
    Then I should see "Problem sheet 4" in the "Hub dashboard" block

  @javascript
  Scenario: Opening the Ignore menu again puts an unconfirmed item back
    When I log in as "hub1" and view the "Hub dashboard"
    And I expand the row "Problem sheet 4"
    And I click on "Ignore in Science team" "button" in the "Hub dashboard" "block"
    And I click on "This assessment only" "link" in the "Hub dashboard" "block"
    And I click on "Ignore in Science team" "button" in the "Hub dashboard" "block"
    And I click on "Ignore in Science team" "button" in the "Hub dashboard" "block"
    Then I should see "This assessment only" in the "Hub dashboard" block
    And I should not see "Click again to confirm" in the "Hub dashboard" block

  @javascript
  Scenario: Ignored assessments are listed in the view panel and can be put back
    Given "hub1" ignored the assessment "Problem sheet 4" in the hub view "Science team"
    When I log in as "hub1" and view the "Hub dashboard"
    Then I should not see "Problem sheet 4" in the "Hub dashboard" block
    When I click on "This view" "button" in the "Hub dashboard" "block"
    And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"
    Then I should see "Problem sheet 4" in the view editor under "Exceptions"
    When I click on "Put back" "link" in the view editor
    Then I should see "Problem sheet 4" in the "Hub dashboard" block

  @javascript
  Scenario: The view editor opens beside the list and the list updates as rules change
    Given a summative "assign" "Sheet 3" in "MTH3005" was due "3 days ago"
    When I log in as "hub1" and view the "Hub dashboard"
    And I click on "This view" "button" in the "Hub dashboard" "block"
    And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"
    Then the view editor should open as a side panel
    And the first assessment row should still be visible
    When I add the module code line "starts with" "MTH3" to the view editor
    Then I should see "Sheet 3" in the "Hub dashboard" block
    And the view editor should say "5 of 6 modules"
