@block @block_le_hub_dashboard
Feature: The hub shows its frame at once and fetches the rows afterwards
  In order to open my Dashboard without waiting for a large view
  As education support (hub) staff
  I need the hub to render its controls and groups first and bring the rows in behind, within a time budget

  Background:
    Given the hub fixtures from hub-view.feature exist
    And "hub1" picked the hub categories "Faculty of Science"
    And a summative "assign" "Problem sheet 4" in "MTH2003" was due "20 days ago" with marking due "2 days ago"
    And a summative "assign" "Problem sheet 5" in "MTH2003" is due "in 6 days"
    And "student1" and "student2" have submitted "Problem sheet 4"
    And "student1" has been marked on "Problem sheet 4"
    And 26 summative assessments in "MTH2003" were due "in 30 days" with marking due "in 50 days"

  # L1: the page comes with the frame and no rows. Read without a browser, so nothing has fetched them yet.
  Scenario: The Dashboard page arrives with the hub's frame, before its rows
    When I log in as "hub1" and view my dashboard
    Then I should see "Hub dashboard" in the "Hub dashboard" block
    And the "Chase" group header should read "Chase …"
    And there should not be a hub row "Problem sheet 4"

  # L2: then the rows, in batches. A view of one batch or fewer comes with the page.
  @javascript
  Scenario: The hub's rows come in behind the frame
    When I log in as "hub1" and view my dashboard
    And the hub has finished loading
    Then I should see "My assessments" in the "My assessments" block
    And the "Chase" group header should read "Chase 1 · Marking late 1"
    And there should be a hub row "Problem sheet 4"
    And the "Done" group should be collapsed

  # L3: the time budget
  @javascript
  Scenario: A batch that runs past the budget says so and can be retried
    Given the hub's load budget is "2" seconds
    And the hub's rows take "5" seconds to load
    When I log in as "hub1" and view the "Hub dashboard"
    Then I should see "Still loading: this view covers 28 assessments" in the "Hub dashboard" block
    And there should not be a hub row "Problem sheet 4"
    When the hub's rows load at once again
    And I click on "Retry" "button" in the "Hub dashboard" "block"
    And the hub has finished loading
    Then there should be a hub row "Problem sheet 4"
    And there should be a hub row "Problem sheet 5"

  # L4: the tree is fetched when the editor opens
  @javascript
  Scenario: The department tree is not in the page until the editor opens
    When I log in as "hub1" and view the "Hub dashboard"
    Then the page should not contain the department tree
    When I click on "This view" "button" in the "Hub dashboard" "block"
    And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"
    Then I should see "Faculty of Science" in the "Hub dashboard" block
