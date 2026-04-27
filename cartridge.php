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

use local_playergames\cartridge\importer;
use local_playergames\cartridge\ai_generator;
use local_playergames\api_key_helper;
use local_playergames\output\cartridge as cartridge_output;
use local_playergames\event\cartridge_imported;
use local_playergames\event\cartridge_deleted;

$tab = optional_param('tab', 'import', PARAM_ALPHA);
$cartridgeid = optional_param('cartridgeid', 0, PARAM_INT);
$editconceptid = optional_param('editconcept', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

require_login();
$context = context_system::instance();
require_capability('local/playergames:managecartridges', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/playergames/cartridge.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('cartridge_pagetitle', 'local_playergames'));
$PAGE->set_heading(get_string('cartridge_pagetitle', 'local_playergames'));

// --- Export action: output JSON directly before any HTML -------------------------

if ($action === 'export_cartridge' && $cartridgeid > 0) {
    $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $cartridgeid]);
    if (!$cartridge) {
        throw new moodle_exception('error_cartridge_notfound', 'local_playergames');
    }
    $concepts = $DB->get_records(
        'local_playergames_concepts',
        ['cartridgeid' => $cartridgeid],
        'id ASC'
    );
    $conceptsdata = [];
    foreach ($concepts as $c) {
        $conceptsdata[] = [
            'term' => $c->term,
            'definition' => $c->definition,
            'category' => $c->category ?? '',
            'difficulty' => (int) $c->difficulty,
        ];
    }
    $exportdata = [
        'name' => $cartridge->name,
        'version' => $cartridge->version,
        'language' => $cartridge->language,
        'concepts' => $conceptsdata,
    ];
    $json = json_encode($exportdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $filename = preg_replace('/[^a-z0-9_-]/i', '_', $cartridge->name) . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    die();
}

// --- POST handler ----------------------------------------------------------------

$ai_preview = null;
$ai_form_data = null;
$ai_error = '';
$redirecturl = new moodle_url('/local/playergames/cartridge.php');

