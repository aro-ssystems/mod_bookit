@mod @mod_bookit @mod_bookit_demo @javascript
Feature: Generate and reset BookIt demo data
  In order to explore BookIt after installation
  As a site administrator
  I need to generate demo data once and reset the plugin to its baseline

  Scenario: Admin generates demo data once and a second run is refused
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    When I click on "Generate demo data" "link"
    Then I should see "Demo data generated successfully."
    And I should see "events: 6"
    When I click on "Generate demo data" "link"
    Then I should see "Demo data was not generated."
    When I visit "/mod/bookit/admin/rooms.php"
    Then I should see "DEMO Lecture Hall A"
    And I should see "DEMO Seminar Room B"

  Scenario: Admin sees the scope of a reset, can cancel it and then confirm it
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Generate demo data" "link"
    When I click on "Reset demo data" "link"
    Then I should see "6 events, 2 rooms besides the standard room"
    And I should see "any other BookIt plugin content you entered yourself"
    When I press "Cancel"
    Then I should see "Demo reset was cancelled. No data was changed."
    When I visit "/mod/bookit/admin/rooms.php"
    Then I should see "DEMO Lecture Hall A"
    When I visit "/mod/bookit/admin/demo_reset_run.php"
    And I press "Continue"
    Then I should see "Demo data was reset to the calendar baseline."
    When I visit "/mod/bookit/admin/rooms.php"
    Then I should not see "DEMO Lecture Hall A"
    And I should not see "DEMO Seminar Room B"

  Scenario: Installing BookIt roles creates no demo checklist
    Given the following config values are set as admin:
      | checklistenabled | 1 | mod_bookit |
    And I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    When I click on "Install BookIt roles" "link"
    Then I should see "Install BookIt roles completed successfully."
    When I visit "/mod/bookit/admin/master_checklist.php"
    Then I should not see "University Examination Administration Checklist"
    And I should not see "DEMO Before the exam"
