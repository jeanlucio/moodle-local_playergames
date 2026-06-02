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
 * Concept cartridge management page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_playergames\cartridge\controller;

$tab = optional_param('tab', 'library', PARAM_ALPHA);
$cartridgeid = optional_param('cartridgeid', 0, PARAM_INT);
$editconceptid = optional_param('editconcept', 0, PARAM_INT);
$editquestionid = optional_param('editquestion', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$aisubtab = optional_param('ai_subtab', 'concepts', PARAM_ALPHA);

require_login();
$context = context_system::instance();
require_capability('local/playergames:managecartridges', $context);

// Handle export before PAGE setup — outputs raw JSON and exits.
controller::handle_export($cartridgeid, $action);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/cartridge.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('cartridge_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('cartridge_pagetitle', 'local_playergames'));

$controller = new controller($context, $tab, $cartridgeid, $editconceptid, $aisubtab, $editquestionid);

if (data_submitted()) {
    require_sesskey();
    $controller->handle_post();
}

$PAGE->requires->js_call_amd('local_playergames/cartridge', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template(
    'local_playergames/cartridge',
    $controller->get_renderable($OUTPUT)
);
echo $OUTPUT->footer();
