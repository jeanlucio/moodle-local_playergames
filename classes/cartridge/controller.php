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
 * Controller for the concept cartridge management page.
 *
 * @package    local_playergames
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_playergames\cartridge;

use local_playergames\event\cartridge_deleted;
use local_playergames\event\cartridge_imported;
use local_playergames\output\cartridge as cartridge_output;

/**
 * Handles POST dispatch and page data assembly for the cartridge management page.
 */
class controller {
    /** @var \context Active Moodle context. */
    private \context $context;

    /** @var string Active tab slug: 'import', 'ai', or 'editor'. */
    private string $tab;

    /** @var int Cartridge ID currently being edited or viewed, or 0. */
    private int $cartridgeid;

    /** @var int Concept ID to pre-fill the inline edit form, or 0. */
    private int $editconceptid;

    /** @var array|null AI preview concepts after a successful generation. */
    private ?array $aipreview = null;

    /** @var \stdClass|null AI form values for repopulation after generation. */
    private ?\stdClass $aiformdata = null;

    /** @var string Error message from a failed AI generation, or empty string. */
    private string $aierror = '';

    /**
     * Constructor.
     *
     * @param \context $context Active Moodle context.
     * @param string $tab Active tab slug.
     * @param int $cartridgeid Cartridge ID to edit, or 0.
     * @param int $editconceptid Concept ID to pre-fill the edit form, or 0.
     */
    public function __construct(
        \context $context,
        string $tab,
        int $cartridgeid,
        int $editconceptid
    ) {
        $this->context = $context;
        $this->tab = $tab;
        $this->cartridgeid = $cartridgeid;
        $this->editconceptid = $editconceptid;
    }

