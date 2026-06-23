@local @local_playergames
Feature: Play the PlayerQuiz daily game
  In order to learn from the daily quiz
  As a student
  I need to answer questions, see feedback and the result without the page auto-advancing

  Background:
    Given the PlayerQuiz has a playable question
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And I log in as "student1"
    And I am playing the PlayerQuiz

  @javascript
  Scenario: A wrong answer reveals the correct option and feedback and then waits
    When I click on ".pg-quiz-answer[data-correct='0']" "css_element"
    Then ".pg-quiz-answer[data-correct='1'].btn-success" "css_element" should exist
    And I should see "Behat feedback after answering."
    And I should see "Continue"

  @javascript
  Scenario: A correct answer shows the result as a modal
    When I click on ".pg-quiz-answer[data-correct='1']" "css_element"
    Then I should see "Quiz completed!"
    And I should see "XP earned:"
