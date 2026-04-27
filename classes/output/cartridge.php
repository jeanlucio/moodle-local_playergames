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
use stdClass;

/**
 * Exports data for the cartridge management Mustache template.
 */
class cartridge implements renderable, templatable {
    /** @var string Active tab slug: 'import', 'ai', or 'editor'. */
    private string $tab;

    /** @var array All cartridge records enriched with concept counts. */
    private array $cartridges;

    /** @var stdClass|null Cartridge being edited in the editor tab, or null. */
    private ?stdClass $editcartridge;

    /** @var array Concepts for the cartridge being edited. */
    private array $concepts;

    /** @var array Category records for the cartridge being edited. */
    private array $categories;

    /** @var stdClass|null Concept pre-filling the edit form, or null. */
    private ?stdClass $editconcept;

    /** @var array|null AI preview data or null if no generation was performed. */
    private ?array $aipreview;

    /** @var stdClass|null Form data repopulated after AI generation. */
    private ?stdClass $aiformdata;

    /** @var string Error message from a failed AI generation, or empty string. */
    private string $aierror;

    /** @var bool Whether at least one AI provider key is configured. */
    private bool $hasaikey;

    /**
     * Constructor.
     *
     * @param string $tab Active tab slug.
     * @param array $cartridges All cartridge records with concept counts.
     * @param stdClass|null $editcartridge Cartridge being edited, or null.
     * @param array $concepts Concepts belonging to the edit cartridge.
     * @param array $categories Category records belonging to the edit cartridge.
     * @param stdClass|null $editconcept Concept whose data should pre-fill the form.
     * @param array|null $aipreview Preview concepts from AI generation, or null.
     * @param stdClass|null $aiformdata Form values to repopulate after generation.
     * @param string $aierror AI error message, or empty string.
     * @param bool $hasaikey Whether a key is configured for any AI provider.
     */
    public function __construct(
        string $tab,
        array $cartridges,
        ?stdClass $editcartridge,
        array $concepts,
        array $categories,
        ?stdClass $editconcept,
        ?array $aipreview,
        ?stdClass $aiformdata,
        string $aierror,
        bool $hasaikey
    ) {
        $this->tab = $tab;
        $this->cartridges = $cartridges;
        $this->editcartridge = $editcartridge;
        $this->concepts = $concepts;
        $this->categories = $categories;
        $this->editconcept = $editconcept;
        $this->aipreview = $aipreview;
        $this->aiformdata = $aiformdata;
        $this->aierror = $aierror;
        $this->hasaikey = $hasaikey;
    }

