@mod @mod_bookit @javascript
Feature: Room active toggle
  In order to retire a room without opening its form
  As an administrator
  I need a switch in the room list

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | roomless | No        | Access   | roomless@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | roomless | C1     | student |
    And the following "activities" exist:
      | activity | name               | course | idnumber |
      | bookit   | My BookIt Activity | C1     | bookit1  |
    And the following "mod_bookit > rooms" exist:
      | name        | shortname |
      | Toggle Hall | TGONE     |

  Scenario: The room list switch deactivates and reactivates a room
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Rooms" "link"
    When I click on "//tr[contains(., 'Toggle Hall')]//input[@data-action='toggle-room-active']" "xpath_element"
    And I reload the page
    Then the "class" attribute of "//tr[contains(., 'Toggle Hall')]" "xpath_element" should contain "text-muted"
    And I should not see "Edit room data"
    And I am on the "My BookIt Activity" "bookit activity" page
    And I should not see "TGONE"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Rooms" "link"
    And I click on "//tr[contains(., 'Toggle Hall')]//input[@data-action='toggle-room-active']" "xpath_element"
    And I reload the page
    And "//tr[contains(@class, 'text-muted') and contains(., 'Toggle Hall')]" "xpath_element" should not exist
    And I am on the "My BookIt Activity" "bookit activity" page
    And I should see "TGONE"

  Scenario: A user without room administration cannot change the switch
    Given I log in as "roomless"
    And I am on site homepage
    And I should be denied access to "/mod/bookit/admin/rooms.php"
    And I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Rooms" "link"
    And "//tr[contains(@class, 'text-muted') and contains(., 'Toggle Hall')]" "xpath_element" should not exist
