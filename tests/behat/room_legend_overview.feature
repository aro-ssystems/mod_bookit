@mod @mod_bookit @javascript
Feature: Room legend and overview
  In order to compare bookable rooms
  As a booking person
  I need shortname buttons or a single room overview

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email              |
      | booker   | Bea       | Booker   | booker@example.com |
    And the following "course enrolments" exist:
      | user   | course | role    |
      | booker | C1     | student |
    And the following "activities" exist:
      | activity | name               | course | idnumber |
      | bookit   | My BookIt Activity | C1     | bookit1  |
    And the following config values are set as admin:
      | resourcesenabled | 1 | mod_bookit |
    And the following "mod_bookit > rooms" exist:
      | name    | shortname | description | seats | active |
      | Alpha   | AA        | Beamer      | 20    | 1      |
      | Beta    | BB        | Window      | 12    | 1      |
      | Closed  | CC        | Stored      | 8     | 0      |
    And the following "mod_bookit > resource_categories" exist:
      | name    |
      | Legend  |
    And the following "mod_bookit > resources" exist:
      | name | category_name | rooms |
      | Mic  | Legend        | Alpha |

  Scenario: Shortname buttons show when they fit
    Given I log in as "admin"
    And I change window size to "medium"
    And I am on the "My BookIt Activity" "bookit activity" page
    Then I should see "AA"
    And I should see "BB"
    And I should not see "CC"
    And "[data-region='room-legend-buttons']" "css_element" should be visible
    And "[data-region='room-legend-overflow']" "css_element" should not be visible
    When I hover "//button[contains(., 'AA')]" "xpath_element"
    Then I should see "Beamer"
    And I should see "Seats: 20"

  Scenario: A narrow window shows only the room overview
    Given the following "mod_bookit > rooms" exist:
      | name   | shortname | description | seats | active |
      | Room 1 | RM0001    | Extra       | 1     | 1      |
      | Room 2 | RM0002    | Extra       | 1     | 1      |
      | Room 3 | RM0003    | Extra       | 1     | 1      |
      | Room 4 | RM0004    | Extra       | 1     | 1      |
      | Room 5 | RM0005    | Extra       | 1     | 1      |
      | Room 6 | RM0006    | Extra       | 1     | 1      |
      | Room 7 | RM0007    | Extra       | 1     | 1      |
      | Room 8 | RM0008    | Extra       | 1     | 1      |
    And I log in as "admin"
    And I change viewport size to "320x700"
    And I am on the "My BookIt Activity" "bookit activity" page
    Then "[data-region='room-legend-buttons']" "css_element" should not be visible
    And "[data-region='room-legend-overflow']" "css_element" should be visible
    When I click on "Room overview" "link"
    Then I should see "Short name"
    And I should see "Description"
    And I should see "Seats"
    And I should see "Resources"
    And I should see "Alpha"
    And I should see "Beamer"
    And I should see "Mic"
    And I should not see "Closed"
    When I set the field "Filter rooms" to "AA — Alpha"
    Then "//tr[contains(., 'Beta')]" "xpath_element" should not be visible
    And "//tr[contains(., 'Alpha')]" "xpath_element" should be visible
    When I set the field "Filter rooms" to ""
    Then I should see "Beta"
