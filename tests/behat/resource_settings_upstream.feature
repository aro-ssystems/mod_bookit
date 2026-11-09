@mod @mod_bookit @javascript
Feature: Resource notification settings next to the hidden optional modules
  In order to configure resource status messages
  As an administrator
  I need the resource notification settings while the optional module switches stay hidden

  Scenario: Admin sees resource notification settings and no optional module switches
    Given I log in as "admin"
    When I navigate to "Plugins > Activity modules > BookIt" in site administration
    Then I should see "This feature is not available in this version."
    And I should see "Resource status-change notifications"
    And "Enable resources module" "field" should not be visible
    And "Enable checklist module" "field" should not be visible
