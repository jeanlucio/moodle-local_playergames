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
 * Quiz question DTO.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\games;

/**
 * Represents a single quiz question with its answer options.
 *
 * A question reaches the game as a cartridge question, whatever the way the cartridge was made.
 */
class quiz_question {
    /** @var string Origin of the question, 'cartridge'; it is kept with the id to tell what was seen today. */
    public string $source = '';

    /** @var int Primary key of the source record. */
    public int $sourceid = 0;

    /** @var string Question stem text. */
    public string $questiontext = '';

    /** @var int Difficulty 1–5 . */
    public int $difficulty = 0;

    /** @var int|null Cartridge category id, or null when uncategorised. */
    public ?int $categoryid = null;

    /** @var string|null Optional general feedback shown after the question is answered. */
    public ?string $generalfeedback = null;

    /** @var quiz_answer[] Five answer options, pre-shuffled. */
    public array $answers = [];
}
