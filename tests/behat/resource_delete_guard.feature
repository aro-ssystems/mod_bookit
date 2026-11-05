@mod @mod_bookit @javascript
Feature: Resource delete guards in the catalog
  In order to protect booked catalog data
  As an administrator
  I need visible rejection when delete is not allowed and deactivation when a resource was booked

  Background:
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I check "Enable resources module"
    And I press "Save changes"
    And I click on "Run install helper" "link"
    And the Bookit default resource data exists
    And I log out

  Scenario: Admin cannot delete a category that still has resources
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Resources" "link"
    And I should see "Technical Equipment"
    When I click on "button[data-action='edit-category']" "css_element" in the "Technical Equipment" "table_row"
    And I click on "button[data-action='delete']" "css_element"
    Then I should see "no resources must be assigned"
    And I should see "Technical Equipment"

  Scenario: Admin cannot delete a booked resource but can deactivate it
    Given the following "mod_bookit > resource_categories" exist:
      | name           |
      | Guard Category |
    And the following "mod_bookit > resources" exist:
      | name           | category_name  |
      | Guard Resource | Guard Category |
    And the following "mod_bookit > events" exist:
      | name        | username | startdate                         | enddate                              | bookingstatus | institution |
      | Guard Event | admin    | ##today noon##%Y-%m-%dT%H:%M:%S## | ##tomorrow noon##%Y-%m-%dT%H:%M:%S## | 1             | 1           |
    And the following "mod_bookit > event_resources" exist:
      | event_name  | resource_name  |
      | Guard Event | Guard Resource |
    And I log in as "admin"
    And I change window size to "large"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Resources" "link"
    When I click on "button[id^='edit-item-']" "css_element" in the "Guard Resource" "table_row"
    And I click on "button[data-action='delete']" "css_element"
    And I wait "1" seconds
    And I click on "button[data-action='delete']" "css_element"
    Then I should see "already been used in at least one booking"
    And I should see "Guard Resource"
    When I click on "button[data-action='deactivate']" "css_element"
    Then I should see "Inactive"