    /**
     * Exports the data needed by the cartridge Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template context data.
     */
    public function export_for_template(renderer_base $output): array {
        $actionurl = (new moodle_url('/local/playergames/cartridge.php'))->out(false);

        $cartridgerows = [];
        foreach ($this->cartridges as $cartridge) {
            $editurl = (new moodle_url('/local/playergames/cartridge.php', [
                'tab' => 'editor',
                'cartridgeid' => $cartridge->id,
            ]))->out(false);
            $exporturl = (new moodle_url('/local/playergames/cartridge.php', [
                'action' => 'export_cartridge',
                'cartridgeid' => $cartridge->id,
            ]))->out(false);
            $cartridgerows[] = [
                'id' => $cartridge->id,
                'name' => format_string($cartridge->name),
                'language' => s($cartridge->language),
                'version' => s($cartridge->version),
                'concepts_count' => $cartridge->concepts_count ?? 0,
                'is_active' => (bool) $cartridge->active,
                'is_inactive' => !(bool) $cartridge->active,
                'uploaded_date' => userdate(
                    $cartridge->timeuploaded,
                    get_string('strftimedatetimeshort', 'core_langconfig')
                ),
                'uploadedby_fullname' => format_string($cartridge->uploadedby_fullname ?? ''),
                'edit_url' => $editurl,
                'export_url' => $exporturl,
                'sesskey' => sesskey(),
            ];
        }

        $conceptrows = [];
        // Build a category name lookup keyed by ID.
        $catnamelookup = [];
        foreach ($this->categories as $cat) {
            $catnamelookup[(int) $cat->id] = format_string($cat->name);
        }
        foreach ($this->concepts as $concept) {
            $editconcepturl = (new moodle_url('/local/playergames/cartridge.php', [
                'tab' => 'editor',
                'cartridgeid' => $this->editcartridge ? $this->editcartridge->id : 0,
                'editconcept' => $concept->id,
            ]))->out(false);
            $catid = isset($concept->categoryid) ? (int) $concept->categoryid : 0;
            $conceptrows[] = [
                'id' => $concept->id,
                'term' => format_string($concept->term),
                'definition' => format_string($concept->definition),
                'category' => $catid > 0 && isset($catnamelookup[$catid])
                    ? $catnamelookup[$catid]
                    : '',
                'difficulty' => (int) $concept->difficulty,
                'edit_url' => $editconcepturl,
                'sesskey' => sesskey(),
                'cartridgeid' => $this->editcartridge ? $this->editcartridge->id : 0,
            ];
        }

        $formaction = 'add_concept';
        $formconceptid = 0;
        $formterm = '';
        $formdefinition = '';
        $formcategoryid = 0;
        $formdifficulty = 3;
        $editingconcept = false;

        if ($this->editconcept !== null) {
            $formaction = 'edit_concept';
            $formconceptid = $this->editconcept->id;
            $formterm = $this->editconcept->term;
            $formdefinition = $this->editconcept->definition;
            $formcategoryid = isset($this->editconcept->categoryid)
                ? (int) $this->editconcept->categoryid
                : 0;
            $formdifficulty = (int) $this->editconcept->difficulty;
            $editingconcept = true;
        }

        // Build category select options (with selected flag).
        $categoryoptions = [];
        foreach ($this->categories as $cat) {
            $categoryoptions[] = [
                'id' => (int) $cat->id,
                'name' => format_string($cat->name),
                'selected' => (int) $cat->id === $formcategoryid,
            ];
        }

        // Build category rows for the management table.
        $catrows = [];
        foreach ($this->categories as $cat) {
            $catrows[] = [
                'id' => (int) $cat->id,
                'name' => format_string($cat->name),
                'cartridgeid' => $this->editcartridge ? $this->editcartridge->id : 0,
                'sesskey' => sesskey(),
            ];
        }

        $aipreviewdata = null;
        if ($this->aipreview !== null) {
            $aipreviewdata = [
                'cartridge_name' => s($this->aipreview['cartridge_name']),
                'language' => s($this->aipreview['language']),
                'concepts' => $this->aipreview['concepts'],
                'count' => count($this->aipreview['concepts']),
            ];
        }

        return [
            'pagetitle' => get_string('cartridge_pagetitle', 'local_playergames'),
            'heading' => get_string('cartridge_heading', 'local_playergames'),
            'action_url' => $actionurl,
            'sesskey' => sesskey(),
            'cartridges' => $cartridgerows,
            'cartridges_empty' => empty($cartridgerows),
            'tab_import_active' => $this->tab === 'import',
            'tab_ai_active' => $this->tab === 'ai',
            'tab_editor_active' => $this->tab === 'editor',
            'has_ai_key' => $this->hasaikey,
            'ai_preview' => $aipreviewdata,
            'has_ai_preview' => $aipreviewdata !== null,
            'ai_form_topic' => $this->aiformdata ? s($this->aiformdata->topic) : '',
            'ai_form_language' => $this->aiformdata ? s($this->aiformdata->language) : '',
            'ai_form_quantity' => $this->aiformdata ? (int) $this->aiformdata->quantity : 20,
            'ai_form_difficulty' => $this->aiformdata ? (int) $this->aiformdata->difficulty : 3,
            'ai_form_categories' => $this->aiformdata
                ? s($this->aiformdata->categories ?? '') : '',
            'ai_error' => s($this->aierror),
            'has_ai_error' => $this->aierror !== '',
            'editor_cartridge_id' => $this->editcartridge ? $this->editcartridge->id : 0,
            'editor_cartridge_name' => $this->editcartridge
                ? format_string($this->editcartridge->name) : '',
            'has_editor_cartridge' => $this->editcartridge !== null,
            'categories' => $catrows,
            'categories_empty' => empty($catrows),
            'category_options' => $categoryoptions,
            'concepts' => $conceptrows,
            'concepts_empty' => empty($conceptrows),
            'form_action' => $formaction,
            'form_concept_id' => $formconceptid,
            'form_term' => s($formterm),
            'form_definition' => s($formdefinition),
            'form_categoryid' => $formcategoryid,
            'form_difficulty' => $formdifficulty,
            'editing_concept' => $editingconcept,
        ];
    }
}
