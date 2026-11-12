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
 * Concept cartridge importer.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

/**
 * Reads fields of a decoded JSON payload (an uploaded cartridge, a model's answer, a posted form).
 *
 * Such a payload has whatever shape its author gave it: a field expected to hold text may hold a list or
 * an object. clean_param() throws on an array, trim() fails with a TypeError, and a (string) cast turns it
 * into the word "Array". These readers treat anything that is not a string, number or boolean as missing.
 */
class payload {
    /**
     * Returns a field as plain text, or '' when it is not a scalar.
     *
     * @param mixed $value Field of the decoded payload.
     * @return string
     */
    public static function text(mixed $value): string {
        return is_scalar($value) ? trim(clean_param((string) $value, PARAM_TEXT)) : '';
    }

    /**
     * Returns a field as a list of values: the array itself, a single scalar as a one entry list, or nothing.
     *
     * @param mixed $value Field of the decoded payload.
     * @return array
     */
    public static function values(mixed $value): array {
        if (is_array($value)) {
            return $value;
        }
        return is_scalar($value) && $value !== '' ? [$value] : [];
    }

    /**
     * Returns a field as a list of plain texts with the empty ones left out.
     *
     * A quiz question needs four real distractors: a blank one would reach the player as an empty button.
     *
     * @param mixed $value Field of the decoded payload: a list of texts, or a single text.
     * @return string[] The non-empty texts, in order, re-indexed from zero.
     */
    public static function texts(mixed $value): array {
        $texts = [];
        foreach (self::values($value) as $item) {
            $text = self::text($item);
            if ($text !== '') {
                $texts[] = $text;
            }
        }
        return $texts;
    }

    /**
     * Returns a field as an integer clamped to a range, or $default when it is not a number.
     *
     * @param mixed $value Field of the decoded payload.
     * @param int $min Lowest value.
     * @param int $max Highest value.
     * @param int $default Returned when the value is missing or not numeric.
     * @return int
     */
    public static function number(mixed $value, int $min, int $max, int $default): int {
        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }
}
