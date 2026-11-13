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
 * Library functions for local_playergames.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a "Gamification" entry to the user's own preferences page.
 *
 * @param navigation_node $usersetting The user settings navigation node.
 * @param stdClass $user The user whose preferences are being shown.
 * @param context_user $usercontext The user context.
 * @param stdClass $course The current course.
 * @param context_course $coursecontext The current course context.
 */
function local_playergames_extend_navigation_user_settings(
    navigation_node $usersetting,
    stdClass $user,
    context_user $usercontext,
    stdClass $course,
    context_course $coursecontext
): void {
    global $USER;

    // Only expose this on the user's own preferences page.
    if ($user->id != $USER->id) {
        return;
    }

    if (!has_capability('local/playergames:viewhub', context_system::instance())) {
        return;
    }

    $usersetting->add(
        get_string('gamification_prefnav', 'local_playergames'),
        new moodle_url('/local/playergames/gamification_preferences.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_playergames_gamification'
    );
}

/**
 * Serves the files embedded in question bank questions shown by the quiz games.
 *
 * @param stdClass|null $course Course of the context, when it has one.
 * @param stdClass|null $cm Course module of the context, when it has one.
 * @param context $context The context in the URL: the one of the question's bank.
 * @param string $filearea File area, one of the bank_file constants.
 * @param array $args The rest of the URL: item id, then the path of the file.
 * @param bool $forcedownload Whether to force a download.
 * @param array $options Options for send_stored_file().
 * @return bool False when there is nothing to serve.
 */
function local_playergames_pluginfile(
    ?stdClass $course,
    ?stdClass $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    return \local_playergames\local\bank_file::serve($context, $filearea, $args, $forcedownload, $options);
}
