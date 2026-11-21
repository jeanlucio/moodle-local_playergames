<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Behat step definitions for local_playergames.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Pages and steps of PlayerGames in acceptance tests; the data comes from the plugin's generator.
 *
 * @package    local_playergames
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_playergames extends behat_base {
    /**
     * Converts page names to URLs for steps like 'I am on the "local_playergames > PlayerGuess" page'.
     *
     * Recognised page names: Hub, PlayerGuess, PlayerFill, PlayerQuiz, Cartridges, Cartridge import, Seasons.
     *
     * @param string $page The page name.
     * @return moodle_url The URL of the page.
     * @throws Exception When the page is not one of those.
     */
    protected function resolve_page_url(string $page): moodle_url {
        $scripts = [
            'hub' => '/local/playergames/hub.php',
            'playerguess' => '/local/playergames/play_guess.php',
            'playerfill' => '/local/playergames/play_fill.php',
            'playerquiz' => '/local/playergames/play_quiz.php',
            'cartridges' => '/local/playergames/cartridge.php',
            'cartridge import' => '/local/playergames/cartridge.php?tab=import',
            'seasons' => '/local/playergames/manage_seasons.php',
        ];
        $key = strtolower($page);
        if (!isset($scripts[$key])) {
            throw new Exception('Unrecognised PlayerGames page "' . $page . '".');
        }
        return new moodle_url($scripts[$key]);
    }

    /**
     * Converts page names to URLs for steps like 'I am on the "Fruits" "local_playergames > Cartridge editor" page'.
     *
     * Recognised page types: Cartridge editor, whose identifier is the name of a cartridge.
     *
     * @param string $type The page type.
     * @param string $identifier The cartridge name.
     * @return moodle_url The URL of the page.
     * @throws Exception When the type is unknown or there is no such cartridge.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;
        if (strtolower($type) !== 'cartridge editor') {
            throw new Exception('Unrecognised PlayerGames page type "' . $type . '".');
        }
        $id = $DB->get_field('local_playergames_cartridges', 'id', ['name' => $identifier]);
        if (!$id) {
            throw new Exception('There is no PlayerGames cartridge named "' . $identifier . '".');
        }
        return new moodle_url('/local/playergames/cartridge.php', ['tab' => 'editor', 'cartridgeid' => $id]);
    }

    /**
     * Draws the words of the day, as the task of midnight does, from the concepts that exist now.
     *
     * @Given the PlayerGames words of the day are drawn
     */
    public function the_playergames_words_of_the_day_are_drawn(): void {
        \local_playergames\task\assign_daily_games::draw_now();
    }

    /**
     * Chooses a cartridge file in the JSON import form of the cartridge page.
     *
     * The form takes a plain file field, which no core step fills; the file picker steps of core attach the
     * file the same way.
     *
     * @When I choose the PlayerGames cartridge file :filepath to import
     * @param string $filepath The file, relative to the root of Moodle.
     */
    public function i_choose_the_playergames_cartridge_file_to_import(string $filepath): void {
        global $CFG;
        $field = $this->find_field(get_string('cartridge_import_file', 'local_playergames'));
        $field->attachFile($CFG->dirroot . '/' . $filepath);
    }

    /**
     * Types a word on the on-screen keyboard of PlayerGuess and sends it.
     *
     * @When I type :word on the PlayerGuess keyboard
     * @param string $word The word, in letters only.
     */
    public function i_type_on_the_playerguess_keyboard(string $word): void {
        foreach (preg_split('//u', core_text::strtoupper($word), -1, PREG_SPLIT_NO_EMPTY) as $letter) {
            $this->execute('behat_general::i_click_on', [".pg-guess-key[data-key=\"{$letter}\"]", 'css_element']);
        }
        $this->execute('behat_general::i_click_on', ['.pg-guess-key[data-key="ENTER"]', 'css_element']);
    }

    /**
     * Answers the word of PlayerFill whose clue is the given definition.
     *
     * @When I answer :word for the PlayerFill clue :definition
     * @param string $word The guess.
     * @param string $definition The clue of the word, as the board shows it.
     * @throws ExpectationException When no word of the board has that clue.
     */
    public function i_answer_for_the_playerfill_clue(string $word, string $definition): void {
        $conceptid = null;
        foreach ($this->find_all('css', '.pg-fill-row') as $row) {
            if (trim($row->find('css', '.pg-fill-definition')->getText()) === $definition) {
                $conceptid = $row->getAttribute('data-conceptid');
                break;
            }
        }
        if ($conceptid === null) {
            throw new ExpectationException('No PlayerFill word has the clue "' . $definition . '".', $this->getSession());
        }
        $this->execute('behat_forms::i_set_the_field_to', ["pg-fill-input-{$conceptid}", $word]);
        $this->execute('behat_general::i_click_on_in_the', [
            '.pg-fill-submit', 'css_element', ".pg-fill-row[data-conceptid=\"{$conceptid}\"]", 'css_element',
        ]);
    }
}
