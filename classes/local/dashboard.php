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
     * @return array
     */
    public function export(): array {
        $c = 'block_le_hub_dashboard';
        [$where, $params] = $this->where();
        $cms = $where === null ? [] : query::aggregates($where, $params, $this->now);
        $riskdays = config::risk_days();
        $allocdays = $this->view ? (int)$this->view->allocdays : config::alloc_days();
        $notes = notes::for_cms(array_keys($cms));
        $convenors = people::convenors(array_column($cms, 'courseid'));
        $statuses = $this->personid ? people::statuses($this->personid, $cms, $this->now) : [];
        $groups = array_fill_keys(rules::GROUPS, []);
        $oldest = null;
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
            $oldest = $oldest === null ? (int)$cm->timeindexed : min($oldest, (int)$cm->timeindexed);
        }
        $sections = [];
        $strip = [];
        foreach ($groups as $key => $rows) {
            usort($rows, fn($a, $b) => ($a['sortkey'] <=> $b['sortkey']) ?: strcmp($a['search'], $b['search']));
            $reasons = [];
            foreach ($rows as $row) {
                foreach ($row['reasons'] as $reason) {
                    $reasons[$reason['key']] = ($reasons[$reason['key']] ?? 0) + 1;
                }
            }
            $counts = [];
            foreach (array_keys(rules::REASONS) as $reason) {
                if (isset($reasons[$reason])) {
                    $counts[] = ['key' => $reason, 'label' => get_string('reason_' . $reason, $c), 'count' => $reasons[$reason]];
                }
            }
            $sections[] = ['key' => $key, 'label' => get_string('group_' . $key, $c), 'count' => count($rows), 'rows' => $rows,
                'hasrows' => !empty($rows), 'reasons' => $counts, 'collapsed' => $key === rules::DONE];
            $strip[] = ['key' => $key, 'label' => get_string('group_' . $key, $c), 'count' => count($rows)];
        }
        $person = $this->personid ? \core_user::get_user($this->personid) : null;
        $personname = $person ? fullname($person) : null;
        return [
            'uniqid' => \html_writer::random_id('hub'),
            'state' => json_encode($this->state()),
            'selectclass' => format::select_class(),
            'views' => $this->views(),
            'hasviews' => (bool)views::all($this->userid),
            'view' => $this->view ? ['id' => (int)$this->view->id, 'name' => format_string(
                $this->view->name,
                true,
                ['escape' => false]
            ),
                'allocdays' => (int)$this->view->allocdays] : null,
            'editor' => $this->view ? $this->editor() : null,
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
            'hasrows' => (bool)$cms,
            'empty' => $person ? get_string('empty_person', $c, $personname)
                : ($this->view ? get_string('empty_view', $c) : get_string('noviews', $c)),
            'freshness' => $this->freshness($oldest),
            'timezone' => \core_date::get_user_timezone(),
            'hoverpopovers' => maconfig::shown('hoverpopovers'),
        ];
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
        $codes = [];
        foreach (['starts', 'contains'] as $kind) {
            foreach ($view->$kind as $text) {
                $codes[] = ['kind' => $kind, 'value' => $text, 'starts' => $kind === 'starts', 'contains' => $kind === 'contains'];
            }
        }
        $names = views::course_codes(array_merge($view->course, $view->outcourse));
        $added = array_map(fn($id) => ['id' => (int)$id, 'code' => $names[$id] ?? (string)$id], $view->course);
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
        return ['categories' => $categories, 'codes' => $codes, 'hascodes' => !empty($codes), 'added' => $added,
            'hasadded' => !empty($added),
            'outcourses' => $out, 'hasoutcourses' => !empty($out), 'outcms' => $outcms, 'hasoutcms' => !empty($outcms),
            'hasexceptions' => !empty($out) || !empty($outcms), 'allocdays' => (int)$view->allocdays,
            'modules' => get_string('modules_count', 'block_le_hub_dashboard', ['in' => $in, 'total' => $total])];
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
