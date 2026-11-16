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

namespace block_le_hub_dashboard\admin;

use block_le_hub_dashboard\local\categories;

/**
 * The "categories the hub works in" setting (C1): the site's category tree as checkboxes, saved as a comma list of
 * ids. Rendered by the catpicker template the view editor shares.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_categories extends \admin_setting {
    /**
     * Constructor: no default, so a new or upgraded site works in every category until the administrator picks.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     */
    public function __construct(string $name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, '');
    }

    #[\Override]
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Saves the ticked categories; anything that is not a category id is dropped.
     *
     * @param mixed $data The ticked ids, or the list as a string.
     * @return string Empty on success.
     */
    #[\Override]
    public function write_setting($data) {
        if (!is_array($data)) {
            $data = explode(',', (string)$data);
        }
        $known = array_map(fn($cat) => (int)$cat->id, categories::all());
        $ids = [];
        foreach ($data as $value) {
            if (is_numeric($value) && in_array((int)$value, $known, true)) {
                $ids[] = (int)$value;
            }
        }
        $ok = $this->config_write($this->name, implode(',', array_unique($ids)));
        categories::reset();
        return $ok ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * The tree of checkboxes, every category of the site, the picked ones ticked.
     *
     * @param mixed $data The current value.
     * @param string $query
     * @return string
     */
    #[\Override]
    public function output_html($data, $query = '') {
        global $OUTPUT;
        $picked = array_map('intval', array_filter(explode(',', (string)$data), 'is_numeric'));
        $tree = categories::tree(false);
        foreach ($tree as &$cat) {
            $cat['checked'] = in_array($cat['id'], $picked, true);
        }
        unset($cat);
        $context = [
            'id' => $this->get_id(),
            'field' => $this->get_full_name(),
            'categories' => $tree,
            'hascategories' => !empty($tree),
        ];
        $html = $OUTPUT->render_from_template('block_le_hub_dashboard/catpicker', $context);
        return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', '', $query);
    }

    #[\Override]
    public function is_related($query) {
        if (parent::is_related($query)) {
            return true;
        }
        foreach (categories::all() as $cat) {
            if (stripos($cat->name, $query) !== false) {
                return true;
            }
        }
        return false;
    }
}
