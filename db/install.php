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
 * Post-install hook for local_playergames.
 *
 * No automatic season is created here. The administrator creates the first
 * season via the Staff HUD admin interface (Fase 4), where a pre-filled form
 * with default dates and XP caps is shown. This avoids creating a "phantom"
 * season whose dates do not match the real academic calendar.
 *
 * Auto-renewal (configured via settings in Fase 2) is handled by the
 * close_expired_seasons scheduled task: when it closes a season and
 * autorenew_seasons is enabled, it automatically creates the next one with
 * the duration defined by season_duration_months.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post-install hook — no-op; season setup is done by the administrator via UI.
 *
 * @return bool
 */
function xmldb_local_playergames_install(): bool {
    return true;
}
