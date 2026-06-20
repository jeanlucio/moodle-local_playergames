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
 * Per-user gamification preference page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/playergames:viewhub', $context);

$url = new moodle_url('/local/playergames/gamification_preferences.php');
$prefsurl = new moodle_url('/user/preferences.php');

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('gamification_pref_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('gamification_pref_pagetitle', 'local_playergames'));
$PAGE->navbar->add(get_string('preferences'), $prefsurl);
$PAGE->navbar->add(get_string('gamification_pref_pagetitle', 'local_playergames'));

$form = new \local_playergames\form\gamification_preferences_form($url->out(false));

if ($form->is_cancelled()) {
    redirect($prefsurl);
} else if ($data = $form->get_data()) {
    \local_playergames\local\preferences::set($USER->id, (bool) $data->enabled);
    redirect($url, get_string('gamification_pref_saved', 'local_playergames'));
}

$form->set_data([
    'enabled' => \local_playergames\local\preferences::is_gamification_enabled($USER->id) ? 1 : 0,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gamification_pref_pagetitle', 'local_playergames'));
$form->display();
echo $OUTPUT->footer();
