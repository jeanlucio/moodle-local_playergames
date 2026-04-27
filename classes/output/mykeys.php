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
     * @return array
     */
    public function export_for_template(): array {
        $providers = [
            api_key_helper::PROVIDER_GEMINI,
            api_key_helper::PROVIDER_GROQ,
            api_key_helper::PROVIDER_OPENAI,
        ];

        $rows = [];
        foreach ($providers as $provider) {
            $personal = get_user_preferences(
                'local_playergames_' . $provider . '_key',
                '',
                $this->userid
            );
            $rows[] = [
                'provider'     => $provider,
                'label'        => get_string('settings_' . $provider . '_key', 'local_playergames'),
                'has_personal' => $personal !== '',
                'placeholder'  => get_string('apikey_placeholder', 'local_playergames'),
                'sesskey'      => sesskey(),
                'action_url'   => (new moodle_url('/local/playergames/mykeys.php'))->out(false),
            ];
        }

        return [
            'heading'    => get_string('mykeys_heading', 'local_playergames'),
            'intro'      => get_string('mykeys_intro', 'local_playergames'),
            'providers'  => $rows,
            'clear_label' => get_string('apikey_clear', 'local_playergames'),
        ];
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
            $this->export_for_template()
        );
    }
}
