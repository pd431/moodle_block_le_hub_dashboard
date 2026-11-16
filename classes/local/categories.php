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

namespace block_le_hub_dashboard\local;

/**
 * The site's course categories as a tree, read once per request: which categories sit under which, in the site's
 * order. The view match resolves "this category and everything under it" here, in PHP, so its SQL is an IN on
 * course.category rather than a LIKE on the category path (L6).
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class categories {
    /**
     * Every category, in sortorder: id => {id, name, parent, depth, path, visible}.
     *
     * @return \stdClass[]
     */
    public static function all(): array {
        global $DB;
        $cache = \cache::make_from_params(\cache_store::MODE_REQUEST, 'block_le_hub_dashboard', 'categories');
        $all = $cache->get('all');
        if ($all === false) {
            $all = $DB->get_records('course_categories', null, 'sortorder ASC', 'id, name, parent, depth, path, visible');
            $cache->set('all', $all);
        }
        return $all;
    }

    /**
     * The given categories and every category under them, as ids. An id the site no longer has is kept, so a
     * view that names it still matches nothing there rather than everything.
     *
     * @param int[] $ids
     * @return int[]
     */
    public static function with_descendants(array $ids): array {
        $ids = array_map('intval', $ids);
        if (!$ids) {
            return [];
        }
        $children = [];
        foreach (self::all() as $cat) {
            $children[(int)$cat->parent][] = (int)$cat->id;
        }
        $out = array_fill_keys($ids, true);
        $queue = $ids;
        while ($queue) {
            $id = array_pop($queue);
            foreach ($children[$id] ?? [] as $child) {
                if (!isset($out[$child])) {
                    $out[$child] = true;
                    $queue[] = $child;
                }
            }
        }
        return array_keys($out);
    }

    /**
     * The tree for a picker: every category in the site's order with its depth and parent.
     *
     * @return array[] [id, name, depth, parent]
     */
    public static function tree(): array {
        $out = [];
        foreach (self::all() as $cat) {
            $out[] = ['id' => (int)$cat->id, 'name' => format_string($cat->name, true, ['escape' => false]),
                'depth' => (int)$cat->depth, 'parent' => (int)$cat->parent];
        }
        return $out;
    }
}
