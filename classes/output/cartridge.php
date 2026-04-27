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

    /** @var stdClass|null Concept pre-filling the edit form, or null. */
    private ?stdClass $editconcept;

    /** @var array|null AI preview data or null if no generation was performed. */
    private ?array $ai_preview;

    /** @var stdClass|null Form data repopulated after AI generation. */
    private ?stdClass $ai_form_data;

    /** @var string Error message from a failed AI generation, or empty string. */
    private string $ai_error;

    /** @var bool Whether at least one AI provider key is configured. */
    private bool $has_ai_key;

    /**
     * Constructor.
     *
     * @param string $tab Active tab slug.
     * @param array $cartridges All cartridge records with concept counts.
     * @param stdClass|null $editcartridge Cartridge being edited, or null.
     * @param array $concepts Concepts belonging to the edit cartridge.
     * @param stdClass|null $editconcept Concept whose data should pre-fill the form.
     * @param array|null $ai_preview Preview concepts from AI generation, or null.
     * @param stdClass|null $ai_form_data Form values to repopulate after generation.
     * @param string $ai_error AI error message, or empty string.
     * @param bool $has_ai_key Whether a key is configured for any AI provider.
     */
    public function __construct(
        string $tab,
        array $cartridges,
        ?stdClass $editcartridge,
        array $concepts,
        ?stdClass $editconcept,
        ?array $ai_preview,
        ?stdClass $ai_form_data,
        string $ai_error,
        bool $has_ai_key
    ) {
        $this->tab = $tab;
        $this->cartridges = $cartridges;
        $this->editcartridge = $editcartridge;
        $this->concepts = $concepts;
        $this->editconcept = $editconcept;
        $this->ai_preview = $ai_preview;
        $this->ai_form_data = $ai_form_data;
        $this->ai_error = $ai_error;
        $this->has_ai_key = $has_ai_key;
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
        foreach ($this->concepts as $concept) {
            $editconcepturl = (new moodle_url('/local/playergames/cartridge.php', [
                'tab' => 'editor',
                'cartridgeid' => $this->editcartridge ? $this->editcartridge->id : 0,
                'editconcept' => $concept->id,
            ]))->out(false);
            $conceptrows[] = [
                'id' => $concept->id,
                'term' => format_string($concept->term),
                'definition' => format_string($concept->definition),
                'category' => format_string($concept->category ?? ''),
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
        $formcategory = '';
        $formdifficulty = 3;
        $editingconcept = false;

        if ($this->editconcept !== null) {
            $formaction = 'edit_concept';
            $formconceptid = $this->editconcept->id;
            $formterm = $this->editconcept->term;
            $formdefinition = $this->editconcept->definition;
            $formcategory = $this->editconcept->category ?? '';
            $formdifficulty = (int) $this->editconcept->difficulty;
            $editingconcept = true;
        }

        $aipreviewdata = null;
        if ($this->ai_preview !== null) {
            $aipreviewdata = [
                'cartridge_name' => s($this->ai_preview['cartridge_name']),
                'language' => s($this->ai_preview['language']),
                'concepts' => $this->ai_preview['concepts'],
                'count' => count($this->ai_preview['concepts']),
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
            'has_ai_key' => $this->has_ai_key,
            'ai_preview' => $aipreviewdata,
            'has_ai_preview' => $aipreviewdata !== null,
            'ai_form_topic' => $this->ai_form_data ? s($this->ai_form_data->topic) : '',
            'ai_form_language' => $this->ai_form_data ? s($this->ai_form_data->language) : '',
            'ai_form_quantity' => $this->ai_form_data ? (int) $this->ai_form_data->quantity : 20,
            'ai_form_difficulty' => $this->ai_form_data ? (int) $this->ai_form_data->difficulty : 3,
            'ai_error' => s($this->ai_error),
            'has_ai_error' => $this->ai_error !== '',
            'editor_cartridge_id' => $this->editcartridge ? $this->editcartridge->id : 0,
            'editor_cartridge_name' => $this->editcartridge
                ? format_string($this->editcartridge->name) : '',
            'has_editor_cartridge' => $this->editcartridge !== null,
            'concepts' => $conceptrows,
            'concepts_empty' => empty($conceptrows),
            'form_action' => $formaction,
            'form_concept_id' => $formconceptid,
            'form_term' => s($formterm),
            'form_definition' => s($formdefinition),
            'form_category' => s($formcategory),
            'form_difficulty' => $formdifficulty,
            'editing_concept' => $editingconcept,
        ];
    }
}
