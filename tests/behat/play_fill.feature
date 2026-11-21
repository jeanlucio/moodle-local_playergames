@local @local_playergames
Feature: Play the PlayerFill daily game
  In order to practise the terms of a cartridge
  As a student
  I need to fill in the words of the day from their definitions

  Background:
    Given the following config values are set as admin:
      | fill_num_words | 4 | local_playergames |
    And the following "local_playergames > seasons" exist:
      | name         |
      | Behat season |
    And the following "local_playergames > cartridges" exist:
      | name   |
      | Fruits |
    # Each term has a letter no other term has: a word whose letters the solved words all reveal solves itself.
    And the following "local_playergames > concepts" exist:
      | cartridge | term  | definition     |
      | Fruits    | grape | A purple fruit |
      | Fruits    | lemon | A sour fruit   |
      | Fruits    | kiwi  | A fuzzy fruit  |
      | Fruits    | date  | A sweet fruit  |
    And the PlayerGames words of the day are drawn
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And I log in as "student1"
    And I am on the "local_playergames > PlayerFill" page

  @javascript
  Scenario: Solving every word wins the game
    When I answer "grape" for the PlayerFill clue "A purple fruit"
    And I answer "lemon" for the PlayerFill clue "A sour fruit"
    And I answer "kiwi" for the PlayerFill clue "A fuzzy fruit"
    And I answer "date" for the PlayerFill clue "A sweet fruit"
    Then I should see "Solved!"
    And I should see "XP earned:"

  @javascript
  Scenario: A wrong guess says how many attempts the word has left
    When I answer "peach" for the PlayerFill clue "A purple fruit"
    Then I should see "Not quite. Attempts left for this word: 3"
    And I should not see "Solved!"
