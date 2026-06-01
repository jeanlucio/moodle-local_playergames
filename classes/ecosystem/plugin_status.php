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
 * Installation status checker for Player ecosystem plugins.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\ecosystem;

/**
 * Queries core_plugin_manager to determine which ecosystem plugins are installed.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_status {
    /**
     * Returns installation status and version for each plugin in the catalog.
     *
     * The returned array is keyed by component name. Each value contains:
     *   - installed  bool   True when the plugin is present on disk.
     *   - version    string Human-readable release string or empty.
     *
     * @return array<string, array{installed: bool, version: string}>
     */
    public static function get_installed(): array {
        $manager = \core_plugin_manager::instance();
        $result  = [];

        foreach (plugin_registry::get_catalog() as $def) {
            $component = $def['component'];
            $info      = $manager->get_plugin_info($component);

            if ($info === null) {
                $result[$component] = ['installed' => false, 'version' => ''];
            } else {
                $version = '';
                if (!empty($info->release)) {
                    $version = (string) $info->release;
                } else if (!empty($info->versiondb)) {
                    $version = (string) $info->versiondb;
                }
                $result[$component] = ['installed' => true, 'version' => $version];
            }
        }

        return $result;
    }
}
