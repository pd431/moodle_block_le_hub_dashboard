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
 * Saved views (D6, P6, P7, P10): personal lists of departments, module code rules, modules added by code, modules
 * taken out and assessments ignored, each with its allocation window. This class owns which assessments a view
 * holds, in an SQL form for the index query and a PHP form for one course, and the views' storage.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class views {
    /** The views table. */
    const TABLE = 'block_le_hub_dashboard_view';
    /** The rules table. */
    const RULES = 'block_le_hub_dashboard_rule';

    /** Rule kinds: what shapes a view, and the exceptions. */
    const KINDS = ['cat', 'starts', 'contains', 'course', 'outcourse', 'outcm'];
    /** The kinds the view editor's rule lines set together. */
    const SHAPE = ['cat', 'starts', 'contains', 'course'];

    /**
     * A user's views, by name, each with its rules grouped by kind.
     *
     * @param int $userid
     * @return \stdClass[] Keyed by id.
     */
    public static function all(int $userid): array {
        global $DB;
        $views = $DB->get_records(self::TABLE, ['userid' => $userid], 'name ASC, id ASC');
        if (!$views) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($views), SQL_PARAMS_NAMED);
        $rules = $DB->get_records_select(self::RULES, "viewid $insql", $params, 'id ASC');
        foreach ($views as $view) {
            self::group_rules($view, []);
        }
        foreach ($rules as $rule) {
            $views[$rule->viewid]->{$rule->kind}[] = $rule->kind === 'starts' || $rule->kind === 'contains'
                ? $rule->value : (int)$rule->value;
        }
        return $views;
    }

    /**
     * Gives a view record empty rule lists.
     *
     * @param \stdClass $view
     * @param array $rules
     */
    protected static function group_rules(\stdClass $view, array $rules): void {
        foreach (self::KINDS as $kind) {
            $view->$kind = $rules[$kind] ?? [];
        }
    }

    /**
     * One of the user's views, or null.
     *
     * @param int $id
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get(int $id, int $userid): ?\stdClass {
        return self::all($userid)[$id] ?? null;
    }

    /**
     * The view the user has open: the last used, else their first, else none.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function current(int $userid): ?\stdClass {
        $all = self::all($userid);
        $saved = (int)config::preference('view', $userid, 0);
        if ($saved && isset($all[$saved])) {
            return $all[$saved];
        }
        return $all ? reset($all) : null;
    }

    /**
     * Creates a view, empty or as a copy of another of the user's views.
     *
     * @param int $userid
     * @param string $name
     * @param int|null $copyof A view id to copy the rules and window of.
     * @return int The new view's id.
     */
    public static function create(int $userid, string $name, ?int $copyof = null): int {
        global $DB;
        $source = $copyof ? self::get($copyof, $userid) : null;
        $now = time();
        $id = $DB->insert_record(self::TABLE, (object)[
            'userid' => $userid,
            'name' => \core_text::substr(trim($name), 0, 255),
            'allocdays' => $source ? (int)$source->allocdays : config::alloc_days(),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        if ($source) {
            foreach (self::KINDS as $kind) {
                foreach ($source->$kind as $value) {
                    $DB->insert_record(self::RULES, (object)['viewid' => $id, 'kind' => $kind, 'value' => (string)$value]);
                }
            }
        }
        return (int)$id;
    }

    /**
     * Renames a view.
     *
     * @param int $id
     * @param int $userid
     * @param string $name
     */
    public static function rename(int $id, int $userid, string $name): void {
        global $DB;
        self::own($id, $userid);
        $DB->update_record(self::TABLE, (object)['id' => $id, 'name' => \core_text::substr(trim($name), 0, 255),
            'timemodified' => time()]);
    }

    /**
     * Deletes a view and its rules.
     *
     * @param int $id
     * @param int $userid
     */
    public static function delete(int $id, int $userid): void {
        global $DB;
        self::own($id, $userid);
        $DB->delete_records(self::RULES, ['viewid' => $id]);
        $DB->delete_records(self::TABLE, ['id' => $id]);
        if ((int)config::preference('view', $userid, 0) === $id) {
            unset_user_preference('block_le_hub_dashboard_view', $userid);
        }
    }

    /**
     * Sets a view's allocation window (P10).
     *
     * @param int $id
     * @param int $userid
     * @param int $days
     */
    public static function set_alloc_days(int $id, int $userid, int $days): void {
        global $DB;
        self::own($id, $userid);
        $DB->update_record(self::TABLE, (object)['id' => $id, 'allocdays' => max(0, $days), 'timemodified' => time()]);
    }

    /**
     * Replaces the lines that shape a view (departments, code rules, modules added), leaving its exceptions.
     *
     * @param int $id
     * @param int $userid
     * @param array $rules kind => values, for the SHAPE kinds.
     */
    public static function set_shape(int $id, int $userid, array $rules): void {
        global $DB;
        self::own($id, $userid);
        $transaction = $DB->start_delegated_transaction();
        [$insql, $params] = $DB->get_in_or_equal(self::SHAPE, SQL_PARAMS_NAMED);
        $DB->delete_records_select(self::RULES, "viewid = :viewid AND kind $insql", $params + ['viewid' => $id]);
        foreach (self::SHAPE as $kind) {
            $values = array_map('trim', array_map('strval', $rules[$kind] ?? []));
            if ($kind === 'course') {
                $values = self::course_ids($values);
            }
            foreach (array_unique(array_filter($values, 'strlen')) as $value) {
                $DB->insert_record(self::RULES, (object)['viewid' => $id, 'kind' => $kind,
                    'value' => \core_text::substr($value, 0, 255)]);
            }
        }
        $DB->update_record(self::TABLE, (object)['id' => $id, 'timemodified' => time()]);
        $transaction->allow_commit();
    }

    /**
     * Course ids for the editor's module lines: an id stays, a short name typed in (editor: add a module by its
     * code) becomes the course's id. An unknown code is an error the editor shows.
     *
     * @param string[] $values
     * @return string[]
     */
    protected static function course_ids(array $values): array {
        global $DB;
        $out = [];
        foreach ($values as $value) {
            if ($value === '' || ctype_digit($value)) {
                $out[] = $value;
                continue;
            }
            $id = $DB->get_field_select('course', 'id', $DB->sql_equal('shortname', ':code', false), ['code' => $value],
                IGNORE_MULTIPLE);
            if (!$id) {
                throw new \moodle_exception('editor_nocode', 'block_le_hub_dashboard', '', $value);
            }
            $out[] = (string)$id;
        }
        return $out;
    }

    /**
     * Adds one exception: a module taken out (outcourse) or an assessment ignored (outcm) (P6).
     *
     * @param int $id
     * @param int $userid
     * @param string $kind outcourse or outcm
     * @param int $value
     */
    public static function add_exception(int $id, int $userid, string $kind, int $value): void {
        global $DB;
        self::own($id, $userid);
        if (!in_array($kind, ['outcourse', 'outcm'])) {
            throw new \coding_exception("Not an exception kind: $kind");
        }
        if (!$DB->record_exists(self::RULES, ['viewid' => $id, 'kind' => $kind, 'value' => (string)$value])) {
            $DB->insert_record(self::RULES, (object)['viewid' => $id, 'kind' => $kind, 'value' => (string)$value]);
        }
    }

    /**
     * Puts an exception back (Undo, or Put back in the editor).
     *
     * @param int $id
     * @param int $userid
     * @param string $kind outcourse or outcm
     * @param int $value
     */
    public static function remove_exception(int $id, int $userid, string $kind, int $value): void {
        global $DB;
        self::own($id, $userid);
        $DB->delete_records(self::RULES, ['viewid' => $id, 'kind' => $kind, 'value' => (string)$value]);
    }

    /**
     * Throws unless the view is the user's.
     *
     * @param int $id
     * @param int $userid
     */
    protected static function own(int $id, int $userid): void {
        global $DB;
        if (!$DB->record_exists(self::TABLE, ['id' => $id, 'userid' => $userid])) {
            throw new \moodle_exception('invalidrecord', 'error', '', self::TABLE);
        }
    }

    /**
     * SQL on the assessment index (alias c) for the assessments in a view: the courses its lines match, less the
     * modules taken out and the assessments ignored. A view with no lines matches nothing.
     *
     * @param \stdClass $view As all() returns.
     * @param string $alias The index cm alias.
     * @return array [sql, params]
     */
    public static function in_view_sql(\stdClass $view, string $alias = 'c'): array {
        global $DB;
        [$where, $params] = self::courses_sql($view);
        $sql = "$alias.courseid IN (SELECT co.id FROM {course} co WHERE $where)";
        if ($view->outcm) {
            [$insql, $inparams] = $DB->get_in_or_equal($view->outcm, SQL_PARAMS_NAMED, 'hx' . count($params), false);
            $sql .= " AND $alias.cmid $insql";
            $params += $inparams;
        }
        return [$sql, $params];
    }

    /**
     * SQL on the course table (alias co) for the courses a view's lines match, less the modules taken out: the one
     * statement of the view's shape, which in_view_sql() and module_counts() both use. No lines match nothing.
     *
     * @param \stdClass $view As all() returns.
     * @return array [sql, params]
     */
    public static function courses_sql(\stdClass $view): array {
        global $DB;
        static $n = 0;
        $p = 'hv' . ++$n;
        $ors = [];
        $params = [];
        $i = 0;
        foreach ($view->cat as $catid) {
            // The category and everything under it (D3).
            $k = $p . 'c' . ++$i;
            $ors[] = "co.category IN (SELECT cc.id FROM {course_categories} cc WHERE cc.id = :$k OR " .
                $DB->sql_like('cc.path', ":{$k}p", false, false) . ')';
            $params[$k] = (int)$catid;
            $params[$k . 'p'] = '%/' . (int)$catid . '/%';
        }
        foreach ($view->starts as $text) {
            $k = $p . 's' . ++$i;
            $ors[] = $DB->sql_like('co.shortname', ":$k", false, false);
            $params[$k] = $DB->sql_like_escape($text) . '%';
        }
        foreach ($view->contains as $text) {
            $k = $p . 'n' . ++$i;
            $ors[] = $DB->sql_like('co.shortname', ":$k", false, false);
            $params[$k] = '%' . $DB->sql_like_escape($text) . '%';
        }
        if ($view->course) {
            [$insql, $inparams] = $DB->get_in_or_equal($view->course, SQL_PARAMS_NAMED, $p . 'a');
            $ors[] = "co.id $insql";
            $params += $inparams;
        }
        if (!$ors) {
            return ['1 = 0', []];
        }
        $where = '(' . implode(' OR ', $ors) . ')';
        if ($view->outcourse) {
            [$insql, $inparams] = $DB->get_in_or_equal($view->outcourse, SQL_PARAMS_NAMED, $p . 'o', false);
            $where .= " AND co.id $insql";
            $params += $inparams;
        }
        return [$where, $params];
    }

    /**
     * Whether a course is in a view, as in_view_sql() without the ignored assessments.
     *
     * @param \stdClass $view
     * @param \stdClass $course With id, shortname, category.
     * @param string $categorypath The course category's path, such as /1/5.
     * @return bool
     */
    public static function course_in_view(\stdClass $view, \stdClass $course, string $categorypath): bool {
        if (in_array((int)$course->id, $view->outcourse)) {
            return false;
        }
        $ids = array_map('intval', array_filter(explode('/', $categorypath)));
        foreach ($view->cat as $catid) {
            if (in_array((int)$catid, $ids)) {
                return true;
            }
        }
        $code = \core_text::strtolower($course->shortname);
        foreach ($view->starts as $text) {
            if ($text !== '' && str_starts_with($code, \core_text::strtolower($text))) {
                return true;
            }
        }
        foreach ($view->contains as $text) {
            if ($text !== '' && str_contains($code, \core_text::strtolower($text))) {
                return true;
            }
        }
        return in_array((int)$course->id, $view->course);
    }

    /**
     * Whether an assessment is in a view, as in_view_sql().
     *
     * @param \stdClass $view
     * @param \stdClass $course With id, shortname, category.
     * @param string $categorypath
     * @param int $cmid
     * @return bool
     */
    public static function cm_in_view(\stdClass $view, \stdClass $course, string $categorypath, int $cmid): bool {
        return !in_array($cmid, $view->outcm) && self::course_in_view($view, $course, $categorypath);
    }

    /**
     * How many indexed modules the view holds, and how many there are: "1 of 13 modules" (P7).
     *
     * @param \stdClass $view
     * @return int[] [in view, total]
     */
    public static function module_counts(\stdClass $view): array {
        global $DB;
        [$where, $params] = self::courses_sql($view);
        $total = $DB->count_records_select('course', 'id <> :site', ['site' => SITEID]);
        $in = $DB->count_records_sql("SELECT COUNT(1) FROM {course} co WHERE co.id <> :site AND $where",
            $params + ['site' => SITEID]);
        return [$in, $total];
    }

    /**
     * The course categories, for the editor's department picker: every category, in tree order.
     *
     * @return array[] id, name, depth, parent
     */
    public static function categories(): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('course_categories', null, 'sortorder ASC', 'id, name, parent, depth') as $cat) {
            $out[] = ['id' => (int)$cat->id, 'name' => format_string($cat->name, true, ['escape' => false]),
                'depth' => (int)$cat->depth, 'parent' => (int)$cat->parent];
        }
        return $out;
    }

    /**
     * The id numbers and short names of the courses a view names (modules added or taken out), for the editor.
     *
     * @param int[] $courseids
     * @return string[] courseid => shortname
     */
    public static function course_codes(array $courseids): array {
        return \block_le_dashboard\local\output\format::course_names(array_map('intval', $courseids));
    }
}
