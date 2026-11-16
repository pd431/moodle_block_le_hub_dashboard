@block @block_le_hub_dashboard @javascript
Feature: The administrator sets the categories the hub works in
  In order to keep archived and irrelevant categories out of every hub view
  As an administrator
  I need to tick the faculties and departments the hub supports, once, for every view, code rule and search

  Background:
    Given the hub fixtures from hub-view.feature exist
    And "hub1" picked the hub categories "Faculty of Science"
    And a summative "assign" "Problem sheet 4" in "MTH2003" was due "20 days ago" with marking due "2 days ago"
    And "student1" and "student2" have submitted "Problem sheet 4"

  Scenario: The administrator limits the hub to the categories that matter
    Given the course category "Archive 2019" exists under "Faculty of Science"
    And a summative "assign" "Old lab" in "OLD1" under "Archive 2019" was due "400 days ago"
    And the hub's valid categories are "Faculty of Science / Biosciences, Faculty of Science / Physics"
    When I log in as "hub1" and view the "Hub dashboard"
    Then there should be a hub row "Problem sheet 4"
    And there should not be a hub row "Old lab"
    When I click on "This view" "button" in the "Hub dashboard" "block"
    And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"
    Then I should see "Biosciences" in the view editor under "Departments"
    And I should not see "Archive 2019" in the view editor under "Departments"
    And I should not see "Faculty of Arts" in the view editor under "Departments"

  Scenario: With no valid categories set, every category is offered, as before
    When I log in as "hub1" and view the "Hub dashboard"
    And I click on "This view" "button" in the "Hub dashboard" "block"
    And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"
    Then I should see "Faculty of Arts" in the view editor under "Departments"

  Scenario: A module added by code outside the valid categories is kept but not shown, and the editor says so
    Given the hub's valid categories are "Faculty of Science / Biosciences"
    And a summative "assign" "Essay" in "HIS1" was due "3 days ago" with marking due "in 10 days"
    When I log in as "hub1" and view the "Hub dashboard"
    And I add the module "HIS1" to the hub view
    Then the view editor should mark "HIS1" as outside the hub's categories
    And there should not be a hub row "Essay"

  Scenario: A person search stays inside the valid categories
    Given the hub's valid categories are "Faculty of Science / Biosciences"
    And a summative "assign" "Essay" in "HIS1" was due "3 days ago" with marking due "in 10 days"
    And "student1" has submitted "Essay"
    And "student1" is allocated to "marker1" on "Essay"
    And "student1" is allocated to "marker1" on "Problem sheet 4"
    When I log in as "hub1" and view the "Hub dashboard"
    And I search the hub dashboard for the person "Mo Marker"
    Then there should be a hub row "Problem sheet 4"
    And there should not be a hub row "Essay"
