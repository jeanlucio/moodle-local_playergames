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
 * Admin setting for the minimum level of an avatar tier.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\local;

use admin_setting_configtext;
use local_playergames\hub\level_manager;

/**
 * The minimum level that unlocks an avatar tier, with a warning when no player can reach it.
 *
 * A level above the top of the level ladder leaves that tier's avatars locked for everyone. That
 * happens by itself when an admin sets a level and later shortens the ladder, so the warning is shown
 * whenever the page is displayed instead of refusing the value when it is saved. Zero is valid: it
 * spreads the tiers across the ladder.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_avatar_tier extends admin_setting_configtext {
    /** @var int The tier this setting unlocks. */
    private int $tier;

    /**
     * Constructor.
     *
     * @param int $tier Tier number.
     */
    public function __construct(int $tier) {
        $this->tier = $tier;
        parent::__construct(
            'local_playergames/avatar_tier' . $tier . '_level',
            get_string('settings_avatar_tier_level', 'local_playergames', $tier),
            get_string('settings_avatar_tier_level_desc', 'local_playergames', $tier),
            '0',
            PARAM_INT
        );
    }

    /**
     * Renders the setting, adding the warning under the help text when the level is out of reach.
     *
     * @param mixed $data The stored value.
     * @param string $query Search text the admin typed.
     * @return string
     */
    public function output_html($data, $query = ''): string {
        global $OUTPUT;

        $original = $this->description;
        $level    = (int) $data;
        $maxlevel = level_manager::max_level();
        if ($level > $maxlevel) {
            $a = (object) ['tier' => $this->tier, 'level' => $level, 'max' => $maxlevel];
            $this->description .= $OUTPUT->notification(
                get_string('settings_avatar_tier_unreachable', 'local_playergames', $a),
                \core\output\notification::NOTIFY_WARNING,
                false
            );
        }
        try {
            return parent::output_html($data, $query);
        } finally {
            $this->description = $original;
        }
    }
}
