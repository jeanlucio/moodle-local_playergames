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
 * Removal of a cartridge with everything that points at it.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Deletes a cartridge, its content and the references other tables hold to it.
 *
 * A season can restrict a game to some cartridges, and the daily task keeps the concepts it assigned. Both
 * name the cartridge or its concepts by id, so leaving them behind left a game with a list of cartridges
 * that no longer exist: no content, and a settings page that showed every cartridge unticked as if the
 * game used all of them.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cartridge_remover {
    /**
     * Deletes a cartridge and everything that belongs to or points at it, in one transaction.
     *
     * @param int $cartridgeid The cartridge.
     * @return void
     */
    public function delete(int $cartridgeid): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $conceptids = $DB->get_fieldset_select(
            'local_playergames_concepts',
            'id',
            'cartridgeid = :cid',
            ['cid' => $cartridgeid]
        );
        if (!empty($conceptids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($conceptids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_playergames_daily_assignments', "conceptid $insql", $inparams);
        }
        $DB->delete_records('local_playergames_concepts', ['cartridgeid' => $cartridgeid]);
        $DB->delete_records('local_playergames_categories', ['cartridgeid' => $cartridgeid]);

        $questionids = $DB->get_fieldset_select(
            'local_playergames_concept_questions',
            'id',
            'cartridgeid = :cid',
            ['cid' => $cartridgeid]
        );
        if (!empty($questionids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_playergames_concept_answers', "questionid $insql", $inparams);
            $DB->delete_records_select('local_playergames_concept_questions', "id $insql", $inparams);
        }

        $this->forget_in_season_games($cartridgeid);
        $DB->delete_records('local_playergames_cartridges', ['id' => $cartridgeid]);

        $transaction->allow_commit();
    }

    /**
     * Takes the cartridge out of the lists the seasons keep for their games.
     *
     * A game left with no cartridge goes back to null, which means every active cartridge, the same as a game
     * nobody restricted.
     *
     * @param int $cartridgeid The cartridge.
     * @return void
     */
    private function forget_in_season_games(int $cartridgeid): void {
        global $DB;

        $rows = $DB->get_records_select(
            'local_playergames_season_games',
            'cartridgeids IS NOT NULL',
            null,
            '',
            'id, cartridgeids'
        );
        foreach ($rows as $row) {
            $kept = json_decode((string) $row->cartridgeids, true);
            if (!is_array($kept) || !in_array($cartridgeid, array_map('intval', $kept), true)) {
                continue;
            }
            $remaining = array_values(array_filter(
                array_map('intval', $kept),
                static fn(int $id): bool => $id !== $cartridgeid
            ));
            $DB->set_field(
                'local_playergames_season_games',
                'cartridgeids',
                $remaining === [] ? null : json_encode($remaining),
                ['id' => $row->id]
            );
        }
    }
}
