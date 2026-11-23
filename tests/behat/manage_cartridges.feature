@local @local_playergames
Feature: Manage the cartridges of PlayerGames
  In order to give the games their content
  As an administrator
  I need to add concepts to a cartridge, switch cartridges off and delete them

  Background:
    Given the following "local_playergames > seasons" exist:
      | name         |
      | Behat season |
    And the following "local_playergames > cartridges" exist:
      | name   |
      | Fruits |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And I log in as "admin"

  Scenario: A concept added during the day gives PlayerGuess its word at once
    Given I am on the "Fruits" "local_playergames > Cartridge editor" page
    When I set the following fields to these values:
      | Term       | grape          |
      | Definition | A purple fruit |
    And I press "Save"
    And I log out
    And I log in as "student1"
    And I am on the "local_playergames > PlayerGuess" page
    Then I should not see "There is no word for today"
    And "#pg-guess-board" "css_element" should exist

  Scenario: A cartridge switched off is shown as inactive
    Given I am on the "local_playergames > Cartridges" page
    When I click on "Deactivate" "button"
    Then I should see "Inactive"
    And "Activate" "button" should exist

  @javascript
  Scenario: Cancelling the deletion keeps the cartridge
    Given I am on the "local_playergames > Cartridges" page
    When I click on "Delete" "button"
    And I click on "Cancel" "button" in the "Confirm" "dialogue"
    Then I should see "Fruits"
    And I should not see "Cartridge deleted."

  @javascript
  Scenario: Confirming the deletion removes the cartridge
    Given I am on the "local_playergames > Cartridges" page
    When I click on "Delete" "button"
    And I should see "Delete this cartridge and all its concepts? This cannot be undone." in the "Confirm" "dialogue"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should see "Cartridge deleted."
    And I should not see "Fruits"

  @javascript @_file_upload
  Scenario: An imported concept cartridge is listed and gives PlayerGuess its word at once
    Given I am on the "local_playergames > Cartridge import" page
    When I choose the PlayerGames cartridge file "local/playergames/tests/fixtures/cartridge_fruits.json" to import
    And I press "Import JSON"
    And I should see "Imported 2 concepts."
    And I log out
    And I log in as "student1"
    And I am on the "local_playergames > PlayerGuess" page
    Then I should not see "There is no word for today"
    And "#pg-guess-board" "css_element" should exist

  @javascript @_file_upload
  Scenario: A file that is not valid JSON is refused
    Given I am on the "local_playergames > Cartridge import" page
    When I choose the PlayerGames cartridge file "local/playergames/tests/fixtures/cartridge_broken.json" to import
    And I press "Import JSON"
    Then I should see "The uploaded file does not contain valid JSON."
    And I should not see "Imported"
