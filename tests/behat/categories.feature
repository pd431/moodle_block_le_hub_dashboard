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
    And the module "OLD1" in the category "Archive 2019" has a summative "assign" "Old lab" due "400 days ago"
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
    And I click on "This view" "button" in the "Hub dashboard" "block"
    And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"
    Then the view editor should mark "HIS1" as outside the hub's categories
    And there should not be a hub row "Essay"

  Scenario: A person search stays inside the valid categories
    Given the hub's valid categories are "Faculty of Science / Biosciences"
    And a summative "assign" "Essay" in "HIS1" was due "3 days ago" with marking due "in 10 days"
    And "student1" has submitted "Essay"
    When I log in as "hub1" and view the "Hub dashboard"
    And I search the hub dashboard for the person "Sam Student"
    Then there should be a hub row "Problem sheet 4"
    And there should not be a hub row "Essay"

  Scenario: Ticking a department ticks its children, and unticking one child leaves the parent partly ticked
    When I log in as "hub1" and view the "Hub dashboard"
    And I click on "This view" "button" in the "Hub dashboard" "block"
    And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"
    Then "Faculty of Science" should be fully ticked in the department tree
    And "Biosciences" should be ticked in the department tree
    When I untick "Faculty of Science" in the department tree
    Then "Biosciences" should not be ticked in the department tree
    When I tick "Faculty of Science" in the department tree
    Then "Biosciences" should be ticked in the department tree
    And "Physics" should be ticked in the department tree
    And "Faculty of Science" should be fully ticked in the department tree
    When I untick "Physics" in the department tree
    Then "Faculty of Science" should be partly ticked in the department tree
    And the view's departments should be saved as "Biosciences"
    When I tick "Physics" in the department tree
    Then "Faculty of Science" should be fully ticked in the department tree
    And the view's departments should be saved as "Faculty of Science"

  Scenario: Typing in the department search narrows the tree and keeps the path readable
    When I log in as "hub1" and view the "Hub dashboard"
    And I click on "This view" "button" in the "Hub dashboard" "block"
    And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"
    And I type "hist" in the department search box
    Then the department tree should list "Faculty of Arts, History"
    And the department tree should not list "Physics"
    When I type "fac sci" in the department search box
    Then the department tree should list "Faculty of Science"
    And the department tree should not list "Faculty of Arts"
    When I type "" in the department search box
    Then the department tree should list "Faculty of Arts, Physics"

  Scenario: The administrator's picker has a search box and the same ticking
    Given I log in as "admin"
    And I navigate to "Plugins > Blocks > Hub dashboard" in site administration
    When I type "bio" in the hub categories search box
    Then the categories picker should list "Faculty of Science, Biosciences"
    And the categories picker should not list "Physics"
    When I type "" in the hub categories search box
    And I tick "Faculty of Science" in the department tree
    Then "Physics" should be ticked in the department tree
    When I press "Save changes"
    And I navigate to "Plugins > Blocks > Hub dashboard" in site administration
    Then "Faculty of Science" should be fully ticked in the department tree
    And "Biosciences" should be ticked in the department tree
