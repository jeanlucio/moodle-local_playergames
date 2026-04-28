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
 * Renderable for the personal AI API keys page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use local_playergames\api_key_helper;
use moodle_url;
use renderer_base;

/**
 * Builds the template context for mykeys.mustache.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mykeys {
    /** @var int The user whose keys are being managed. */
    private int $userid;

    /**
     * Constructor.
     *
     * @param int $userid
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Returns the Mustache template context array.
     *
     * @param renderer_base $output The active renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $getpref = function (string $provider): string {
            return get_user_preferences(
                'local_playergames_' . $provider . '_key',
                '',
                $this->userid
            );
        };

        $valgemini = $getpref(api_key_helper::PROVIDER_GEMINI);
        $valgroq   = $getpref(api_key_helper::PROVIDER_GROQ);
        $valopenai = $getpref(api_key_helper::PROVIDER_OPENAI);

        $logrows = $this->get_log_rows();

        return [
            'heading'               => get_string('mykeys_heading', 'local_playergames'),
            'intro'                 => get_string('mykeys_intro', 'local_playergames'),
            'action_url'            => (new moodle_url('/local/playergames/mykeys.php'))->out(false),
            'sesskey'               => sesskey(),
            'val_gemini'            => $valgemini,
            'val_groq'              => $valgroq,
            'val_openai'            => $valopenai,
            'label_gemini'          => get_string('settings_gemini_key', 'local_playergames'),
            'label_groq'            => get_string('settings_groq_key', 'local_playergames'),
            'label_openai'          => get_string('settings_openai_key', 'local_playergames'),
            'placeholder'           => get_string('apikey_placeholder', 'local_playergames'),
            'save_label'            => get_string('mykeys_save', 'local_playergames'),
            'show_hide_label'       => get_string('mykeys_show_hide', 'local_playergames'),
            'openai_advanced_label' => get_string('mykeys_openai_advanced', 'local_playergames'),
            'openai_advanced_hint'  => get_string('mykeys_openai_advanced_hint', 'local_playergames'),
            'openai_url_label'      => get_string('settings_openai_baseurl', 'local_playergames'),
            'openai_url'            => api_key_helper::get_openai_baseurl(),
            'openai_model_label'    => get_string('settings_openai_model', 'local_playergames'),
            'openai_model'          => api_key_helper::get_openai_model(),
            'ailog_heading'         => get_string('mykeys_ailog_heading', 'local_playergames'),
            'ailog_empty_label'     => get_string('mykeys_ailog_empty', 'local_playergames'),
            'ailog_topic_label'     => get_string('mykeys_ailog_topic', 'local_playergames'),
            'ailog_concepts_label'  => get_string('mykeys_ailog_concepts', 'local_playergames'),
            'ailog_model_label'     => get_string('mykeys_ailog_model', 'local_playergames'),
            'ailog_rows'            => $logrows,
            'ailog_has_rows'        => !empty($logrows),
        ];
    }

    /**
     * Fetches the last 15 AI generation log entries for this user.
     *
     * @return array Array of row arrays for the Mustache template.
     */
    private function get_log_rows(): array {
        global $DB;
        $records = $DB->get_records(
            'local_playergames_ai_log',
            ['userid' => $this->userid],
            'timecreated DESC',
            'id, provider, model, topic, conceptcount, timecreated',
            0,
            15
        );
        $rows = [];
        $providericonsmap = [
            'Gemini' => 'fa fa-google',
            'Groq'   => 'fa fa-bolt',
            'OpenAI' => 'fa fa-plug',
        ];
        foreach ($records as $record) {
            $rows[] = [
                'provider'      => s($record->provider),
                'provider_icon' => $providericonsmap[$record->provider] ?? 'fa fa-cog',
                'model'         => s($record->model ?? ''),
                'topic'         => format_string($record->topic),
                'conceptcount'  => (int) $record->conceptcount,
                'date'          => userdate(
                    $record->timecreated,
                    get_string('strftimedatetimeshort', 'core_langconfig')
                ),
            ];
        }
        return $rows;
    }

    /**
     * Renders the page content using the Mustache template.
     *
     * @param renderer_base $output
     * @return string HTML
     */
    public function render_content(renderer_base $output): string {
        return $output->render_from_template(
            'local_playergames/mykeys',
            $this->export_for_template($output)
        );
    }
}
