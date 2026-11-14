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
 * Admin setting for a whole number within a range.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use admin_setting_configtext;

/**
 * A whole number setting that refuses a value outside its range.
 *
 * A plain integer setting only checks that the text is an integer, so a negative value was saved without
 * a word and then broke whatever read it: a negative season length created seasons that were over before
 * they began, and a negative amount of XP took XP away. The range is checked when the page is saved.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_int_range extends admin_setting_configtext {
    /** @var int Smallest value accepted. */
    private int $min;

    /** @var int|null Largest value accepted, or null for no upper limit. */
    private ?int $max;

    /**
     * Constructor.
     *
     * @param string $name Setting name, as plugin/name.
     * @param string $visiblename Label.
     * @param string $description Help text.
     * @param int $default Default value.
     * @param int $min Smallest value accepted.
     * @param int|null $max Largest value accepted, or null for no upper limit.
     */
    public function __construct(
        string $name,
        string $visiblename,
        string $description,
        int $default,
        int $min,
        ?int $max = null
    ) {
        parent::__construct($name, $visiblename, $description, (string) $default, PARAM_INT);
        $this->min = $min;
        $this->max = $max;
    }

    /**
     * Refuses a value that is not a whole number inside the range.
     *
     * @param mixed $data The submitted value.
     * @return bool|string True when valid, otherwise the error message.
     */
    public function validate($data): bool|string {
        $result = parent::validate($data);
        if ($result !== true) {
            return $result;
        }

        $value = (int) $data;
        if ($value >= $this->min && ($this->max === null || $value <= $this->max)) {
            return true;
        }
        if ($this->max === null) {
            return get_string('settings_int_min', 'local_playergames', $this->min);
        }
        return get_string(
            'settings_int_between',
            'local_playergames',
            (object) ['min' => $this->min, 'max' => $this->max]
        );
    }
}
