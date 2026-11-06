@mod @mod_bookit @javascript

Feature: Delete master checklist category
  In order to manage master checklists for bookit activities
  As an administrator and user with the role bookit_serviceteam
  I need to be able to view and edit master checklists

  Background:
    Given the following "users" exist:
      | username     | firstname | lastname | email                    |
      | serviceteam1 | Service   | Team     | serviceteam@example.com |
    And I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I check "Enable checklist module"
    And I press "Save changes"
    And the Bookit default roles are imported
    And the Bookit default checklist data exists
    And the following "role assigns" exist:
      | user         | role               | contextlevel | reference |
      | serviceteam1 | bookit_serviceteam | System       |           |
    And I log out

  Scenario: Admin can delete an empty master checklist category
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Master checklist" "link"
    And I click on "add-checklist-category-button" "button"
    And I set the following fields to these values:
      | Category name | Empty category admin |
    And I press "Save changes"
    And I should see "Empty category admin"
    And I click on "button[id^='edit-checklistcategory-']" "css_element" in the "Empty category admin" "table_row"
    And I should see "Delete"
    And I click on "button[data-action='delete']" "css_element"
    And I should see "Confirm"
    And I click on "button[data-action='delete']" "css_element"
    Then I should not see "Empty category admin"

  Scenario: Service-Team can delete an empty master checklist category
    Given I log in as "serviceteam1"
    And I change window size to "large"
    And I click on "BookIt" "link" in the ".primary-navigation" "css_element"
    And I click on "Master checklist" "link"
    And I click on "add-checklist-category-button" "button"
    And I set the following fields to these values:
      | Category name | Empty category service |
    And I press "Save changes"
    And I should see "Empty category service"
    And I click on "button[id^='edit-checklistcategory-']" "css_element" in the "Empty category service" "table_row"
    And I should see "Delete"
    And I click on "button[data-action='delete']" "css_element"
    And I should see "Confirm"
    And I click on "button[data-action='delete']" "css_element"
    Then I should not see "Empty category service"

  Scenario: A category that still has items cannot be deleted
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Master checklist" "link"
    And I should see "Exam Preparation"
    And I click on "button[id^='edit-checklistcategory-']" "css_element" in the "Exam Preparation" "table_row"
    And I should see "Delete"
    And I click on "button[data-action='delete']" "css_element"
    And I should see "Confirm"
    And I click on "button[data-action='delete']" "css_element"
    Then I should see "This checklist category cannot be deleted because it still contains items. Empty it first."
    And I should see "Exam Preparation"
