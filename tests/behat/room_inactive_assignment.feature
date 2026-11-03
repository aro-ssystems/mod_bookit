@mod @mod_bookit
Feature: Inactive room assignments stay visible
  In order to keep existing room assignments
  As an administrator
  I need a deactivated room to stay named on resources and bookings

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activities" exist:
      | activity | name               | course | idnumber |
      | bookit   | My BookIt Activity | C1     | bookit1  |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | booker   | Bea       | Booker   | booker@example.com |
    And the following "course enrolments" exist:
      | user   | course | role    |
      | booker | C1     | student |
    And I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I check "Enable resources module"
    And I press "Save changes"
    And the following "mod_bookit > rooms" exist:
      | name       | shortname | description | seats |
      | Kept Hall  | KEPT      | Beamer      | 20    |
      | Other Hall | OTHER     | Window      | 12    |
    And the following "mod_bookit > resource_categories" exist:
      | name     |
      | Keep cat |
    And the following "mod_bookit > resources" exist:
      | name    | category_name | rooms     |
      | Mic One | Keep cat      | Kept Hall |
      | Mic Two | Keep cat      | Kept Hall |
      | Shared  | Keep cat      |           |
    And the following "mod_bookit > events" exist:
      | name         | startdate           | enddate             | bookingstatus | institution | room      | username |
      | Morning exam | ##tomorrow 09:00##%Y-%m-%dT%H:%M:%S## | ##tomorrow 11:00##%Y-%m-%dT%H:%M:%S## | 2 | 1 | Kept Hall | booker |

  Scenario: Explicit assignments keep the room name after the room form deactivates it
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Rooms" "link"
    And I click on "Kept Hall" "link"
    And I click on "Edit room data" "link"
    And I set the field "Set room active" to "0"
    And I press "Save changes"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Resources" "link"
    Then the "data-bookit-resource-roomname" attribute of "//tr[contains(., 'Mic One')]//*[@data-bookit-resource-roomname]" "xpath_element" should contain "Kept Hall"
    And the "data-bookit-resource-roomname" attribute of "//tr[contains(., 'Mic Two')]//*[@data-bookit-resource-roomname]" "xpath_element" should contain "Kept Hall"
    And "//tr[contains(., 'Shared')]//*[@data-bookit-resource-roomname='Kept Hall']" "xpath_element" should not exist
    And I open the Bookit overview "allrequests" for "My BookIt Activity"
    And I should see "Kept Hall" in the "Morning exam" "table_row"
    And I am on the "My BookIt Activity" "bookit activity" page
    And I should not see "KEPT"
    And I click on "Room overview" "link"
    And I should not see "Kept Hall"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Rooms" "link"
    And I click on "Kept Hall" "link"
    And I click on "Edit room data" "link"
    And I set the field "Set room active" to "1"
    And I press "Save changes"
    And I am on the "My BookIt Activity" "bookit activity" page
    And I should see "KEPT"
