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
 * Renderable for the cartridge management page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\output;

use renderable;
use renderer_base;
use templatable;
use moodle_url;

/**
 * Exports data for the cartridge management shell template.
 * Follows the PlayerHUD manage_layout pattern: heading + server-side nav tabs + content_html.
 */
class cartridge implements renderable, templatable {
    /** @var string Active tab slug: 'library', 'import', 'ai', or 'editor'. */
    private string $tab;

    /** @var string Pre-rendered HTML for the active tab content area. */
    private string $contenthtml;

    /**
     * Constructor.
     *
     * @param string $tab Active tab slug.
     * @param string $contenthtml Pre-rendered HTML for the tab content area.
     */
    public function __construct(string $tab, string $contenthtml) {
        $this->tab = $tab;
        $this->contenthtml = $contenthtml;
    }

    /**
     * Exports the data needed by the cartridge shell Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context data.
     */
    public function export_for_template(renderer_base $output): array {
        $baseurl = '/local/playergames/cartridge.php';

        $tabs = [
            [
                'active' => $this->tab === 'library',
                'url' => (new moodle_url($baseurl, ['tab' => 'library']))->out(false),
                'text' => get_string('cartridge_tab_library', 'local_playergames'),
                'icon' => '<i class="fa fa-list" aria-hidden="true"></i>',
            ],
            [
                'active' => $this->tab === 'import',
                'url' => (new moodle_url($baseurl, ['tab' => 'import']))->out(false),
                'text' => get_string('cartridge_tab_import', 'local_playergames'),
                'icon' => '<i class="fa fa-upload" aria-hidden="true"></i>',
            ],
            [
                'active' => $this->tab === 'ai',
                'url' => (new moodle_url($baseurl, ['tab' => 'ai']))->out(false),
                'text' => get_string('cartridge_tab_ai', 'local_playergames'),
                'icon' => '<i class="fa fa-magic" aria-hidden="true"></i>',
            ],
            [
                'active' => $this->tab === 'editor',
                'url' => (new moodle_url($baseurl, ['tab' => 'editor']))->out(false),
                'text' => get_string('cartridge_tab_editor', 'local_playergames'),
                'icon' => '<i class="fa fa-pencil" aria-hidden="true"></i>',
            ],
        ];

        return [
            'heading' => get_string('cartridge_heading', 'local_playergames'),
            'tabs' => $tabs,
            'content_html' => $this->contenthtml,
        ];
    }
}
