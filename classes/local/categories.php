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
 * course.category rather than a LIKE on the category path (L6). The administrator's "categories the hub works in"
 * (C1) are resolved here too: the universe every view, code rule, added module and person search stays within.
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
        $cache = self::cache();
        $all = $cache->get('all');
        if ($all === false) {
            $all = $DB->get_records('course_categories', null, 'sortorder ASC', 'id, name, parent, depth, path, visible');
            $cache->set('all', $all);
        }
        return $all;
    }

    /**
     * Forgets the tree and the setting read in this request: after a category or the setting changes mid-request
     * (the administrator saving, a test).
     */
    public static function reset(): void {
        self::cache()->purge();
    }

    /**
     * The request cache.
     *
     * @return \cache
     */
    protected static function cache(): \cache {
        return \cache::make_from_params(\cache_store::MODE_REQUEST, 'block_le_hub_dashboard', 'categories');
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
     * The categories the hub works in (C1), with everything under them, as ids; null when the administrator has
     * not limited them, so every category counts.
     *
     * @return int[]|null
     */
    public static function valid(): ?array {
        $cache = self::cache();
        $valid = $cache->get('valid');
        if ($valid === false) {
            $picked = config::categories();
            $valid = $picked ? self::with_descendants($picked) : null;
            $cache->set('valid', $valid);
        }
        return $valid;
    }

    /**
     * Whether a category is inside the hub's categories (C1).
     *
     * @param int $catid
     * @return bool
     */
    public static function inside(int $catid): bool {
        $valid = self::valid();
        return $valid === null || in_array($catid, $valid, true);
    }

    /**
     * SQL that keeps a course inside the hub's categories (C1), or null when they are not limited.
     *
     * @param string $alias The course table's alias.
     * @param string $prefix A parameter prefix unique in the statement.
     * @return array|null [sql, params]
     */
    public static function limit_sql(string $alias = 'co', string $prefix = 'hubcat'): ?array {
        global $DB;
        $valid = self::valid();
        if ($valid === null) {
            return null;
        }
        [$insql, $params] = $DB->get_in_or_equal($valid, SQL_PARAMS_NAMED, $prefix);
        return ["$alias.category $insql", $params];
    }

    /**
     * The minimal cover of some categories (C3): a category whose ancestor is also picked is dropped, since the
     * ancestor already covers it. What a view or the setting saves.
     *
     * @param int[] $ids
     * @return int[]
     */
    public static function cover(array $ids): array {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $all = self::all();
        $picked = array_fill_keys($ids, true);
        $out = [];
        foreach ($ids as $id) {
            $covered = false;
            $parent = isset($all[$id]) ? (int)$all[$id]->parent : 0;
            while ($parent && isset($all[$parent])) {
                if (isset($picked[$parent])) {
                    $covered = true;
                    break;
                }
                $parent = (int)$all[$parent]->parent;
            }
            if (!$covered) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /**
     * The tree for a picker: the categories in the site's order with their depth and parent. Limited to the hub's
     * categories (C1) unless asked for the whole site, as the administrator's own picker is.
     *
     * @param bool $limited
     * @return array[] [id, name, depth, parent]
     */
    public static function tree(bool $limited = true): array {
        $valid = $limited ? self::valid() : null;
        $shown = [];
        foreach (self::all() as $cat) {
            if ($valid === null || in_array((int)$cat->id, $valid, true)) {
                $shown[(int)$cat->id] = $cat;
            }
        }
        $out = [];
        foreach ($shown as $cat) {
            // Depth and parent within what is shown, so a limited tree starts at the left.
            $depth = 1;
            $parent = (int)$cat->parent;
            $shownparent = 0;
            while ($parent && isset(self::all()[$parent])) {
                if (isset($shown[$parent])) {
                    $depth++;
                    $shownparent = $shownparent ?: $parent;
                }
                $parent = (int)self::all()[$parent]->parent;
            }
            $out[] = ['id' => (int)$cat->id, 'name' => format_string($cat->name, true, ['escape' => false]),
                'depth' => $depth, 'parent' => $shownparent];
        }
        return $out;
    }
}
