@mod @mod_bookit @javascript @_file_upload
Feature: Import a master checklist
  In order to trust an imported checklist
  As an administrator
  I need a lasting message and no Observer assignment

  Background:
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I check "Enable checklist module"
    And I press "Save changes"
    And the Bookit default roles are imported
    And the Bookit default checklist data exists
    And I log out

  Scenario: A valid file with Observer shows a lasting success message
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Master checklist" "link"
    And I click on "import-master-checklist-button" "button"
    And I upload "mod/bookit/tests/fixtures/checklist_import_mixed.csv" file to "CSV file" filemanager
    And I click on "Import" "button" in the ".modal-dialog" "css_element"
    Then I should see "The checklist was imported successfully."
    And I reload the page
    And I should see "The checklist was imported successfully."
    And I should see "Mixed item"

  Scenario: A broken file shows a lasting error and imports nothing
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Master checklist" "link"
    And I click on "import-master-checklist-button" "button"
    And I upload "mod/bookit/tests/fixtures/checklist_import_bad.csv" file to "CSV file" filemanager
    And I click on "Import" "button" in the ".modal-dialog" "css_element"
    Then I should see "could not be read"
    And I reload the page
    And I should see "could not be read"

  Scenario: A file that only names Observer is rejected and names the line
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Plugins > Activity modules > BookIt" in site administration
    And I click on "Master checklist" "link"
    And I click on "import-master-checklist-button" "button"
    And I upload "mod/bookit/tests/fixtures/checklist_import_observer.csv" file to "CSV file" filemanager
    And I click on "Import" "button" in the ".modal-dialog" "css_element"
    Then I should see "Line 3"
    And I should not see "Observer item"
