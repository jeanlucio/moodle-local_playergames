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
 * Personal AI API key management page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/playergames:manageownkeys', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/mykeys.php'));
$PAGE->set_title(get_string('mykeys_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('mykeys_pagetitle', 'local_playergames'));
$PAGE->requires->js_call_amd('local_playergames/mykeys', 'init');

// Handle POST — save all three keys at once.
if (data_submitted() && confirm_sesskey()) {
    $geminikey = optional_param('gemini_key', '', PARAM_RAW_TRIMMED);
    $groqkey   = optional_param('groq_key', '', PARAM_RAW_TRIMMED);
    $openaikey = optional_param('openai_key', '', PARAM_RAW_TRIMMED);

    \local_playergames\api_key_helper::save_user_key(
        \local_playergames\api_key_helper::PROVIDER_GEMINI,
        $geminikey
    );
    \local_playergames\api_key_helper::save_user_key(
        \local_playergames\api_key_helper::PROVIDER_GROQ,
        $groqkey
    );
    \local_playergames\api_key_helper::save_user_key(
        \local_playergames\api_key_helper::PROVIDER_OPENAI,
        $openaikey
    );

    \core\notification::success(get_string('apikey_saved', 'local_playergames'));
    redirect(new moodle_url('/local/playergames/mykeys.php'));
}

$output = $PAGE->get_renderer('core');
$renderable = new \local_playergames\output\mykeys($USER->id);

echo $output->header();
echo $renderable->render_content($output);
echo $output->footer();
