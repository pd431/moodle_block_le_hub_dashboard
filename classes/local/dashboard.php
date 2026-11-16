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

use block_le_dashboard\local\config as maconfig;
use block_le_dashboard\local\output\format;
use block_le_dashboard\local\scope;
use block_le_dashboard\local\view\base;
use block_le_dashboard\local\years;

/**
 * The Hub dashboard for one user: the saved view (or person) and year they have open, the rows of its assessments
 * classified into the four groups, and the controls' state (P1 to P10). Rendered by block_le_hub_dashboard/view.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dashboard {
    /** The type filter's values; summative is the default (D2). */
    const TYPES = ['summative', 'formative', 'both'];

    /** Views with more assessments than this load their rows in batches of this size, after the frame (L1, L2). */
    const BATCH = 25;

    /** The Filter button's groups and items, in order (P3). Reason items come from rules::REASONS. */
    const FILTERS = [
        'work' => ['plagiarism', 'mitigation', 'exempt', 'returned'],
        'allocation' => ['unallocated', 'noallocation'],
        'notes' => ['notes', 'escalated'],
        'setup' => ['anonymous', 'setup'],
    ];

    /** @var int */
    protected int $userid;
    /** @var int */
    protected int $now;
    /** @var \stdClass|null */
    protected ?\stdClass $view;
    /** @var int */
    protected int $yearid;
    /** @var string */
    protected string $type;
    /** @var int|null */
    protected ?int $personid;

    /**
     * Constructor. Null arguments take the user's saved choices.
     *
     * @param int $userid
     * @param int|null $viewid The saved view, 0 for none.
     * @param int|null $yearid Academic year id, 0 for all years.
     * @param string|null $type One of TYPES.
     * @param int|null $personid A person whose assessments replace the view's (P2), 0 for none.
     * @param int|null $now
     */
    public function __construct(
        int $userid,
        ?int $viewid = null,
        ?int $yearid = null,
        ?string $type = null,
        ?int $personid = null,
        ?int $now = null
    ) {
        $this->userid = $userid;
        $this->now = $now ?? maconfig::now();
        $this->view = $viewid === null ? views::current($userid) : ($viewid ? views::get($viewid, $userid) : null);
        if ($viewid !== null && $this->view) {
            config::set_preference('view', $this->view->id, $userid);
        }
        if ($yearid === null) {
            $saved = config::preference('year', $userid, null);
            $known = $saved !== null && ((int)$saved === 0 || years::get((int)$saved));
            $yearid = $known ? (int)$saved : years::default_id($this->now);
        } else {
            config::set_preference('year', $yearid, $userid);
        }
        $this->yearid = $yearid;
        if ($type === null) {
            $type = (string)config::preference('type', $userid, 'summative');
        } else {
            config::set_preference('type', $type, $userid);
        }
        $this->type = in_array($type, self::TYPES, true) ? $type : 'summative';
        $this->personid = $personid ?: null;
    }

    /**
     * The state the page's script keeps and sends back.
     *
     * @return array
     */
    public function state(): array {
        return ['viewid' => $this->view ? (int)$this->view->id : 0, 'yearid' => $this->yearid, 'type' => $this->type,
            'personid' => (int)$this->personid];
    }

    /**
     * The assessments on screen, for Refresh.
     *
     * @return int[]
     */
    public function cmids(): array {
        global $DB;
        [$where, $params] = $this->where();
        if ($where === null) {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_sql("SELECT c.cmid FROM {block_le_dashboard_cm} c " . base::LIVE .
            " WHERE $where", $params));
    }

    /**
     * SQL on the index (alias c) for what is shown: a person's assessments, or the view's in the year. Marking that
     * is late stays visible whatever year is picked (hub-view: Search and year).
     *
     * @return array [sql or null when nothing is shown, params]
     */
    /**
     * Every assessment the state covers, in list order, with when each was indexed: the frame's list of what to
     * fetch (L1) and the index's age.
     *
     * @return int[] cmid => timeindexed
     */
    public function cm_list(): array {
        global $DB;
        [$where, $params] = $this->where();
        if ($where === null) {
            return [];
        }
        return $DB->get_records_sql_menu("SELECT c.cmid, c.timeindexed
                                            FROM {block_le_dashboard_cm} c
                                            JOIN {course} co ON co.id = c.courseid
                                            " . base::LIVE . "
                                           WHERE $where
                                        ORDER BY c.due ASC, c.name ASC, c.cmid ASC", $params);
    }

    /**
     * The SQL that picks this dashboard's cms: the person's scope, or the view's courses in the year (late work in
     * any year), as [where, params]; null when there is nothing to show.
     *
     * @return array|null
     */
    protected function where(): ?array {
        if ($this->personid) {
            return people::scope_sql($this->personid);
        }
        if (!$this->view) {
            return [null, []];
        }
        [$insql, $params] = views::in_view_sql($this->view);
        [$yearsql, $yearparams] = scope::in_year_sql('c', years::get($this->yearid));
        return ["$insql AND ($yearsql OR " . rules::LATE_ANY_YEAR_SQL . ')', $params + $yearparams + ['latenow' => $this->now]];
    }

    /**
     * The template context.
     *
     * @param bool $deferred Leave the rows for the script to fetch, however small the view (L1).
     * @param bool $witheditor Include the editor's context (the department tree) (L4).
     * @return array
     */
    public function export(bool $deferred = false, bool $witheditor = true): array {
        $c = 'block_le_hub_dashboard';
        $list = $this->cm_list();
        $oldest = $list ? min(array_map('intval', $list)) : null;
        // A small view comes with its rows; a large one, or the block's first paint, lists what to fetch (L1, L2).
        $inline = !$deferred && count($list) <= self::BATCH;
        $groups = $inline ? $this->groups(array_keys($list)) : array_fill_keys(rules::GROUPS, []);
        $loading = !$inline && !empty($list);
        $sections = [];
        $strip = [];
        foreach ($groups as $key => $rows) {
            $sections[] = ['key' => $key, 'label' => get_string('group_' . $key, $c), 'count' => count($rows), 'rows' => $rows,
                'hasrows' => !empty($rows) || $loading, 'reasons' => self::reason_counts($key, $rows),
                'collapsed' => $key === rules::DONE, 'loading' => $loading];
            $strip[] = ['key' => $key, 'label' => get_string('group_' . $key, $c), 'count' => count($rows),
                'loading' => $loading];
        }
        $person = $this->personid ? \core_user::get_user($this->personid) : null;
        $personname = $person ? fullname($person) : null;
        return [
            'uniqid' => \html_writer::random_id('hub'),
            'state' => json_encode($this->state()),
            'cmids' => $inline ? '' : implode(',', array_keys($list)),
            'loading' => $loading,
            'budget' => config::load_seconds(),
            'budgettext' => get_string('budget_over', $c, count($list)),
            'unheld' => testing::watching_rows(),
            'selectclass' => format::select_class(),
            'views' => $this->views(),
            'hasviews' => (bool)views::all($this->userid),
            'view' => $this->view ? ['id' => (int)$this->view->id, 'name' => format_string(
                $this->view->name,
                true,
                ['escape' => false]
            ),
                'allocdays' => (int)$this->view->allocdays] : null,
            'editor' => $witheditor && $this->view ? $this->editor() : null,
            'person' => $person ? ['id' => (int)$person->id, 'name' => $personname,
                'banner' => get_string('personscope', $c, $personname)] : null,
            'years' => $this->years(),
            'types' => array_map(
                fn($t) => ['key' => $t, 'label' => get_string($t, $c), 'active' => $t === $this->type],
                self::TYPES
            ),
            'type' => $this->type,
            'filters' => $this->filters(),
            'strip' => $strip,
            'groups' => $sections,
            'hasrows' => (bool)$list,
            'empty' => $person ? get_string('empty_person', $c, $personname)
                : ($this->view ? get_string('empty_view', $c) : get_string('noviews', $c)),
            'freshness' => $this->freshness($oldest),
            'timezone' => \core_date::get_user_timezone(),
            'hoverpopovers' => maconfig::shown('hoverpopovers'),
        ];
    }

    /**
     * The rows of some of the state's assessments, built and sorted, by group (L2): what one batch fetch returns.
     *
     * @param int[] $cmids
     * @return array[] group => rows, in list order
     */
    public function groups(array $cmids): array {
        $cmids = array_values(array_unique(array_map('intval', $cmids)));
        $groups = array_fill_keys(rules::GROUPS, []);
        [$where, $params] = $this->where();
        if ($where === null || !$cmids) {
            return $groups;
        }
        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'hb');
        $cms = query::aggregates("($where) AND c.cmid $insql", $params + $inparams, $this->now);
        $riskdays = config::risk_days();
        $allocdays = $this->view ? (int)$this->view->allocdays : config::alloc_days();
        $notes = notes::for_cms(array_keys($cms));
        $convenors = people::convenors(array_column($cms, 'courseid'));
        $statuses = $this->personid ? people::statuses($this->personid, $cms, $this->now) : [];
        foreach ($cms as $cmid => $cm) {
            $class = rules::classify($cm, $this->now, $riskdays, $allocdays);
            $groups[$class->group][] = row::build(
                $cm,
                $class,
                $this->personid ? null : $this->view,
                $this->now,
                $this->userid,
                $notes[$cmid] ?? [],
                $convenors[$cm->courseid] ?? [],
                $statuses[$cmid] ?? null
            );
        }
        foreach ($groups as &$rows) {
            usort($rows, fn($a, $b) => ($a['sortkey'] <=> $b['sortkey']) ?: strcmp($a['search'], $b['search']));
        }
        unset($rows);
        return $groups;
    }

    /**
     * A group's reasons with their counts, every reason the group can carry (P9): the page's script keeps the
     * counts as rows arrive and filters change, and hides a reason with none.
     *
     * @param string $group
     * @param array $rows
     * @return array[] key, label, count
     */
    public static function reason_counts(string $group, array $rows): array {
        $c = 'block_le_hub_dashboard';
        $counts = [];
        foreach ($rows as $row) {
            foreach ($row['reasons'] as $reason) {
                $counts[$reason['key']] = ($counts[$reason['key']] ?? 0) + 1;
            }
        }
        $out = [];
        foreach (rules::REASONS as $reason => $of) {
            if ($of === $group) {
                $out[] = ['key' => $reason, 'label' => get_string('reason_' . $reason, $c), 'count' => $counts[$reason] ?? 0,
                    'none' => empty($counts[$reason])];
            }
        }
        return $out;
    }

    /**
     * The view picker's options.
     *
     * @return array
     */
    protected function views(): array {
        $out = [];
        foreach (views::all($this->userid) as $view) {
            $out[] = ['id' => (int)$view->id, 'name' => format_string($view->name, true, ['escape' => false]),
                'selected' => $this->view && (int)$view->id === (int)$this->view->id];
        }
        return $out;
    }

    /**
     * The year picker's options, as My assessments'.
     *
     * @return array
     */
    protected function years(): array {
        $options = [['id' => 0, 'label' => get_string('allyears', 'block_le_dashboard'), 'selected' => $this->yearid === 0]];
        foreach (years::all() as $year) {
            $options[] = ['id' => (int)$year->id, 'label' => format_string($year->label, true, ['escape' => false]),
                'selected' => (int)$year->id === $this->yearid];
        }
        return $options;
    }

    /**
     * The Filter button's groups (P3): Reason first, then Work, Allocation, Hub notes, Set-up.
     *
     * @return array
     */
    protected function filters(): array {
        $c = 'block_le_hub_dashboard';
        $groups = [['key' => 'reason', 'label' => get_string('fgroup_reason', $c), 'items' => array_map(fn($r) =>
            ['key' => 'r:' . $r, 'label' => get_string('reason_' . $r, $c)], array_keys(rules::REASONS))]];
        foreach (self::FILTERS as $group => $items) {
            $groups[] = ['key' => $group, 'label' => get_string('fgroup_' . $group, $c), 'items' => array_map(fn($i) =>
                ['key' => 'f:' . $i, 'label' => get_string('f_' . $i, $c)], $items)];
        }
        return $groups;
    }

    /**
     * The view editor's data (P7): every category as a tree, the view's lines and exceptions, its window.
     *
     * @return array
     */
    protected function editor(): array {
        $view = $this->view;
        $categories = views::categories();
        foreach ($categories as &$cat) {
            $cat['checked'] = in_array($cat['id'], $view->cat);
        }
        unset($cat);
        $picker = [
            'id' => \html_writer::random_id('hubcats'),
            'label' => get_string('editor_departments', 'block_le_hub_dashboard'),
            'categories' => $categories,
            'hascategories' => !empty($categories),
        ];
        $codes = [];
        foreach (['starts', 'contains'] as $kind) {
            foreach ($view->$kind as $text) {
                $codes[] = ['kind' => $kind, 'value' => $text, 'starts' => $kind === 'starts', 'contains' => $kind === 'contains'];
            }
        }
        $names = views::course_codes(array_merge($view->course, $view->outcourse));
        $outside = self::outside_courses($view->course);
        $added = array_map(fn($id) => ['id' => (int)$id, 'code' => $names[$id] ?? (string)$id,
            'outside' => in_array((int)$id, $outside, true),
            'outsidetext' => get_string('editor_outside', 'block_le_hub_dashboard', $names[$id] ?? (string)$id)], $view->course);
        $out = array_map(fn($id) => ['id' => (int)$id, 'code' => $names[$id] ?? (string)$id], $view->outcourse);
        $outcms = [];
        if ($view->outcm) {
            global $DB;
            [$insql, $params] = $DB->get_in_or_equal($view->outcm, SQL_PARAMS_NAMED);
            foreach (
                $DB->get_records_select(
                    'block_le_dashboard_cm',
                    "cmid $insql",
                    $params,
                    'name',
                    'cmid, name, courseid'
                ) as $cm
            ) {
                $outcms[] = ['id' => (int)$cm->cmid, 'name' => format_string(
                    $cm->name,
                    true,
                    ['context' => \context_course::instance($cm->courseid), 'escape' => false]
                )];
            }
        }
        [$in, $total] = views::module_counts($view);
        return ['categories' => $categories, 'picker' => $picker, 'codes' => $codes, 'hascodes' => !empty($codes),
            'added' => $added,
            'hasadded' => !empty($added),
            'outcourses' => $out, 'hasoutcourses' => !empty($out), 'outcms' => $outcms, 'hasoutcms' => !empty($outcms),
            'hasexceptions' => !empty($out) || !empty($outcms), 'allocdays' => (int)$view->allocdays,
            'modules' => get_string('modules_count', 'block_le_hub_dashboard', ['in' => $in, 'total' => $total])];
    }

    /**
     * Which of some courses sit outside the categories the hub works in (C1): a view keeps a module it added by
     * code, but its rows are not shown and the editor says so.
     *
     * @param int[] $courseids
     * @return int[]
     */
    protected static function outside_courses(array $courseids): array {
        global $DB;
        if (!$courseids || categories::valid() === null) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $courseids), SQL_PARAMS_NAMED);
        $out = [];
        foreach ($DB->get_records_select_menu('course', "id $insql", $params, '', 'id, category') as $id => $category) {
            if (!categories::inside((int)$category)) {
                $out[] = (int)$id;
            }
        }
        return $out;
    }

    /**
     * When the index was last updated, as My assessments says it.
     *
     * @param int|null $oldest
     * @return array
     */
    protected function freshness(?int $oldest): array {
        $since = (int)config::preference('refreshing', $this->userid, 0);
        return [
            'text' => format::updated($oldest, $this->now),
            'dated' => format::updated_dated($oldest, $this->now),
            'refreshing' => $since > $this->now - base::REFRESHING_EXPIRES,
            'preparing' => get_config('block_le_dashboard', 'backfillpending')
                && ($oldest === null || !get_config('block_le_dashboard', 'backfilldone')),
        ];
    }
}
