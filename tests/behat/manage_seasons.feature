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
