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
 * The form of a mission.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_playergames\hub\mission_manager;

/**
 * Adds a mission, or changes one.
 *
 * Custom data: 'locked' (bool), when somebody completed the mission in the active season and only its name may
 * change; 'adding' (bool), when the kind is still to be chosen.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mission_form extends \moodleform {
    /**
     * Define the form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $locked = !empty($this->_customdata['locked']);
        $adding = !empty($this->_customdata['adding']);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if ($locked) {
            $mform->addElement('static', 'lockednotice', '', get_string('mission_locked_help', 'local_playergames'));
        }

        $types = [];
        foreach (array_keys(mission_manager::TYPES) as $type) {
            $types[$type] = get_string('mission_type_' . $type, 'local_playergames');
        }
        $mform->addElement('select', 'type', get_string('mission_field_type', 'local_playergames'), $types);
        if (!$adding) {
            // The kind of a mission is what its progress counts: changing it would mix two counts in one row.
            $mform->hardFreeze('type');
        }

        $mform->addElement('text', 'name', get_string('mission_field_name', 'local_playergames'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'mission_field_name', 'local_playergames');

        foreach (['targetvalue' => 'target', 'xpreward' => 'xp', 'freezereward' => 'freezes'] as $field => $label) {
            $mform->addElement('text', $field, get_string('mission_field_' . $label, 'local_playergames'), ['size' => 8]);
            $mform->setType($field, PARAM_INT);
        }
        $mform->addHelpButton('targetvalue', 'mission_field_target', 'local_playergames');
        $mform->addHelpButton('freezereward', 'mission_field_freezes', 'local_playergames');

        $mform->addElement('advcheckbox', 'enabled', get_string('mission_enabled', 'local_playergames'));
        $mform->setDefault('enabled', 1);
        $mform->setDefault('freezereward', 0);

        if ($locked) {
            $mform->hardFreeze(['targetvalue', 'xpreward', 'freezereward', 'enabled']);
        }

        $this->add_action_buttons();
    }

    /**
     * Checks the values against the range of the kind of mission.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors, keyed by field.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($this->_customdata['locked'])) {
            return $errors;
        }
        $type = (string) ($data['type'] ?? '');
        if (!isset(mission_manager::TYPES[$type])) {
            return $errors + ['type' => get_string('error_mission_type', 'local_playergames')];
        }
        $problems = mission_manager::errors(
            $type,
            (int) ($data['targetvalue'] ?? 0),
            (int) ($data['xpreward'] ?? 0),
            (int) ($data['freezereward'] ?? 0)
        );
        foreach ($problems as $field => $key) {
            $errors[$field] = get_string($key, 'local_playergames');
        }
        return $errors;
    }
}