if (data_submitted()) {
    require_sesskey();
    $postaction = required_param('action', PARAM_ALPHANUMEXT);

    if ($postaction === 'import_json') {
        // File upload path.
        if (!isset($_FILES['jsonfile']) || $_FILES['jsonfile']['error'] !== UPLOAD_ERR_OK) {
            redirect(
                new moodle_url('/local/playergames/cartridge.php', ['tab' => 'import']),
                get_string('error_cartridge_nofile', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        if (!is_uploaded_file($_FILES['jsonfile']['tmp_name'])) {
            redirect(
                new moodle_url('/local/playergames/cartridge.php', ['tab' => 'import']),
                get_string('error_cartridge_nofile', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        $jsonstring = file_get_contents($_FILES['jsonfile']['tmp_name']);
        try {
            $imp = new importer();
            $result = $imp->import($jsonstring, (int) $USER->id);
            $event = cartridge_imported::create([
                'context' => $context,
                'objectid' => $result->cartridgeid,
            ]);
            $event->trigger();
            $msg = get_string('cartridge_import_success', 'local_playergames', $result);
            redirect(
                new moodle_url('/local/playergames/cartridge.php', [
                    'tab' => 'editor',
                    'cartridgeid' => $result->cartridgeid,
                ]),
                $msg,
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } catch (moodle_exception $e) {
            redirect(
                new moodle_url('/local/playergames/cartridge.php', ['tab' => 'import']),
                $e->getMessage(),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

    } else if ($postaction === 'generate_ai') {
        $topic = required_param('topic', PARAM_TEXT);
        $ailanguage = optional_param('ai_language', '', PARAM_TEXT);
        $quantity = required_param('quantity', PARAM_INT);
        $difficulty = required_param('difficulty', PARAM_INT);
        $quantity = max(10, min(100, $quantity));
        $difficulty = max(1, min(5, $difficulty));

        $ai_form_data = new stdClass();
        $ai_form_data->topic = $topic;
        $ai_form_data->language = $ailanguage;
        $ai_form_data->quantity = $quantity;
        $ai_form_data->difficulty = $difficulty;

        $tab = 'ai';
        try {
            $gen = new ai_generator();
            $rawconcepts = $gen->generate($topic, $ailanguage, $quantity, $difficulty);
            $indexed = [];
            foreach ($rawconcepts as $i => $c) {
                $indexed[] = [
                    'index' => $i,
                    'term' => s($c['term'] ?? ''),
                    'definition' => s($c['definition'] ?? ''),
                    'category' => s($c['category'] ?? ''),
                    'difficulty' => max(1, min(5, (int) ($c['difficulty'] ?? 3))),
                ];
            }
            $ai_preview = [
                'cartridge_name' => $topic,
                'language' => $ailanguage,
                'concepts' => $indexed,
            ];
        } catch (moodle_exception $e) {
            $ai_error = $e->getMessage();
        }
        // Fall through to page rendering (no redirect).

    } else if ($postaction === 'save_ai_cartridge') {
        $cartridgename = required_param('ai_cartridge_name', PARAM_TEXT);
        $cartridgelang = optional_param('ai_language', '', PARAM_TEXT);
        $rawconcepts = isset($_POST['concepts']) && is_array($_POST['concepts'])
            ? $_POST['concepts']
            : [];

        $newcartridge = new stdClass();
        $newcartridge->name = core_text::substr(clean_param($cartridgename, PARAM_TEXT), 0, 255);
        $newcartridge->version = '1.0';
        $newcartridge->language = core_text::substr(
            clean_param($cartridgelang, PARAM_TEXT),
            0,
            20
        );
        $newcartridge->timeuploaded = time();
        $newcartridge->uploadedby = (int) $USER->id;
        $newcartridge->active = 1;
        $newcartridgeid = $DB->insert_record('local_playergames_cartridges', $newcartridge);

        $imp = new importer();
        $imp->save_concepts($newcartridgeid, $rawconcepts);

        $event = cartridge_imported::create([
            'context' => $context,
            'objectid' => $newcartridgeid,
        ]);
        $event->trigger();

        redirect(
            new moodle_url('/local/playergames/cartridge.php', [
                'tab' => 'editor',
                'cartridgeid' => $newcartridgeid,
            ]),
            get_string('cartridge_created', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else if ($postaction === 'add_concept') {
        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $DB->get_record_or_exception(
            'local_playergames_cartridges',
            ['id' => $postcartridgeid],
            'error_cartridge_notfound',
            'local_playergames'
        );
        $imp = new importer();
        $concept = $imp->sanitize_concept([
            'term' => required_param('term', PARAM_TEXT),
            'definition' => required_param('definition', PARAM_TEXT),
            'category' => optional_param('category', '', PARAM_TEXT),
            'difficulty' => optional_param('difficulty', 3, PARAM_INT),
        ], $postcartridgeid);
        if ($concept->term === '') {
            redirect(
                new moodle_url('/local/playergames/cartridge.php', [
                    'tab' => 'editor',
                    'cartridgeid' => $postcartridgeid,
                ]),
                get_string('error_concept_empty_term', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        $DB->insert_record('local_playergames_concepts', $concept);
        redirect(
            new moodle_url('/local/playergames/cartridge.php', [
                'tab' => 'editor',
                'cartridgeid' => $postcartridgeid,
            ]),
            get_string('concept_saved', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else if ($postaction === 'edit_concept') {
        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $postconceptid = required_param('concept_id', PARAM_INT);
        $existingconcept = $DB->get_record_or_exception(
            'local_playergames_concepts',
            ['id' => $postconceptid, 'cartridgeid' => $postcartridgeid],
            'error_cartridge_notfound',
            'local_playergames'
        );
        $imp = new importer();
        $updated = $imp->sanitize_concept([
            'term' => required_param('term', PARAM_TEXT),
            'definition' => required_param('definition', PARAM_TEXT),
            'category' => optional_param('category', '', PARAM_TEXT),
            'difficulty' => optional_param('difficulty', 3, PARAM_INT),
        ], $postcartridgeid);
        if ($updated->term === '') {
            redirect(
                new moodle_url('/local/playergames/cartridge.php', [
                    'tab' => 'editor',
                    'cartridgeid' => $postcartridgeid,
                    'editconcept' => $postconceptid,
                ]),
                get_string('error_concept_empty_term', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        $updated->id = $existingconcept->id;
        $DB->update_record('local_playergames_concepts', $updated);
        redirect(
            new moodle_url('/local/playergames/cartridge.php', [
                'tab' => 'editor',
                'cartridgeid' => $postcartridgeid,
            ]),
            get_string('concept_saved', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else if ($postaction === 'delete_concept') {
        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $postconceptid = required_param('concept_id', PARAM_INT);
        $DB->delete_records(
            'local_playergames_concepts',
            ['id' => $postconceptid, 'cartridgeid' => $postcartridgeid]
        );
        redirect(
            new moodle_url('/local/playergames/cartridge.php', [
                'tab' => 'editor',
                'cartridgeid' => $postcartridgeid,
            ]),
            get_string('concept_deleted', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else if ($postaction === 'toggle_active') {
        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $cartridgerow = $DB->get_record_or_exception(
            'local_playergames_cartridges',
            ['id' => $postcartridgeid],
            'error_cartridge_notfound',
            'local_playergames'
        );
        $cartridgerow->active = $cartridgerow->active ? 0 : 1;
        $DB->update_record('local_playergames_cartridges', $cartridgerow);
        redirect(
            new moodle_url('/local/playergames/cartridge.php'),
            '',
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else if ($postaction === 'delete_cartridge') {
        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $DB->delete_records('local_playergames_concepts', ['cartridgeid' => $postcartridgeid]);
        $cartridgerow = $DB->get_record('local_playergames_cartridges', ['id' => $postcartridgeid]);
        if ($cartridgerow) {
            $DB->delete_records('local_playergames_cartridges', ['id' => $postcartridgeid]);
            $event = cartridge_deleted::create([
                'context' => $context,
                'objectid' => $postcartridgeid,
            ]);
            $event->trigger();
        }
        redirect(
            new moodle_url('/local/playergames/cartridge.php'),
            get_string('cartridge_deleted', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// --- Build page data -------------------------------------------------------------

$allcartridges = $DB->get_records('local_playergames_cartridges', null, 'timeuploaded DESC');

// Bulk-load concept counts to avoid N+1 queries.
$conceptcounts = [];
if (!empty($allcartridges)) {
    $cartridgeids = array_keys($allcartridges);
    [$insql, $inparams] = $DB->get_in_or_equal($cartridgeids);
    $rows = $DB->get_records_sql(
        "SELECT cartridgeid, COUNT(id) AS cnt
           FROM {local_playergames_concepts}
          WHERE cartridgeid {$insql}
       GROUP BY cartridgeid",
        $inparams
    );
    foreach ($rows as $row) {
        $conceptcounts[(int) $row->cartridgeid] = (int) $row->cnt;
    }
}

// Bulk-load uploaders to avoid N+1 queries.
$uploaderids = array_unique(array_column((array) $allcartridges, 'uploadedby'));
$uploaders = [];
if (!empty($uploaderids)) {
    [$insql2, $inparams2] = $DB->get_in_or_equal($uploaderids);
    $uploaderusers = $DB->get_records_sql(
        "SELECT id, firstname, lastname FROM {user} WHERE id {$insql2}",
        $inparams2
    );
    foreach ($uploaderusers as $u) {
        $uploaders[(int) $u->id] = fullname($u);
    }
}

foreach ($allcartridges as $cartridge) {
    $cartridge->concepts_count = $conceptcounts[(int) $cartridge->id] ?? 0;
    $cartridge->uploadedby_fullname = $uploaders[(int) $cartridge->uploadedby] ?? '';
}

$editcartridge = null;
$concepts = [];
$editconcept = null;

if ($cartridgeid > 0) {
    $editcartridge = $DB->get_record('local_playergames_cartridges', ['id' => $cartridgeid]);
    if ($editcartridge) {
        $concepts = array_values(
            $DB->get_records('local_playergames_concepts', ['cartridgeid' => $cartridgeid], 'id ASC')
        );
        if ($editconceptid > 0) {
            $editconcept = $DB->get_record(
                'local_playergames_concepts',
                ['id' => $editconceptid, 'cartridgeid' => $cartridgeid]
            ) ?: null;
        }
    }
}

$gen = new ai_generator();
$hasaikey = $gen->has_key();

$renderable = new cartridge_output(
    $tab,
    array_values($allcartridges),
    $editcartridge,
    $concepts,
    $editconcept,
    $ai_preview,
    $ai_form_data,
    $ai_error,
    $hasaikey
);

$PAGE->requires->js_call_amd('local_playergames/cartridge', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_playergames/cartridge', $renderable->export_for_template($OUTPUT));
echo $OUTPUT->footer();
