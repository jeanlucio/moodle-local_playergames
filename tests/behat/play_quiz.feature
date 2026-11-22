@local @local_playergames
Feature: Play the PlayerQuiz daily game
  In order to learn from the daily quiz
  As a student
  I need to answer questions, see feedback and the result without the page auto-advancing

  Background:
    Given the following "local_playergames > seasons" exist:
      | name         |
      | Behat season |
    And the following "local_playergames > cartridges" exist:
      | name       | type |
      | Behat quiz | quiz |
    And the following "local_playergames > quiz questions" exist:
      | cartridge  | questiontext    | correct        | feedback                        |
      | Behat quiz | Behat question? | Correct option | Behat feedback after answering. |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And I log in as "student1"
    And I am on the "local_playergames > PlayerQuiz" page

  @javascript
  Scenario: A wrong answer reveals the correct option and feedback and then waits
    When I click on "Wrong 1" "button"
    Then ".pg-quiz-answer.btn-success" "css_element" should exist
    And I should see "Behat feedback after answering."
    And I should see "Continue"

  @javascript
  Scenario: A correct answer shows the result as a modal
    When I click on "Correct option" "button"
    Then I should see "PlayerQuiz completed!"
    And I should see "XP earned:"

  @javascript
  Scenario: A question asked again does not show the verdict of the last answer
    When I click on "Wrong 1" "button"
    And I click on "Continue" "button"
    Then ".pg-quiz-mark" "css_element" should not exist
    And ".pg-quiz-answer.btn-danger" "css_element" should not exist
    And the "Correct option" "button" should be enabled
