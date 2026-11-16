@block @block_le_hub_dashboard @javascript
Feature: The hub meets its contrast targets in light and dark colour modes
  In order to use the hub with Moodle's dark mode turned on
  As hub staff
  I need every part of the hub to stay readable, in every state, whichever colour mode I use

  # Pierre, 2026-11-14 (hub-beta3.feature, its D1; cited as DM1 in the code): dark mode as My assessments beta38 does it. Targets as there:
  # every text APCA Lc 75 and WCAG 4.5:1; icons, bar segments, control outlines and focus indicators Lc 45 and
  # 3:1. Moodle 5.3 draws dark mode by setting data-bs-theme="dark" on the html tag; the light scenarios also run
  # on 4.5 and 5.2, which have no colour modes, and the dark ones skip there.

  Background:
    Given the hub fixtures from hub-view.feature exist
    And "hub1" picked the hub categories "Faculty of Science"
    And a summative "assign" "Problem sheet 4" in "MTH2003" was due "20 days ago" with marking due "2 days ago"
    And a summative "assign" "Problem sheet 5" in "MTH2003" is due "in 6 days"
    And "student1" and "student2" have submitted "Problem sheet 4"
    And "student1" has been marked on "Problem sheet 4"
    And the site has colour modes turned on if it can

  Scenario Outline: The hub meets its contrast targets at rest, hovered and focused in <mode> mode
    Given "hub1" uses the <mode> colour mode
    When I log in as "hub1" and view the "Hub dashboard"
    Then the page should be in <mode> colour mode
    And the Hub dashboard block should meet its contrast targets
    And every kind of control in the Hub dashboard block should meet its contrast targets when hovered
    And keyboard focus should be visible on every control in the Hub dashboard block

    Examples:
      | mode  |
      | light |
      | dark  |

  Scenario Outline: An open flag list and an expanded row meet their targets in <mode> mode
    Given "hub1" uses the <mode> colour mode
    When I log in as "hub1" and view the "Hub dashboard"
    And I click on the flag count of "Problem sheet 4"
    And I expand the row "Problem sheet 4"
    Then the Hub dashboard block should meet its contrast targets

    Examples:
      | mode  |
      | light |
      | dark  |

  Scenario Outline: The view editor meets its targets in <mode> mode
    Given "hub1" uses the <mode> colour mode
    When I log in as "hub1" and view the "Hub dashboard"
    And I click on "This view" "button" in the "Hub dashboard" "block"
    And I click on "Edit what this view shows" "link" in the "Hub dashboard" "block"
    Then the Hub dashboard block should meet its contrast targets
    And every kind of control in the Hub dashboard block should meet its contrast targets when hovered

    Examples:
      | mode  |
      | light |
      | dark  |
