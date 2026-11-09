@mod @mod_bookit @javascript
Feature: Room legend hover text
  In order to read room details
  As a booking person
  I need hover text to show the characters that were saved

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
    And the following "mod_bookit > rooms" exist:
      | name      | shortname | description          | seats |
      | Studio    | A&B       | Ton & Licht <Studio> | 18    |
      | Quiet     | QUIET     |                      | 4     |

  Scenario: Shortname hover shows the description and seats once
    Given I log in as "admin"
    And I change window size to "medium"
    And I am on the "My BookIt Activity" "bookit activity" page
    When I hover "//button[contains(., 'A&B')]" "xpath_element"
    Then I should see "Ton & Licht <Studio>"
    And I should see "Seats: 18"
    And I should not see "&amp;"
    And I should not see "&lt;"

  Scenario: Hover without a description shows only the seat count
    Given I log in as "admin"
    And I change window size to "medium"
    And I am on the "My BookIt Activity" "bookit activity" page
    When I hover "//button[contains(., 'QUIET')]" "xpath_element"
    Then I should see "Seats: 4"
    And I should not see "Ton & Licht"

  Scenario: Overflow hover shows the shortname once
    Given the following "mod_bookit > rooms" exist:
      | name   | shortname | seats |
      | Room 1 | RM0001    | 1     |
      | Room 2 | RM0002    | 1     |
      | Room 3 | RM0003    | 1     |
      | Room 4 | RM0004    | 1     |
      | Room 5 | RM0005    | 1     |
      | Room 6 | RM0006    | 1     |
      | Room 7 | RM0007    | 1     |
      | Room 8 | RM0008    | 1     |
    And I log in as "admin"
    And I change viewport size to "320x700"
    And I am on the "My BookIt Activity" "bookit activity" page
    When I hover "[data-region='room-legend-overflow'] a" "css_element"
    Then I should see "A&B"
    And I should not see "&amp;"
