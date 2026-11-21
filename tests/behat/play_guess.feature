@local @local_playergames
Feature: Play the PlayerGuess daily game
  In order to practise the terms of a cartridge
  As a student
  I need to guess the word of the day from its definition

  Background:
    Given the following "local_playergames > seasons" exist:
      | name         |
      | Behat season |
    And the following "local_playergames > cartridges" exist:
      | name   |
      | Fruits |
    And the following "local_playergames > concepts" exist:
      | cartridge | term  | definition     |
      | Fruits    | grape | A purple fruit |
    And the PlayerGames words of the day are drawn
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And I log in as "student1"

  @javascript
  Scenario: Guessing the word wins the game
    Given I am on the "local_playergames > PlayerGuess" page
    When I type "grape" on the PlayerGuess keyboard
    Then I should see "Correct!"
    And I should see "XP earned:"

  @javascript
  Scenario: A wrong guess uses an attempt and the game goes on
    Given I am on the "local_playergames > PlayerGuess" page
    When I type "lemon" on the PlayerGuess keyboard
    Then I should see "Attempt 2 of 6"
    And I should not see "Correct!"

  @javascript
  Scenario: Running out of attempts ends the game and shows the word
    Given the following config values are set as admin:
      | guess_max_attempts | 1 | local_playergames |
    And I am on the "local_playergames > PlayerGuess" page
    When I type "lemon" on the PlayerGuess keyboard
    Then I should see "Out of attempts"
    And I should see "You ran out of attempts"

  @javascript
  Scenario: The game of the day is played only once
    Given I am on the "local_playergames > PlayerGuess" page
    And I type "grape" on the PlayerGuess keyboard
    And I should see "Correct!"
    When I am on the "local_playergames > PlayerGuess" page
    Then I should see "You have already played PlayerGuess today. Come back tomorrow!"
