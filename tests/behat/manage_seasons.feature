@local @local_playergames
Feature: Manage the seasons of PlayerGames
  In order to run the games in seasons
  As an administrator
  I need to create a season and start it

  Background:
    Given I log in as "admin"

  Scenario: A new season is listed after it is saved
    Given I am on the "local_playergames > Seasons" page
    When I set the following fields to these values:
      | Season name | Spring                 |
      | Start date  | ##+1 day##%Y-%m-%d##   |
      | End date    | ##+30 days##%Y-%m-%d## |
    And I press "Save changes"
    Then I should see "Season created."
    And I should see "Spring"

  Scenario: Activating a season makes it the active one
    Given the following "local_playergames > seasons" exist:
      | name   | active |
      | Spring | 0      |
    And I am on the "local_playergames > Seasons" page
    When I press "Activate"
    Then I should see "Season activated."
    And I should see "Active" in the "Spring" "table_row"

  Scenario: A game whose cartridges are all unchecked is switched off
    Given the following "local_playergames > seasons" exist:
      | name   |
      | Spring |
    And the following "local_playergames > cartridges" exist:
      | name   |
      | Fruits |
    And I am on the "local_playergames > Season games" page
    And the field "Fruits" in the "PlayerGuess" "fieldset" matches value "1"
    When I set the field "Fruits" in the "PlayerGuess" "fieldset" to ""
    And I press "Save changes"
    Then I should see "Game configuration saved."
    And the field "Fruits" in the "PlayerGuess" "fieldset" matches value ""
    And the field "pg-enabled-guess" matches value ""
    And the field "Fruits" in the "PlayerFill" "fieldset" matches value "1"
    And the field "pg-enabled-fill" matches value "1"

  @javascript
  Scenario: The switch of a game follows its cartridges
    Given the following "local_playergames > seasons" exist:
      | name   |
      | Spring |
    And the following "local_playergames > cartridges" exist:
      | name   |
      | Fruits |
    And I am on the "local_playergames > Season games" page
    When I click on "Fruits" "checkbox" in the "PlayerGuess" "fieldset"
    Then the field "pg-enabled-guess" matches value ""
    And I should see "No cartridge checked" in the "PlayerGuess" "fieldset"
    And I click on "pg-enabled-guess" "checkbox"
    And the field "Fruits" in the "PlayerGuess" "fieldset" matches value "1"
    And I should not see "No cartridge checked" in the "PlayerGuess" "fieldset"
