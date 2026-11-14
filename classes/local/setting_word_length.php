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
 * Admin setting for one end of a term length range.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use admin_setting_configtext;

/**
 * The minimum or the maximum length of the terms PlayerGuess and PlayerFill may draw.
 *
 * A plain integer setting accepted a minimum above the maximum, or zero or less, and the daily task
 * then found no term that fits and assigned nothing, every day, with no message besides a line in the
 * cron log. This setting refuses those values when the page is saved. The two ends are saved by the
 * same form, so the other end is read from what was submitted, and from what is stored when the form
 * did not carry it.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_word_length extends admin_setting_configtext {
    /** @var string The end this setting is: 'min' or 'max'. */
    private string $end;

    /** @var string Name of the setting that holds the other end. */
    private string $othername;

    /** @var int Default of the other end, for when nothing was submitted or stored. */
    private int $otherdefault;

    /**
     * Constructor.
     *
     * @param string $name Setting name, as plugin/name.
     * @param string $visiblename Label.
     * @param string $description Help text.
     * @param int $default Default value.
     * @param string $end 'min' or 'max'.
     * @param string $othername Name (without the plugin) of the setting that holds the other end.
     * @param int $otherdefault Default of the other end.
     */
    public function __construct(
        string $name,
        string $visiblename,
        string $description,
        int $default,
        string $end,
        string $othername,
        int $otherdefault
    ) {
        parent::__construct($name, $visiblename, $description, (string) $default, PARAM_INT);
        $this->end          = $end;
        $this->othername    = $othername;
        $this->otherdefault = $otherdefault;
    }

    /**
     * Refuses a value that is not a positive whole number, or that crosses the other end.
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
        if ($value < 1) {
            return get_string('settings_length_invalid', 'local_playergames');
        }

        $other = $this->other_value();
        $crosses = $this->end === 'min' ? $value > $other : $value < $other;
        if ($crosses) {
            return get_string('settings_length_range_invalid', 'local_playergames');
        }
        return true;
    }

    /**
     * The other end as it will be after this save.
     *
     * @return int
     */
    private function other_value(): int {
        $submitted = optional_param('s_' . $this->plugin . '_' . $this->othername, '', PARAM_RAW_TRIMMED);
        if ($submitted !== '' && is_numeric($submitted)) {
            return (int) $submitted;
        }
        return (int) get_config($this->plugin, $this->othername) ?: $this->otherdefault;
    }
}
