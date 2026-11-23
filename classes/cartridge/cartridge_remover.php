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

use local_playergames\hub\season_manager;
use local_playergames\local\day;

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
    /** @var string[] Games of a season not closed yet that the last deletion switched off. */
    private array $switchedoff = [];

    /**
     * The games the last deletion switched off, because they used no other cartridge.
     *
     * @return string[] Game types.
     */
    public function switched_off_games(): array {
        return array_keys($this->switchedoff);
    }

    /**
     * Deletes a cartridge and everything that belongs to or points at it, in one transaction.
     *
     * @param int $cartridgeid The cartridge.
     * @return bool Whether an assignment of today was removed, so the caller draws today's words again.
     */
    public function delete(int $cartridgeid): bool {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $conceptids = $DB->get_fieldset_select(
            'local_playergames_concepts',
            'id',
            'cartridgeid = :cid',
            ['cid' => $cartridgeid]
        );
        $removedtoday = $this->drop_assignments($conceptids);
        $DB->delete_records('local_playergames_concepts', ['cartridgeid' => $cartridgeid]);

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

        return $removedtoday;
    }

    /**
     * Removes the daily assignments that point at the concepts.
     *
     * An assignment left behind names a concept that is gone: PlayerGuess answers "unavailable" to every
     * guess of the day, and PlayerFill rebuilds each player's round. When a concept was one of today's
     * words, the whole set of that game for today is dropped so it can be drawn again.
     *
     * @param int[] $conceptids The concepts that are going away.
     * @param bool $todayonly Leave the assignments of earlier days, for concepts that stay.
     * @return bool Whether an assignment of today was removed.
     */
    private function drop_assignments(array $conceptids, bool $todayonly = false): bool {
        global $DB;

        if ($conceptids === []) {
            return false;
        }

        $today = day::today();
        [$insql, $inparams] = $DB->get_in_or_equal($conceptids, SQL_PARAMS_NAMED);
        $gametypes = $DB->get_fieldset_select(
            'local_playergames_daily_assignments',
            'DISTINCT gametype',
            "gamedate = :today AND conceptid $insql",
            ['today' => $today] + $inparams
        );
        foreach ($gametypes as $gametype) {
            $DB->delete_records('local_playergames_daily_assignments', ['gamedate' => $today, 'gametype' => $gametype]);
        }
        if (!$todayonly) {
            $DB->delete_records_select('local_playergames_daily_assignments', "conceptid $insql", $inparams);
        }

        return $gametypes !== [];
    }

    /**
     * Takes the cartridge out of the lists the seasons keep for their games.
     *
     * A game left with no cartridge is switched off, as it is when the administrator unticks all of them: the other
     * cartridges are the ones that were left out on purpose.
     *
     * @param int $cartridgeid The cartridge.
     * @return void
     */
    private function forget_in_season_games(int $cartridgeid): void {
        global $DB;

        $this->switchedoff = [];
        $rows = $DB->get_records_sql(
            "SELECT g.id, g.gametype, g.enabled, g.cartridgeids, s.status
               FROM {local_playergames_season_games} g
          LEFT JOIN {local_playergames_seasons} s ON s.id = g.seasonid
              WHERE g.cartridgeids IS NOT NULL"
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
            $DB->update_record('local_playergames_season_games', (object) [
                'id' => $row->id,
                'cartridgeids' => json_encode($remaining),
                'enabled' => $remaining === [] ? 0 : $row->enabled,
            ]);
            if ($remaining === [] && $row->enabled && $row->status !== season_manager::STATUS_CLOSED) {
                $this->switchedoff[$row->gametype] = true;
            }
        }
    }

    /**
     * Takes the cartridge's words out of today's games, for a cartridge that was switched off.
     *
     * The games read the word of the day straight from the concept, so a cartridge taken out of the library
     * would go on serving it until midnight.
     *
     * @param int $cartridgeid The cartridge.
     * @return bool Whether an assignment of today was removed, so the caller draws today's words again.
     */
    public function release_todays_words(int $cartridgeid): bool {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $removedtoday = $this->drop_assignments($DB->get_fieldset_select(
            'local_playergames_concepts',
            'id',
            'cartridgeid = :cid',
            ['cid' => $cartridgeid]
        ), true);
        $transaction->allow_commit();

        return $removedtoday;
    }

    /**
     * Takes one concept out of today's games, for a concept whose term was changed.
     *
     * Guesses already made, and the puzzle already built, are against the old word.
     *
     * @param int $conceptid The concept.
     * @return bool Whether an assignment of today was removed, so the caller draws today's words again.
     */
    public function release_todays_word(int $conceptid): bool {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $removedtoday = $this->drop_assignments([$conceptid], true);
        $transaction->allow_commit();

        return $removedtoday;
    }

    /**
     * Deletes one concept and the daily assignments that point at it, in one transaction.
     *
     * @param int $conceptid The concept.
     * @param int $cartridgeid The cartridge the request names; a concept of another cartridge is left alone.
     * @return bool Whether an assignment of today was removed, so the caller draws today's words again.
     */
    public function delete_concept(int $conceptid, int $cartridgeid): bool {
        global $DB;

        if (!$DB->record_exists('local_playergames_concepts', ['id' => $conceptid, 'cartridgeid' => $cartridgeid])) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();
        $removedtoday = $this->drop_assignments([$conceptid]);
        $DB->delete_records('local_playergames_concepts', ['id' => $conceptid, 'cartridgeid' => $cartridgeid]);
        $transaction->allow_commit();

        return $removedtoday;
    }
}