    /**
     * Outputs the cartridge as a downloadable JSON file and exits.
     * Must be called before any PAGE setup or HTML output.
     * Returns immediately (no-op) when the action is not 'export_cartridge'.
     *
     * @param int $cartridgeid Cartridge ID to export.
     * @param string $action Current GET action value.
     * @throws \moodle_exception If the cartridge does not exist.
     */
    public static function handle_export(int $cartridgeid, string $action): void {
        global $DB;

        if ($action !== 'export_cartridge' || $cartridgeid <= 0) {
            return;
        }

        $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $cartridgeid]);
        if (!$cartridge) {
            throw new \moodle_exception('error_cartridge_notfound', 'local_playergames');
        }

        $concepts = $DB->get_records(
            'local_playergames_concepts',
            ['cartridgeid' => $cartridgeid],
            'id ASC'
        );

        $exportcatmap = [];
        $exportcatids = array_filter(array_column((array) $concepts, 'categoryid'));
        if (!empty($exportcatids)) {
            [$excatsql, $excatparams] = $DB->get_in_or_equal($exportcatids);
            $catrows = $DB->get_records_sql(
                "SELECT id, name FROM {local_playergames_categories} WHERE id {$excatsql}",
                $excatparams
            );
            foreach ($catrows as $cat) {
                $exportcatmap[(int) $cat->id] = $cat->name;
            }
        }

        $conceptsdata = [];
        foreach ($concepts as $c) {
            $conceptsdata[] = [
                'term' => $c->term,
                'definition' => $c->definition,
                'category' => $exportcatmap[(int) $c->categoryid] ?? '',
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

    /**
     * Dispatches the active POST action to the appropriate private handler.
     * Action handlers either redirect or update internal AI state.
     *
     * @throws \moodle_exception On data integrity failures.
     */
    public function handle_post(): void {
        $postaction = required_param('action', PARAM_ALPHANUMEXT);

        if ($postaction === 'import_json') {
            $this->action_import_json();
        } else if ($postaction === 'generate_ai') {
            $this->action_generate_ai();
        } else if ($postaction === 'save_ai_cartridge') {
            $this->action_save_ai_cartridge();
        } else if ($postaction === 'create_cartridge') {
            $this->action_create_cartridge();
        } else if ($postaction === 'add_concept') {
            $this->action_add_concept();
        } else if ($postaction === 'edit_concept') {
            $this->action_edit_concept();
        } else if ($postaction === 'delete_concept') {
            $this->action_delete_concept();
        } else if ($postaction === 'toggle_active') {
            $this->action_toggle_active();
        } else if ($postaction === 'delete_cartridge') {
            $this->action_delete_cartridge();
        } else if ($postaction === 'add_category') {
            $this->action_add_category();
        } else if ($postaction === 'rename_category') {
            $this->action_rename_category();
        } else if ($postaction === 'delete_category') {
            $this->action_delete_category();
        }
    }

    /**
     * Builds and returns the Mustache template context array for the cartridge page.
     *
     * @param \renderer_base $output The active renderer.
     * @return array Template context data.
     */
    public function get_renderable(\renderer_base $output): array {
        global $DB;

        $allcartridges = $DB->get_records('local_playergames_cartridges', null, 'timecreated DESC');

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

        // Bulk-load uploader full names to avoid N+1 queries.
        $uploaderids = array_unique(array_column((array) $allcartridges, 'uploadedby'));
        $uploaders = [];
        if (!empty($uploaderids)) {
            [$insql2, $inparams2] = $DB->get_in_or_equal($uploaderids);
            $uploaderusers = $DB->get_records_sql(
                "SELECT id, firstname, lastname, firstnamephonetic, lastnamephonetic,
                        middlename, alternatename FROM {user} WHERE id {$insql2}",
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
        $categories = [];

        if ($this->cartridgeid > 0) {
            $editcartridge = $DB->get_record(
                'local_playergames_cartridges',
                ['id' => $this->cartridgeid]
            );
            if ($editcartridge) {
                $catmgr = new category_manager();
                $categories = $catmgr->get_categories($this->cartridgeid);
                $concepts = array_values(
                    $DB->get_records(
                        'local_playergames_concepts',
                        ['cartridgeid' => $this->cartridgeid],
                        'id ASC'
                    )
                );
                if ($this->editconceptid > 0) {
                    $editconcept = $DB->get_record(
                        'local_playergames_concepts',
                        ['id' => $this->editconceptid, 'cartridgeid' => $this->cartridgeid]
                    ) ?: null;
                }
            }
        }

        $gen = new ai_generator();
        $renderable = new cartridge_output(
            $this->tab,
            array_values($allcartridges),
            $editcartridge,
            $concepts,
            $categories,
            $editconcept,
            $this->aipreview,
            $this->aiformdata,
            $this->aierror,
            $gen->has_key()
        );
        return $renderable->export_for_template($output);
    }

    /**
     * Returns a URL pointing to the cartridge management page.
     *
     * @param array $params Query string parameters.
     * @return \moodle_url
     */
    private function url(array $params = []): \moodle_url {
        return new \moodle_url('/local/playergames/cartridge.php', $params);
    }

    /**
     * Handles the import_json POST action.
     */
    private function action_import_json(): void {
        global $USER;

        if (!isset($_FILES['jsonfile']) || $_FILES['jsonfile']['error'] !== UPLOAD_ERR_OK) {
            redirect(
                $this->url(['tab' => 'import']),
                get_string('error_cartridge_nofile', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        if (!is_uploaded_file($_FILES['jsonfile']['tmp_name'])) {
            redirect(
                $this->url(['tab' => 'import']),
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
                'context' => $this->context,
                'objectid' => $result->cartridgeid,
            ]);
            $event->trigger();
            redirect(
                $this->url(['tab' => 'editor', 'cartridgeid' => $result->cartridgeid]),
                get_string('cartridge_import_success', 'local_playergames', $result),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } catch (\moodle_exception $e) {
            redirect(
                $this->url(['tab' => 'import']),
                $e->getMessage(),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    /**
     * Handles the generate_ai POST action.
     * Does not redirect — sets internal AI state for page re-rendering.
     */
    private function action_generate_ai(): void {
        $topic = required_param('topic', PARAM_TEXT);
        $ailanguage = optional_param('ai_language', '', PARAM_TEXT);
        $quantity = required_param('quantity', PARAM_INT);
        $difficulty = required_param('difficulty', PARAM_INT);
        $aicategoriesraw = optional_param('ai_categories', '', PARAM_TEXT);
        $quantity = max(10, min(100, $quantity));
        $difficulty = max(1, min(5, $difficulty));

        $categorynames = [];
        if ($aicategoriesraw !== '') {
            foreach (explode(',', $aicategoriesraw) as $catname) {
                $catname = trim(clean_param($catname, PARAM_TEXT));
                if ($catname !== '') {
                    $categorynames[] = $catname;
                }
            }
        }

        $this->aiformdata = new \stdClass();
        $this->aiformdata->topic = $topic;
        $this->aiformdata->language = $ailanguage;
        $this->aiformdata->quantity = $quantity;
        $this->aiformdata->difficulty = $difficulty;
        $this->aiformdata->categories = $aicategoriesraw;
        $this->tab = 'ai';

        try {
            $gen = new ai_generator();
            $rawconcepts = $gen->generate($topic, $ailanguage, $quantity, $difficulty, $categorynames);
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
            $this->aipreview = [
                'cartridge_name' => $topic,
                'language' => $ailanguage,
                'concepts' => $indexed,
            ];
        } catch (\moodle_exception $e) {
            $this->aierror = $e->getMessage();
        }
    }

    /**
     * Handles the save_ai_cartridge POST action.
     */
    private function action_save_ai_cartridge(): void {
        global $DB, $USER;

        $cartridgename = required_param('ai_cartridge_name', PARAM_TEXT);
        $cartridgelang = optional_param('ai_language', '', PARAM_TEXT);
        $rawconcepts = isset($_POST['concepts']) && is_array($_POST['concepts'])
            ? $_POST['concepts']
            : [];

        $newcartridge = new \stdClass();
        $newcartridge->name = \core_text::substr(clean_param($cartridgename, PARAM_TEXT), 0, 255);
        $newcartridge->version = '1.0';
        $newcartridge->language = \core_text::substr(
            clean_param($cartridgelang, PARAM_TEXT),
            0,
            20
        );
        $aiauthor = \core_text::substr(
            clean_param(optional_param('ai_author', '', PARAM_TEXT), PARAM_TEXT),
            0,
            255
        );
        $now = time();
        $newcartridge->timecreated = $now;
        $newcartridge->timemodified = $now;
        $newcartridge->uploadedby = (int) $USER->id;
        $newcartridge->author = $aiauthor !== '' ? $aiauthor : null;
        $newcartridge->active = 1;
        $newcartridgeid = $DB->insert_record('local_playergames_cartridges', $newcartridge);

        $imp = new importer();
        $imp->save_concepts($newcartridgeid, $rawconcepts);

        $event = cartridge_imported::create([
            'context' => $this->context,
            'objectid' => $newcartridgeid,
        ]);
        $event->trigger();

        redirect(
            $this->url(['tab' => 'editor', 'cartridgeid' => $newcartridgeid]),
            get_string('cartridge_created', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Handles the create_cartridge POST action.
     */
    private function action_create_cartridge(): void {
        global $DB, $USER;

        $cartridgename = required_param('cartridge_name', PARAM_TEXT);
        $cartridgelang = optional_param('cartridge_language', '', PARAM_TEXT);
        $cartridgeauthor = \core_text::substr(
            clean_param(optional_param('cartridge_author', '', PARAM_TEXT), PARAM_TEXT),
            0,
            255
        );

        $newcartridge = new \stdClass();
        $newcartridge->name = \core_text::substr(clean_param($cartridgename, PARAM_TEXT), 0, 255);
        $newcartridge->version = '1.0';
        $newcartridge->language = \core_text::substr(
            clean_param($cartridgelang, PARAM_TEXT),
            0,
            20
        );
        $createtime = time();
        $newcartridge->timecreated = $createtime;
        $newcartridge->timemodified = $createtime;
        $newcartridge->uploadedby = (int) $USER->id;
        $newcartridge->author = $cartridgeauthor !== '' ? $cartridgeauthor : null;
        $newcartridge->active = 1;
        $newcartridgeid = $DB->insert_record('local_playergames_cartridges', $newcartridge);

        redirect(
            $this->url(['tab' => 'editor', 'cartridgeid' => $newcartridgeid]),
            get_string('cartridge_created', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Handles the add_concept POST action.
     */
    private function action_add_concept(): void {
        global $DB;

        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        if (!$DB->record_exists('local_playergames_cartridges', ['id' => $postcartridgeid])) {
            throw new \moodle_exception('error_cartridge_notfound', 'local_playergames');
        }

        $imp = new importer();
        $concept = $imp->sanitize_concept([
            'term' => required_param('term', PARAM_TEXT),
            'definition' => required_param('definition', PARAM_TEXT),
            'categoryid' => optional_param('categoryid', 0, PARAM_INT),
            'difficulty' => optional_param('difficulty', 3, PARAM_INT),
        ], $postcartridgeid);

        if ($concept->term === '') {
            redirect(
                $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
                get_string('error_concept_empty_term', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        $DB->insert_record('local_playergames_concepts', $concept);
        $DB->set_field(
            'local_playergames_cartridges',
            'timemodified',
            time(),
            ['id' => $postcartridgeid]
        );
        redirect(
            $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
            get_string('concept_saved', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Handles the edit_concept POST action.
     */
    private function action_edit_concept(): void {
        global $DB;

        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $postconceptid = required_param('concept_id', PARAM_INT);
        $existingconcept = $DB->get_record(
            'local_playergames_concepts',
            ['id' => $postconceptid, 'cartridgeid' => $postcartridgeid]
        );
        if (!$existingconcept) {
            throw new \moodle_exception('error_cartridge_notfound', 'local_playergames');
        }

        $imp = new importer();
        $updated = $imp->sanitize_concept([
            'term' => required_param('term', PARAM_TEXT),
            'definition' => required_param('definition', PARAM_TEXT),
            'categoryid' => optional_param('categoryid', 0, PARAM_INT),
            'difficulty' => optional_param('difficulty', 3, PARAM_INT),
        ], $postcartridgeid);

        if ($updated->term === '') {
            redirect(
                $this->url([
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
        $DB->set_field(
            'local_playergames_cartridges',
            'timemodified',
            time(),
            ['id' => $postcartridgeid]
        );
        redirect(
            $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
            get_string('concept_saved', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Handles the delete_concept POST action.
     */
    private function action_delete_concept(): void {
        global $DB;

        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $postconceptid = required_param('concept_id', PARAM_INT);
        $DB->delete_records(
            'local_playergames_concepts',
            ['id' => $postconceptid, 'cartridgeid' => $postcartridgeid]
        );
        $DB->set_field(
            'local_playergames_cartridges',
            'timemodified',
            time(),
            ['id' => $postcartridgeid]
        );
        redirect(
            $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
            get_string('concept_deleted', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Handles the toggle_active POST action.
     */
    private function action_toggle_active(): void {
        global $DB;

        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $cartridgerow = $DB->get_record('local_playergames_cartridges', ['id' => $postcartridgeid]);
        if (!$cartridgerow) {
            throw new \moodle_exception('error_cartridge_notfound', 'local_playergames');
        }
        $cartridgerow->active = $cartridgerow->active ? 0 : 1;
        $cartridgerow->timemodified = time();
        $DB->update_record('local_playergames_cartridges', $cartridgerow);
        redirect($this->url(), '', null, \core\output\notification::NOTIFY_SUCCESS);
    }

    /**
     * Handles the delete_cartridge POST action.
     */
    private function action_delete_cartridge(): void {
        global $DB;

        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $DB->delete_records('local_playergames_concepts', ['cartridgeid' => $postcartridgeid]);
        $DB->delete_records('local_playergames_categories', ['cartridgeid' => $postcartridgeid]);
        $cartridgerow = $DB->get_record('local_playergames_cartridges', ['id' => $postcartridgeid]);
        if ($cartridgerow) {
            $DB->delete_records('local_playergames_cartridges', ['id' => $postcartridgeid]);
            $event = cartridge_deleted::create([
                'context' => $this->context,
                'objectid' => $postcartridgeid,
            ]);
            $event->trigger();
        }
        redirect(
            $this->url(),
            get_string('cartridge_deleted', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Handles the add_category POST action.
     */
    private function action_add_category(): void {
        global $DB;

        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $catname = required_param('category_name', PARAM_TEXT);
        if (!$DB->record_exists('local_playergames_cartridges', ['id' => $postcartridgeid])) {
            throw new \moodle_exception('error_cartridge_notfound', 'local_playergames');
        }
        try {
            $catmgr = new category_manager();
            $catmgr->create($postcartridgeid, $catname);
            redirect(
                $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
                get_string('category_saved', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } catch (\moodle_exception $e) {
            redirect(
                $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
                $e->getMessage(),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    /**
     * Handles the rename_category POST action.
     */
    private function action_rename_category(): void {
        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $postcategoryid = required_param('category_id', PARAM_INT);
        $catname = required_param('category_name', PARAM_TEXT);
        try {
            $catmgr = new category_manager();
            $catmgr->rename($postcategoryid, $postcartridgeid, $catname);
            redirect(
                $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
                get_string('category_saved', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } catch (\moodle_exception $e) {
            redirect(
                $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
                $e->getMessage(),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    /**
     * Handles the delete_category POST action.
     */
    private function action_delete_category(): void {
        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $postcategoryid = required_param('category_id', PARAM_INT);
        $catmgr = new category_manager();
        $catmgr->delete($postcategoryid, $postcartridgeid);
        redirect(
            $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
            get_string('category_deleted', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}
