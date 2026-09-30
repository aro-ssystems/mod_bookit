@mod @mod_bookit
Feature: Room form
  In order to keep room shortnames usable
  As an administrator
  I need to open the room form and reject a shortname that is too long

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | roomless | No        | Access   | roomless@example.com |
    And the following "mod_bookit > rooms" exist:
      | name      | shortname |
      | Edit Room | EDIT1     |

  Scenario: Room form opens for a new room and an existing room shows the saved shortname
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Rooms" "link"
    And I press "New room"
    Then I should see "Shortname"
    And I visit "/mod/bookit/admin/rooms.php"
    And I click on "Edit Room" "link"
    And I click on "Edit room data" "link"
    And the field "Shortname" matches value "EDIT1"

  Scenario: Room form is not available to a user without room administration
    Given I log in as "roomless"
    And I am on site homepage
    Then I should not see "New room"
    And I should not see "Edit room data"

  Scenario: Room form saves a shortname of six characters
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Rooms" "link"
    And I press "New room"
    And I set the following fields to these values:
      | Name            | Form Room |
      | Shortname       | FORM01    |
      | Amount of seats | 12        |
      | Color           | #336699   |
    And I press "Save changes"
    And I visit "/mod/bookit/admin/rooms.php"
    Then I should see "FORM01"

  Scenario: Room form rejects a shortname of seven characters when creating a room
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Rooms" "link"
    And I press "New room"
    And I set the following fields to these values:
      | Name            | Too Long Room |
      | Shortname       | TOOLONG       |
      | Amount of seats | 12            |
      | Color           | #336699       |
    And I press "Save changes"
    Then I should see "at most 6 characters"
    And I visit "/mod/bookit/admin/rooms.php"
    And I should not see "TOOLONG"

  Scenario: Room form keeps the saved shortname when an edit is too long
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Rooms" "link"
    And I click on "Edit Room" "link"
    And I click on "Edit room data" "link"
    And I set the following fields to these values:
      | Shortname       | TOOLONG |
      | Amount of seats | 12      |
      | Color           | #336699 |
    And I press "Save changes"
    Then I should see "at most 6 characters"
    And I visit "/mod/bookit/admin/rooms.php"
    And I should see "EDIT1"
    And I should not see "TOOLONG"
