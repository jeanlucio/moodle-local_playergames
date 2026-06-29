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
use local_playergames\output\cartridge as cartridge_shell;
use local_playergames\cartridge\quiz_generator;

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

    /** @var string Active AI sub-tab: 'concepts' or 'quiz'. */
    private string $aisubtab = 'concepts';

    /** @var array|null AI preview concepts after a successful concept generation. */
    private ?array $aipreview = null;

    /** @var \stdClass|null AI form values for repopulation after concept generation. */
    private ?\stdClass $aiformdata = null;

    /** @var string Error message from a failed concept AI generation, or empty string. */
    private string $aierror = '';

    /** @var array|null AI preview questions after a successful quiz generation. */
    private ?array $quizpreview = null;

    /** @var \stdClass|null Quiz AI form values for repopulation. */
    private ?\stdClass $quizformdata = null;

    /** @var string Error message from a failed quiz AI generation, or empty string. */
    private string $quizerror = '';

    /** @var int Quiz question ID to pre-fill the inline edit form, or 0. */
    private int $editquestionid;

    /**
     * Constructor.
     *
     * @param \context $context Active Moodle context.
     * @param string $tab Active tab slug.
     * @param int $cartridgeid Cartridge ID to edit, or 0.
     * @param int $editconceptid Concept ID to pre-fill the edit form, or 0.
     * @param string $aisubtab Active AI sub-tab: 'concepts' or 'quiz'.
     * @param int $editquestionid Quiz question ID to pre-fill the edit form, or 0.
     */
    public function __construct(
        \context $context,
        string $tab,
        int $cartridgeid,
        int $editconceptid,
        string $aisubtab = 'concepts',
        int $editquestionid = 0
    ) {
        $this->context = $context;
        $this->tab = $tab;
        $this->cartridgeid = $cartridgeid;
        $this->editconceptid = $editconceptid;
        $this->aisubtab = in_array($aisubtab, ['concepts', 'quiz'], true) ? $aisubtab : 'concepts';
        $this->editquestionid = $editquestionid;
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

        $exportdata = (new exporter())->build($cartridge);

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
        } else if ($postaction === 'generate_ai_quiz') {
            $this->action_generate_ai_quiz();
        } else if ($postaction === 'save_ai_quiz_cartridge') {
            $this->action_save_ai_quiz_cartridge();
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
        } else if ($postaction === 'add_quiz_question') {
            $this->action_add_quiz_question();
        } else if ($postaction === 'edit_quiz_question') {
            $this->action_edit_quiz_question();
        } else if ($postaction === 'delete_quiz_question') {
            $this->action_delete_quiz_question();
        }
    }

    /**
     * Builds and returns the Mustache template context array for the cartridge shell.
     * Renders the active tab sub-template server-side and injects it as content_html.
     *
     * @param \renderer_base $output The active renderer.
     * @return array Template context data.
     */
    public function get_renderable(\renderer_base $output): array {
        global $DB;

        $baseurl = '/local/playergames/cartridge.php';
        $contenthtml = '';

        // Level 2: editing a specific cartridge. A cartridge id always means edit mode,
        // regardless of the tab slug — the tab bar is hidden in this case.
        $editing = $this->cartridgeid > 0;

        if ($editing) {
            $cartridge = $DB->get_record('local_playergames_cartridges', ['id' => $this->cartridgeid]);
            if (!$cartridge) {
                redirect(
                    $this->url(['tab' => 'library']),
                    get_string('error_cartridge_notfound', 'local_playergames'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            if (($cartridge->type ?? 'concept') === 'quiz') {
                $contenthtml = $this->render_tab_quiz_editor($output, $DB, $baseurl, $cartridge);
            } else {
                $contenthtml = $this->render_tab_editor($output, $DB, $baseurl, $cartridge);
            }
            $shell = new cartridge_shell($this->tab, $contenthtml, true);
            return $shell->export_for_template($output);
        }

        // Level 1: ways to obtain a cartridge. Legacy 'editor' with no id falls back to 'create'.
        if ($this->tab === 'import') {
            $contenthtml = $this->render_tab_import($output, $baseurl);
        } else if ($this->tab === 'ai') {
            $contenthtml = $this->render_tab_ai($output, $baseurl);
        } else if ($this->tab === 'create' || $this->tab === 'editor') {
            $contenthtml = $this->render_tab_create($output, $baseurl);
        } else {
            $contenthtml = $this->render_tab_library($output, $DB, $baseurl);
        }

        $shell = new cartridge_shell($this->tab, $contenthtml, false);
        return $shell->export_for_template($output);
    }

    /**
     * Renders the library tab HTML.
     *
     * @param \renderer_base $output The active renderer.
     * @param \moodle_database $db Database instance.
     * @param string $baseurl Base page URL string.
     * @return string Rendered HTML.
     */
    private function render_tab_library(\renderer_base $output, \moodle_database $db, string $baseurl): string {
        $allcartridges = $db->get_records('local_playergames_cartridges', null, 'active DESC, name ASC');

        $conceptcounts = [];
        $questioncounts = [];
        if (!empty($allcartridges)) {
            $cartridgeids = array_keys($allcartridges);
            [$insql, $inparams] = $db->get_in_or_equal($cartridgeids);
            $rows = $db->get_records_sql(
                "SELECT cartridgeid, COUNT(id) AS cnt
                   FROM {local_playergames_concepts}
                  WHERE cartridgeid {$insql}
               GROUP BY cartridgeid",
                $inparams
            );
            foreach ($rows as $row) {
                $conceptcounts[(int) $row->cartridgeid] = (int) $row->cnt;
            }
            [$insql2, $inparams2] = $db->get_in_or_equal($cartridgeids);
            $qrows = $db->get_records_sql(
                "SELECT cartridgeid, COUNT(id) AS cnt
                   FROM {local_playergames_concept_questions}
                  WHERE cartridgeid {$insql2}
               GROUP BY cartridgeid",
                $inparams2
            );
            foreach ($qrows as $row) {
                $questioncounts[(int) $row->cartridgeid] = (int) $row->cnt;
            }
        }

        $uploaderids = array_unique(array_column((array) $allcartridges, 'uploadedby'));
        $uploaders = [];
        if (!empty($uploaderids)) {
            [$insql2, $inparams2] = $db->get_in_or_equal($uploaderids);
            $uploaderusers = $db->get_records_sql(
                "SELECT id, firstname, lastname, firstnamephonetic, lastnamephonetic,
                        middlename, alternatename FROM {user} WHERE id {$insql2}",
                $inparams2
            );
            foreach ($uploaderusers as $u) {
                $uploaders[(int) $u->id] = fullname($u);
            }
        }

        $cartridgerows = [];
        foreach ($allcartridges as $cartridge) {
            $cartridge->concepts_count = $conceptcounts[(int) $cartridge->id] ?? 0;
            $cartridge->uploadedby_fullname = $uploaders[(int) $cartridge->uploadedby] ?? '';
            $editurl = (new \moodle_url($baseurl, [
                'cartridgeid' => $cartridge->id,
            ]))->out(false);
            $exporturl = (new \moodle_url($baseurl, [
                'action' => 'export_cartridge',
                'cartridgeid' => $cartridge->id,
            ]))->out(false);
            $cartridgetype = $cartridge->type ?? 'concept';
            $itemscount = $cartridgetype === 'quiz'
                ? ($questioncounts[(int) $cartridge->id] ?? 0)
                : ($conceptcounts[(int) $cartridge->id] ?? 0);
            $cartridgerows[] = [
                'id' => $cartridge->id,
                'name' => format_string($cartridge->name),
                'language' => s($cartridge->language),
                'version' => s($cartridge->version),
                'items_count' => $itemscount,
                'is_quiz' => $cartridgetype === 'quiz',
                'is_concept' => $cartridgetype !== 'quiz',
                'is_active' => (bool) $cartridge->active,
                'is_inactive' => !(bool) $cartridge->active,
                'created_date' => userdate(
                    $cartridge->timecreated,
                    get_string('strftimedatetimeshort', 'core_langconfig')
                ),
                'modified_date' => userdate(
                    $cartridge->timemodified,
                    get_string('strftimedatetimeshort', 'core_langconfig')
                ),
                'author' => format_string($cartridge->author ?? ''),
                'uploadedby_fullname' => format_string($cartridge->uploadedby_fullname),
                'edit_url' => $editurl,
                'export_url' => $exporturl,
                'sesskey' => sesskey(),
            ];
        }

        $ctx = [
            'action_url' => (new \moodle_url($baseurl))->out(false),
            'sesskey' => sesskey(),
            'cartridges' => $cartridgerows,
            'cartridges_empty' => empty($cartridgerows),
            'url_tab_import' => (new \moodle_url($baseurl, ['tab' => 'import']))->out(false),
            'url_tab_ai' => (new \moodle_url($baseurl, ['tab' => 'ai']))->out(false),
            'url_tab_create' => (new \moodle_url($baseurl, ['tab' => 'create']))->out(false),
        ];
        return $output->render_from_template('local_playergames/cartridge_tab_library', $ctx);
    }

    /**
     * Renders the import tab HTML.
     *
     * @param \renderer_base $output The active renderer.
     * @param string $baseurl Base page URL string.
     * @return string Rendered HTML.
     */
    private function render_tab_import(\renderer_base $output, string $baseurl): string {
        $ctx = [
            'action_url' => (new \moodle_url($baseurl))->out(false),
            'sesskey' => sesskey(),
        ];
        return $output->render_from_template('local_playergames/cartridge_tab_import', $ctx);
    }

    /**
     * Renders the "create cartridge from scratch" tab HTML.
     *
     * @param \renderer_base $output The active renderer.
     * @param string $baseurl Base page URL string.
     * @return string Rendered HTML.
     */
    private function render_tab_create(\renderer_base $output, string $baseurl): string {
        $ctx = [
            'action_url' => (new \moodle_url($baseurl))->out(false),
            'sesskey' => sesskey(),
        ];
        return $output->render_from_template('local_playergames/cartridge_tab_create', $ctx);
    }

    /**
     * Renders the AI generation tab HTML.
     *
     * @param \renderer_base $output The active renderer.
     * @param string $baseurl Base page URL string.
     * @return string Rendered HTML.
     */
    private function render_tab_ai(\renderer_base $output, string $baseurl): string {
        $gen = new ai_generator();

        $aipreviewdata = null;
        if ($this->aipreview !== null) {
            $aipreviewdata = [
                'cartridge_name' => s($this->aipreview['cartridge_name']),
                'language'       => s($this->aipreview['language']),
                'concepts'       => $this->aipreview['concepts'],
            ];
        }

        $quizpreviewdata = null;
        if ($this->quizpreview !== null) {
            $quizpreviewdata = [
                'cartridge_name' => s($this->quizpreview['cartridge_name']),
                'language'       => s($this->quizpreview['language']),
                'questions'      => $this->quizpreview['questions'],
            ];
        }

        $ctx = [
            'action_url'          => (new \moodle_url($baseurl))->out(false),
            'sesskey'             => sesskey(),
            'has_ai_key'          => $gen->has_key(),
            'aihub_installed'     => class_exists(\local_aihub\ai::class),
            'subtab_concepts'     => $this->aisubtab === 'concepts',
            'subtab_quiz'         => $this->aisubtab === 'quiz',
            // Concepts sub-tab.
            'has_ai_error'        => $this->aierror !== '',
            'ai_error'            => s($this->aierror),
            'has_ai_preview'      => $aipreviewdata !== null,
            'ai_preview'          => $aipreviewdata,
            'ai_form_topic'       => $this->aiformdata ? s($this->aiformdata->topic) : '',
            'ai_form_context'     => $this->aiformdata
                ? s($this->aiformdata->context ?? '') : '',
            'ai_form_language'    => $this->aiformdata ? s($this->aiformdata->language) : '',
            'ai_form_quantity'    => $this->aiformdata ? (int) $this->aiformdata->quantity : 20,
            'ai_form_difficulty'  => $this->aiformdata
                ? (int) $this->aiformdata->difficulty : 3,
            'ai_form_categories'  => $this->aiformdata
                ? s($this->aiformdata->categories ?? '') : '',
            // Quiz sub-tab.
            'has_quiz_error'      => $this->quizerror !== '',
            'quiz_error'          => s($this->quizerror),
            'has_quiz_preview'    => $quizpreviewdata !== null,
            'quiz_preview'        => $quizpreviewdata,
            'quiz_form_topic'     => $this->quizformdata ? s($this->quizformdata->topic) : '',
            'quiz_form_language'  => $this->quizformdata
                ? s($this->quizformdata->language) : '',
            'quiz_form_quantity'  => $this->quizformdata
                ? (int) $this->quizformdata->quantity : 20,
            'quiz_form_difficulty' => $this->quizformdata
                ? (int) ($this->quizformdata->difficulty ?? 3) : 3,
            'quiz_form_context'   => $this->quizformdata
                ? s($this->quizformdata->context ?? '') : '',
            'quiz_form_categories' => $this->quizformdata
                ? s($this->quizformdata->categories ?? '') : '',
        ];
        return $output->render_from_template('local_playergames/cartridge_tab_ai', $ctx);
    }

    /**
     * Renders the concept editor for a validated concept-type cartridge.
     *
     * @param \renderer_base $output The active renderer.
     * @param \moodle_database $db Database instance.
     * @param string $baseurl Base page URL string.
     * @param \stdClass $editcartridge The concept cartridge record being edited.
     * @return string Rendered HTML.
     */
    private function render_tab_editor(
        \renderer_base $output,
        \moodle_database $db,
        string $baseurl,
        \stdClass $editcartridge
    ): string {
        $editconcept = null;

        $catmgr = new category_manager();
        $categories = $catmgr->get_categories($this->cartridgeid);
        $concepts = array_values(
            $db->get_records(
                'local_playergames_concepts',
                ['cartridgeid' => $this->cartridgeid],
                'id ASC'
            )
        );
        if ($this->editconceptid > 0) {
            $editconcept = $db->get_record(
                'local_playergames_concepts',
                ['id' => $this->editconceptid, 'cartridgeid' => $this->cartridgeid]
            ) ?: null;
        }

        $catnamelookup = [];
        foreach ($categories as $cat) {
            $catnamelookup[(int) $cat->id] = format_string($cat->name);
        }

        $formaction = 'add_concept';
        $formconceptid = 0;
        $formterm = '';
        $formdefinition = '';
        $formcategoryid = 0;
        $formdifficulty = 3;
        $editingconcept = false;

        if ($editconcept !== null) {
            $formaction = 'edit_concept';
            $formconceptid = $editconcept->id;
            $formterm = $editconcept->term;
            $formdefinition = $editconcept->definition;
            $formcategoryid = isset($editconcept->categoryid) ? (int) $editconcept->categoryid : 0;
            $formdifficulty = (int) $editconcept->difficulty;
            $editingconcept = true;
        }

        $categoryoptions = [];
        foreach ($categories as $cat) {
            $categoryoptions[] = [
                'id' => (int) $cat->id,
                'name' => format_string($cat->name),
                'selected' => (int) $cat->id === $formcategoryid,
            ];
        }

        $catrows = [];
        foreach ($categories as $cat) {
            $catrows[] = [
                'id' => (int) $cat->id,
                'name' => format_string($cat->name),
                'cartridgeid' => $this->cartridgeid,
                'sesskey' => sesskey(),
            ];
        }

        $conceptrows = [];
        foreach ($concepts as $concept) {
            $editconcepturl = (new \moodle_url($baseurl, [
                'tab' => 'editor',
                'cartridgeid' => $this->cartridgeid,
                'editconcept' => $concept->id,
            ]))->out(false);
            $catid = isset($concept->categoryid) ? (int) $concept->categoryid : 0;
            $conceptrows[] = [
                'id' => $concept->id,
                'term' => format_string($concept->term),
                'definition' => format_string($concept->definition),
                'category' => $catid > 0 && isset($catnamelookup[$catid])
                    ? $catnamelookup[$catid] : '',
                'difficulty' => (int) $concept->difficulty,
                'edit_url' => $editconcepturl,
                'sesskey' => sesskey(),
                'cartridgeid' => $this->cartridgeid,
            ];
        }

        $cancelurl = (new \moodle_url($baseurl, [
            'tab' => 'editor',
            'cartridgeid' => $this->cartridgeid,
        ]))->out(false);

        $exporturl = (new \moodle_url($baseurl, [
            'action' => 'export_cartridge',
            'cartridgeid' => $this->cartridgeid,
        ]))->out(false);

        $ctx = [
            'action_url' => (new \moodle_url($baseurl))->out(false),
            'sesskey' => sesskey(),
            'has_editor_cartridge' => true,
            'editor_cartridge_id' => $this->cartridgeid,
            'editor_cartridge_name' => format_string($editcartridge->name),
            'export_url' => $exporturl,
            'url_tab_library' => (new \moodle_url($baseurl, ['tab' => 'library']))->out(false),
            'url_cancel_edit' => $cancelurl,
            'editing_concept' => $editingconcept,
            'form_action' => $formaction,
            'form_concept_id' => $formconceptid,
            'form_term' => s($formterm),
            'form_definition' => s($formdefinition),
            'form_categoryid' => $formcategoryid,
            'form_difficulty' => $formdifficulty,
            'categories' => $catrows,
            'categories_empty' => empty($catrows),
            'category_options' => $categoryoptions,
            'concepts' => $conceptrows,
            'concepts_empty' => empty($conceptrows),
        ];
        return $output->render_from_template('local_playergames/cartridge_tab_editor', $ctx);
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
            $successkey = ($result->type ?? 'concept') === 'quiz'
                ? 'cartridge_import_success_quiz'
                : 'cartridge_import_success';
            redirect(
                $this->url(['cartridgeid' => $result->cartridgeid]),
                get_string($successkey, 'local_playergames', $result),
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
        $aicontext = optional_param('ai_context', '', PARAM_TEXT);
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
        $this->aiformdata->context = $aicontext;
        $this->aiformdata->language = $ailanguage;
        $this->aiformdata->quantity = $quantity;
        $this->aiformdata->difficulty = $difficulty;
        $this->aiformdata->categories = $aicategoriesraw;
        $this->tab = 'ai';

        try {
            $gen = new ai_generator();
            $rawconcepts = $gen->generate(
                $topic,
                $ailanguage,
                $quantity,
                $difficulty,
                $categorynames,
                $aicontext
            );
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
        $cartridgetype = optional_param('cartridge_type', 'concept', PARAM_ALPHA);
        $cartridgetype = in_array($cartridgetype, ['concept', 'quiz'], true) ? $cartridgetype : 'concept';
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
        $newcartridge->type = $cartridgetype;
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
        $qids = $DB->get_fieldset_select(
            'local_playergames_concept_questions',
            'id',
            'cartridgeid = :cid',
            ['cid' => $postcartridgeid]
        );
        if (!empty($qids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($qids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_playergames_concept_answers', "questionid $insql", $inparams);
            $DB->delete_records_select(
                'local_playergames_concept_questions',
                "id $insql",
                $inparams
            );
        }
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
     * Handles the generate_ai_quiz POST action.
     *
     * Calls the AI to generate standalone MCQ previews, then re-renders the AI tab
     * with the quiz sub-tab active so the user can review before saving.
     */
    private function action_generate_ai_quiz(): void {
        $topic      = required_param('topic', PARAM_TEXT);
        $language   = optional_param('ai_language', '', PARAM_TEXT);
        $quantity   = max(5, min(50, (int) required_param('quantity', PARAM_INT)));
        $difficulty = max(1, min(5, (int) optional_param('difficulty', 3, PARAM_INT)));
        $aicontext  = optional_param('ai_context', '', PARAM_TEXT);
        $categoriesraw = optional_param('ai_categories', '', PARAM_TEXT);

        $categorynames = [];
        if ($categoriesraw !== '') {
            foreach (explode(',', $categoriesraw) as $catname) {
                $catname = trim(clean_param($catname, PARAM_TEXT));
                if ($catname !== '') {
                    $categorynames[] = $catname;
                }
            }
        }

        $this->aisubtab = 'quiz';
        $this->quizformdata = new \stdClass();
        $this->quizformdata->topic      = $topic;
        $this->quizformdata->language   = $language;
        $this->quizformdata->quantity   = $quantity;
        $this->quizformdata->difficulty = $difficulty;
        $this->quizformdata->context    = $aicontext;
        $this->quizformdata->categories = $categoriesraw;
        $this->tab = 'ai';

        try {
            $gen = new quiz_generator();
            $questions = $gen->generate_preview(
                $topic,
                $language,
                $quantity,
                $difficulty,
                $categorynames,
                $aicontext
            );
            $indexed = [];
            foreach ($questions as $i => $q) {
                $indexed[] = [
                    'index'        => $i,
                    'questiontext' => s($q['questiontext']),
                    'correct'      => s($q['correct']),
                    'distractors'  => array_map('s', $q['distractors']),
                    'category'     => s($q['category'] ?? ''),
                    'difficulty'   => max(1, min(5, (int) ($q['difficulty'] ?? 3))),
                ];
            }
            $this->quizpreview = [
                'cartridge_name' => $topic,
                'language'       => $language,
                'questions'      => $indexed,
            ];
        } catch (\moodle_exception $e) {
            $this->quizerror = $e->getMessage();
        }
    }

    /**
     * Handles the save_ai_quiz_cartridge POST action.
     *
     * Creates a quiz-type cartridge and saves the AI-generated MCQs to it.
     */
    private function action_save_ai_quiz_cartridge(): void {
        global $DB, $USER;

        $cartridgename  = required_param('ai_cartridge_name', PARAM_TEXT);
        $cartridgelang  = optional_param('ai_language', '', PARAM_TEXT);
        $aiauthor       = optional_param('ai_author', '', PARAM_TEXT);
        $rawquestions   = isset($_POST['questions']) && is_array($_POST['questions'])
            ? $_POST['questions'] : [];

        $newcartridge               = new \stdClass();
        $newcartridge->name         = \core_text::substr(
            clean_param($cartridgename, PARAM_TEXT),
            0,
            255
        );
        $newcartridge->version      = '1.0';
        $newcartridge->language     = \core_text::substr(
            clean_param($cartridgelang, PARAM_TEXT),
            0,
            20
        );
        $newcartridge->author       = $aiauthor !== ''
            ? \core_text::substr(clean_param($aiauthor, PARAM_TEXT), 0, 255) : null;
        $newcartridge->type         = 'quiz';
        $newcartridge->active       = 1;
        $now = time();
        $newcartridge->timecreated  = $now;
        $newcartridge->timemodified = $now;
        $newcartridge->uploadedby   = (int) $USER->id;
        $newcartridgeid = $DB->insert_record('local_playergames_cartridges', $newcartridge);

        $questions = [];
        foreach ($rawquestions as $q) {
            $qtext       = trim(clean_param($q['questiontext'] ?? '', PARAM_TEXT));
            $correct     = trim(clean_param($q['correct'] ?? '', PARAM_TEXT));
            $distractors = [];
            foreach ((array) ($q['distractors'] ?? []) as $d) {
                $distractors[] = trim(clean_param($d, PARAM_TEXT));
            }
            if ($qtext !== '' && $correct !== '' && count($distractors) >= 4) {
                $questions[] = [
                    'questiontext' => $qtext,
                    'correct'      => $correct,
                    'distractors'  => array_slice($distractors, 0, 4),
                    'category'     => trim(clean_param($q['category'] ?? '', PARAM_TEXT)),
                    'difficulty'   => max(1, min(5, (int) ($q['difficulty'] ?? 3))),
                ];
            }
        }

        $gen = new quiz_generator();
        $gen->save_standalone($newcartridgeid, $questions);

        $event = cartridge_imported::create([
            'context'  => $this->context,
            'objectid' => $newcartridgeid,
        ]);
        $event->trigger();

        redirect(
            $this->url(['cartridgeid' => $newcartridgeid]),
            get_string('cartridge_created', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
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

    /**
     * Renders the quiz question editor tab for a quiz-type cartridge.
     *
     * @param \renderer_base $output The active renderer.
     * @param \moodle_database $db Database instance.
     * @param string $baseurl Base page URL string.
     * @param \stdClass $cartridge The quiz cartridge record.
     * @return string Rendered HTML.
     */
    private function render_tab_quiz_editor(
        \renderer_base $output,
        \moodle_database $db,
        string $baseurl,
        \stdClass $cartridge
    ): string {
        $questions = $db->get_records(
            'local_playergames_concept_questions',
            ['cartridgeid' => (int) $cartridge->id],
            'id ASC'
        );

        $allqids = array_keys($questions);
        $answersbyquestion = [];
        if (!empty($allqids)) {
            [$insql, $inparams] = $db->get_in_or_equal($allqids);
            $answers = $db->get_records_sql(
                "SELECT * FROM {local_playergames_concept_answers}
                  WHERE questionid {$insql}
                  ORDER BY sortorder ASC",
                $inparams
            );
            foreach ($answers as $ans) {
                $answersbyquestion[(int) $ans->questionid][] = $ans;
            }
        }

        $catmgr = new category_manager();
        $categories = $catmgr->get_categories((int) $cartridge->id);
        $catnamelookup = [];
        foreach ($categories as $cat) {
            $catnamelookup[(int) $cat->id] = format_string($cat->name);
        }

        $editquestion = null;
        if ($this->editquestionid > 0) {
            $editquestion = $db->get_record(
                'local_playergames_concept_questions',
                ['id' => $this->editquestionid, 'cartridgeid' => (int) $cartridge->id]
            ) ?: null;
        }

        $formaction = 'add_quiz_question';
        $formquestionid = 0;
        $formquestiontext = '';
        $formcorrect = '';
        $formdistractors = ['', '', '', ''];
        $formcategoryid = 0;
        $formdifficulty = 3;
        $formfeedback = '';
        $editingquestion = false;

        if ($editquestion !== null) {
            $formaction = 'edit_quiz_question';
            $formquestionid = (int) $editquestion->id;
            $formquestiontext = $editquestion->questiontext;
            $formcategoryid = isset($editquestion->categoryid) ? (int) $editquestion->categoryid : 0;
            $formdifficulty = (int) $editquestion->difficulty;
            $formfeedback = (string) ($editquestion->generalfeedback ?? '');
            $editingquestion = true;
            $qanswers = $answersbyquestion[(int) $editquestion->id] ?? [];
            $di = 0;
            foreach ($qanswers as $ans) {
                if ($ans->iscorrect) {
                    $formcorrect = $ans->answertext;
                } else if ($di < 4) {
                    $formdistractors[$di++] = $ans->answertext;
                }
            }
        }

        $categoryoptions = [];
        foreach ($categories as $cat) {
            $categoryoptions[] = [
                'id' => (int) $cat->id,
                'name' => format_string($cat->name),
                'selected' => (int) $cat->id === $formcategoryid,
            ];
        }

        $catrows = [];
        foreach ($categories as $cat) {
            $catrows[] = [
                'id' => (int) $cat->id,
                'name' => format_string($cat->name),
                'cartridgeid' => (int) $cartridge->id,
                'sesskey' => sesskey(),
            ];
        }

        $questionrows = [];
        foreach ($questions as $q) {
            $qanswers = $answersbyquestion[(int) $q->id] ?? [];
            $correct = '';
            $distractors = [];
            foreach ($qanswers as $ans) {
                if ($ans->iscorrect) {
                    $correct = s($ans->answertext);
                } else {
                    $distractors[] = s($ans->answertext);
                }
            }
            $editurl = (new \moodle_url($baseurl, [
                'tab' => 'editor',
                'cartridgeid' => (int) $cartridge->id,
                'editquestion' => (int) $q->id,
            ]))->out(false);
            $qcatid = isset($q->categoryid) ? (int) $q->categoryid : 0;
            $questionrows[] = [
                'id' => (int) $q->id,
                'questiontext' => format_string($q->questiontext),
                'correct' => $correct,
                'distractors' => $distractors,
                'category' => $qcatid > 0 && isset($catnamelookup[$qcatid])
                    ? $catnamelookup[$qcatid] : '',
                'difficulty' => (int) $q->difficulty,
                'edit_url' => $editurl,
                'sesskey' => sesskey(),
                'cartridgeid' => (int) $cartridge->id,
            ];
        }

        $cancelurl = (new \moodle_url($baseurl, [
            'tab' => 'editor',
            'cartridgeid' => (int) $cartridge->id,
        ]))->out(false);

        $ctx = [
            'action_url' => (new \moodle_url($baseurl))->out(false),
            'sesskey' => sesskey(),
            'has_editor_cartridge' => true,
            'editor_cartridge_id' => (int) $cartridge->id,
            'editor_cartridge_name' => format_string($cartridge->name),
            'export_url' => (new \moodle_url($baseurl, [
                'action' => 'export_cartridge',
                'cartridgeid' => (int) $cartridge->id,
            ]))->out(false),
            'url_tab_library' => (new \moodle_url($baseurl, ['tab' => 'library']))->out(false),
            'url_cancel_edit' => $cancelurl,
            'editing_question' => $editingquestion,
            'form_action' => $formaction,
            'form_question_id' => $formquestionid,
            'form_questiontext' => s($formquestiontext),
            'form_correct' => s($formcorrect),
            'form_distractor_0' => s($formdistractors[0]),
            'form_distractor_1' => s($formdistractors[1]),
            'form_distractor_2' => s($formdistractors[2]),
            'form_distractor_3' => s($formdistractors[3]),
            'form_categoryid' => $formcategoryid,
            'form_difficulty' => $formdifficulty,
            'form_generalfeedback' => s($formfeedback),
            'category_options' => $categoryoptions,
            'categories' => $catrows,
            'categories_empty' => empty($catrows),
            'questions' => $questionrows,
            'questions_empty' => empty($questionrows),
        ];
        return $output->render_from_template('local_playergames/cartridge_tab_quiz_editor', $ctx);
    }

    /**
     * Validates that a category belongs to the cartridge before it is stored.
     *
     * @param int $categoryid Posted category id (0 = none).
     * @param int $cartridgeid Owning cartridge id.
     * @return int|null The category id if it belongs to the cartridge, otherwise null.
     */
    private function valid_category_id(int $categoryid, int $cartridgeid): ?int {
        global $DB;
        if ($categoryid <= 0) {
            return null;
        }
        $exists = $DB->record_exists(
            'local_playergames_categories',
            ['id' => $categoryid, 'cartridgeid' => $cartridgeid]
        );
        return $exists ? $categoryid : null;
    }

    /**
     * Handles the add_quiz_question POST action.
     */
    private function action_add_quiz_question(): void {
        global $DB;

        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $cartridge = $DB->get_record(
            'local_playergames_cartridges',
            ['id' => $postcartridgeid, 'type' => 'quiz'],
            '*',
            MUST_EXIST
        );

        $qtext = trim(clean_param(required_param('questiontext', PARAM_TEXT), PARAM_TEXT));
        $correct = trim(clean_param(required_param('correct', PARAM_TEXT), PARAM_TEXT));
        $categoryid = $this->valid_category_id(
            optional_param('categoryid', 0, PARAM_INT),
            $postcartridgeid
        );
        $difficulty = max(1, min(5, optional_param('difficulty', 3, PARAM_INT)));
        $feedback = trim(clean_param(optional_param('generalfeedback', '', PARAM_TEXT), PARAM_TEXT));
        $distractors = [];
        for ($i = 0; $i < 4; $i++) {
            $d = trim(clean_param(optional_param("distractor_{$i}", '', PARAM_TEXT), PARAM_TEXT));
            $distractors[] = $d;
        }

        if ($qtext === '' || $correct === '') {
            redirect(
                $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
                get_string('error_concept_empty_term', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        $now = time();
        $qrecord = new \stdClass();
        $qrecord->conceptid = null;
        $qrecord->cartridgeid = $postcartridgeid;
        $qrecord->questiontext = $qtext;
        $qrecord->source = 'manual';
        $qrecord->difficulty = $difficulty;
        $qrecord->categoryid = $categoryid;
        $qrecord->generalfeedback = $feedback !== '' ? $feedback : null;
        $qrecord->timecreated = $now;
        $questionid = (int) $DB->insert_record('local_playergames_concept_questions', $qrecord);

        $correctrec = new \stdClass();
        $correctrec->questionid = $questionid;
        $correctrec->answertext = $correct;
        $correctrec->iscorrect = 1;
        $correctrec->sortorder = 0;
        $DB->insert_record('local_playergames_concept_answers', $correctrec, false);

        foreach ($distractors as $i => $dtext) {
            $dist = new \stdClass();
            $dist->questionid = $questionid;
            $dist->answertext = $dtext;
            $dist->iscorrect = 0;
            $dist->sortorder = $i + 1;
            $DB->insert_record('local_playergames_concept_answers', $dist, false);
        }

        $DB->set_field(
            'local_playergames_cartridges',
            'timemodified',
            $now,
            ['id' => $postcartridgeid]
        );
        redirect(
            $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
            get_string('quiz_editor_question_saved', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Handles the edit_quiz_question POST action.
     */
    private function action_edit_quiz_question(): void {
        global $DB;

        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $postquestionid = required_param('question_id', PARAM_INT);
        $DB->get_record(
            'local_playergames_concept_questions',
            ['id' => $postquestionid, 'cartridgeid' => $postcartridgeid],
            '*',
            MUST_EXIST
        );

        $qtext = trim(clean_param(required_param('questiontext', PARAM_TEXT), PARAM_TEXT));
        $correct = trim(clean_param(required_param('correct', PARAM_TEXT), PARAM_TEXT));
        $categoryid = $this->valid_category_id(
            optional_param('categoryid', 0, PARAM_INT),
            $postcartridgeid
        );
        $difficulty = max(1, min(5, optional_param('difficulty', 3, PARAM_INT)));
        $feedback = trim(clean_param(optional_param('generalfeedback', '', PARAM_TEXT), PARAM_TEXT));
        $distractors = [];
        for ($i = 0; $i < 4; $i++) {
            $d = trim(clean_param(optional_param("distractor_{$i}", '', PARAM_TEXT), PARAM_TEXT));
            $distractors[] = $d;
        }

        if ($qtext === '' || $correct === '') {
            redirect(
                $this->url([
                    'tab' => 'editor',
                    'cartridgeid' => $postcartridgeid,
                    'editquestion' => $postquestionid,
                ]),
                get_string('error_concept_empty_term', 'local_playergames'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        $updated = new \stdClass();
        $updated->id = $postquestionid;
        $updated->questiontext = $qtext;
        $updated->difficulty = $difficulty;
        $updated->categoryid = $categoryid;
        $updated->generalfeedback = $feedback !== '' ? $feedback : null;
        $DB->update_record('local_playergames_concept_questions', $updated);
        $DB->delete_records('local_playergames_concept_answers', ['questionid' => $postquestionid]);

        $correctrec = new \stdClass();
        $correctrec->questionid = $postquestionid;
        $correctrec->answertext = $correct;
        $correctrec->iscorrect = 1;
        $correctrec->sortorder = 0;
        $DB->insert_record('local_playergames_concept_answers', $correctrec, false);

        foreach ($distractors as $i => $dtext) {
            $dist = new \stdClass();
            $dist->questionid = $postquestionid;
            $dist->answertext = $dtext;
            $dist->iscorrect = 0;
            $dist->sortorder = $i + 1;
            $DB->insert_record('local_playergames_concept_answers', $dist, false);
        }

        $DB->set_field(
            'local_playergames_cartridges',
            'timemodified',
            time(),
            ['id' => $postcartridgeid]
        );
        redirect(
            $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
            get_string('quiz_editor_question_saved', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    /**
     * Handles the delete_quiz_question POST action.
     */
    private function action_delete_quiz_question(): void {
        global $DB;

        $postcartridgeid = required_param('cartridgeid', PARAM_INT);
        $postquestionid = required_param('question_id', PARAM_INT);
        $DB->delete_records('local_playergames_concept_answers', ['questionid' => $postquestionid]);
        $DB->delete_records(
            'local_playergames_concept_questions',
            ['id' => $postquestionid, 'cartridgeid' => $postcartridgeid]
        );
        $DB->set_field(
            'local_playergames_cartridges',
            'timemodified',
            time(),
            ['id' => $postcartridgeid]
        );
        redirect(
            $this->url(['tab' => 'editor', 'cartridgeid' => $postcartridgeid]),
            get_string('quiz_editor_question_deleted', 'local_playergames'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}
