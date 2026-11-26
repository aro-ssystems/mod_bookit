@mod @mod_bookit
Feature: Room shortname contrast
  In order to read the default room shortname
  As an administrator
  I need dark text on an empty room colour in the resource list

  Background:
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I check "Enable resources module"
    And I press "Save changes"
    And the following "mod_bookit > rooms" exist:
      | name       | shortname |
      | Other room | OTHER     |
    And the following "mod_bookit > resource_categories" exist:
      | name     |
      | Contrast |
    And the following "mod_bookit > resources" exist:
      | name           | category_name | rooms        |
      | Contrast item  | Contrast      | Default room |

  Scenario: Default room shortname uses dark text on an empty colour
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Resources" "link"
    Then the "data-bookit-resource-tabledata-room-textclass" attribute of "DFLT" "link" should contain "text-dark"
