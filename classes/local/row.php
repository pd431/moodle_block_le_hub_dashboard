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

/**
 * One builder for every hub row (P5, P6): the collapsed line (reason, status sentence, stacked progress, flags in
 * one fixed order) and the expanded detail (tracker dates, set-up, bars, people, notes, Ignore). Views pass the
 * classified aggregates in; nothing else appends to a row.
 *
 * Flags come in one order: hidden, check settings, allocation state, no marking deadline, academic conduct,
 * individual deadlines, exempt or deferred, new work, sent before marked, changed since sent, changed since
 * released, escalated, hub notes. Each count flag shows only when My assessments' switch for it is on (D8).
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class row {
    /** Each hub flag's icon. Academic conduct, hidden and check settings keep My assessments' own. */
    const ICONS = [
        'allocated' => 'fa-user-check',
        'unallocated' => 'fa-user-slash',
        'noallocation' => 'fa-user-slash',
        'nomarkingdue' => 'fa-calendar-xmark',
        'individual' => 'fa-calendar-plus',
        'exempt' => 'fa-user-xmark',
        'returned' => 'fa-file-circle-plus',
        'early' => 'fa-paper-plane',
        'changedsent' => 'fa-pen-to-square',
        'changedrel' => 'fa-pen-to-square',
        'escalated' => 'fa-bullhorn',
        'notes' => 'fa-note-sticky',
        'anonymous' => 'fa-user-secret',
    ];

    /** Each hub flag's tone: attention means the hub can act on it now; set-up states are neutral (D8). */
    const TONES = [
        'allocated' => 'grey',
        'unallocated' => 'attention',
        'noallocation' => 'grey',
        'nomarkingdue' => 'info',
        'individual' => 'info',
        'exempt' => 'grey',
        'returned' => 'info',
        'early' => 'attention',
        'changedsent' => 'attention',
        'changedrel' => 'attention',
        'escalated' => 'attention',
        'notes' => 'info',
        'anonymous' => 'grey',
    ];

    /**
     * A row.
     *
     * @param \stdClass $cm Aggregated index record (query::aggregates()).
     * @param \stdClass $class rules::classify().
     * @param \stdClass $view The open saved view (for Ignore), or null when the list is one person's.
     * @param int $now
     * @param int $viewerid
     * @param array $notes The assessment's notes (notes::for_cms()).
     * @param array $convenors The course's module leads (people::convenors()).
     * @param string|null $personstatus The status sentence when the list is one person's (people::statuses()).
     * @return array
     */
    public static function build(
        \stdClass $cm,
        \stdClass $class,
        ?\stdClass $view,
        int $now,
        int $viewerid,
        array $notes,
        array $convenors,
        ?string $personstatus
    ): array {
        $row = format::row($cm, (string)$cm->courseshortname, 'hub');
        $row['id'] = 'hub-' . $cm->cmid;
        $row['courseid'] = (int)$cm->courseid;
        $row['coursename'] = format_string($cm->coursefullname, true,
            ['context' => \context_course::instance($cm->courseid), 'escape' => false]);
        $row['group'] = $class->group;
        $row['reason'] = self::reason($class->reason);
        $row['reasons'] = array_map([self::class, 'reason'], $class->reasons);
        $row['reasonkeys'] = implode(' ', $class->reasons);
        $row['status'] = $personstatus ?? self::status($cm, $class, $now);
        $row['progress'] = self::progress($cm, $class);
        $row['flags'] = self::flags($row, $cm, $notes);
        $row['flagkeys'] = implode(' ', array_merge(array_column($row['flags'], 'key'),
            $cm->anonymous ? ['anonymous'] : [], $cm->individual || !empty($cm->pending) ? ['mitigation'] : []));
        $row['dates'] = self::dates($cm);
        $row['setup'] = self::setup($cm);
        $row['bars'] = self::bars($cm);
        $row['people'] = self::people($cm, $viewerid, $convenors);
        $row['notes'] = self::notes($notes, $now);
        $row['hasnotes'] = !empty($notes);
        $row['ignore'] = $view ? self::ignore($view, $row) : null;
        $row['sortkey'] = rules::sort_key($cm, $class);
        $row['search'] .= ' ' . \core_text::strtolower($row['coursename']);
        foreach ($row['people']['convenors'] as $c) {
            $row['search'] .= ' ' . \core_text::strtolower($c['name']);
        }
        foreach ($row['people']['markers'] as $m) {
            $row['search'] .= ' ' . \core_text::strtolower($m['name']);
        }
        return format::load_error($row, $cm, $cm->total === 0);
    }

    /**
     * The Ignore menu (P6): its two items and what the toast says once each is done.
     *
     * @param \stdClass $view
     * @param array $row
     * @return array
     */
    protected static function ignore(\stdClass $view, array $row): array {
        $c = 'block_le_hub_dashboard';
        $viewname = format_string($view->name, true, ['escape' => false]);
        return [
            'viewid' => (int)$view->id,
            'viewname' => $viewname,
            'label' => get_string('ignore_in', $c, $viewname),
            'module' => get_string('ignore_module', $c, $row['course']),
            'donemodule' => get_string('ignored_module', $c, ['code' => $row['course'], 'view' => $viewname]),
            'donecm' => get_string('ignored_assessment', $c, ['name' => $row['name'], 'view' => $viewname]),
        ];
    }

    /**
     * A reason tag.
     *
     * @param string $key
     * @return array key, label, group
     */
    public static function reason(string $key): array {
        return ['key' => $key, 'label' => get_string('reason_' . $key, 'block_le_hub_dashboard'), 'group' => rules::REASONS[$key]];
    }

    /**
     * The status sentence: what the leading reason turns on, with the date or days that matter.
     *
     * @param \stdClass $cm
     * @param \stdClass $class
     * @param int $now
     * @return string
     */
    public static function status(\stdClass $cm, \stdClass $class, int $now): string {
        $c = 'block_le_hub_dashboard';
        switch ($class->reason) {
            case 'upcoming':
                return get_string('status_upcoming', $c, ['submitted' => $cm->submitted, 'total' => $cm->total,
                    'when' => self::when((int)$cm->due, $now)]);
            case 'allocate':
                return get_string('status_allocate', $c, ['n' => $cm->unallocated, 'when' => self::when((int)$cm->due, $now)]);
            case 'ontrack':
            case 'risk':
                if ($cm->units === 0) {
                    return get_string('status_nothing', $c);
                }
                return $cm->markingdue ? get_string('status_ontrack', $c, ['n' => $cm->unmarked,
                    'when' => self::when((int)$cm->markingdue, $now)]) : get_string('status_nodeadline', $c, $cm->unmarked);
            case 'late':
                return get_string('status_late', $c, ['n' => $cm->unmarked, 'days' => self::days_past((int)$cm->markingdue, $now)]);
            case 'release':
                return get_string('status_release', $c, $cm->units);
            case 'notsent':
                return get_string('status_notsent', $c, ['n' => $cm->tosend - $cm->sent, 'total' => $cm->tosend]);
            case 'early':
                return get_string('status_early', $c, $cm->early);
            case 'changedsent':
                return get_string('status_changedsent', $c, $cm->changedsent);
            case 'released':
                return get_string('status_released', $c, $cm->timereleased ? format::datetime((int)$cm->timereleased) : '');
            default:
                return get_string('status_changedrel', $c, $cm->changedrel);
        }
    }

    /**
     * "in 6 days", "today" or "3 days ago", by calendar days in the viewer's time zone.
     *
     * @param int $time
     * @param int $now
     * @return string
     */
    public static function when(int $time, int $now): string {
        $days = format::calendar_days($now, $time);
        $c = 'block_le_hub_dashboard';
        if ($days === 0) {
            return get_string('today', $c);
        }
        if ($days > 0) {
            return $days === 1 ? get_string('in_day', $c) : get_string('in_days', $c, $days);
        }
        return $days === -1 ? get_string('day_ago', $c) : get_string('days_ago', $c, -$days);
    }

    /**
     * "2 days" past a deadline.
     *
     * @param int $time
     * @param int $now
     * @return string
     */
    protected static function days_past(int $time, int $now): string {
        $days = max(1, -format::calendar_days($now, $time));
        return $days === 1 ? get_string('day_past', 'block_le_hub_dashboard') : get_string('days_past',
            'block_le_hub_dashboard', $days);
    }

    /**
     * The stacked progress bar (P5): submitted, marked, moderated, sent to hub, released, each as a share of who
     * should hand in, with the number the group turns on as its text.
     *
     * @param \stdClass $cm
     * @param \stdClass $class
     * @return array|null segments [key, label, value, percent], max, text, key
     */
    public static function progress(\stdClass $cm, \stdClass $class): ?array {
        $c = 'block_le_hub_dashboard';
        $max = $cm->isgroup ? max($cm->units, (int)($cm->marking[$cm->stagelist[0] ?? '']->units ?? 0)) : $cm->total;
        if ($max <= 0) {
            return null;
        }
        $marked = $cm->units - $cm->unmarked;
        $segments = [];
        if ($cm->adapter !== 'generic' && !$cm->isgroup) {
            $segments[] = ['key' => 'submitted', 'label' => get_string('seg_submitted', $c), 'value' => $cm->submitted];
        }
        $segments[] = ['key' => 'marked', 'label' => get_string('seg_marked', $c), 'value' => max(0, $marked)];
        foreach (['moderated', 'agreed'] as $stage) {
            if (isset($cm->marking[$stage])) {
                $segments[] = ['key' => 'moderated', 'label' => get_string('seg_moderated', $c),
                    'value' => (int)$cm->marking[$stage]->marked];
                break;
            }
        }
        if ($cm->sendstep) {
            $segments[] = ['key' => 'sent', 'label' => get_string('seg_sent', $c), 'value' => $cm->sent];
        }
        if (empty($cm->norelease)) {
            $segments[] = ['key' => 'released', 'label' => get_string('seg_released', $c), 'value' => $cm->released];
        }
        foreach ($segments as &$s) {
            $s['percent'] = (int)round(100 * min($s['value'], $max) / $max);
        }
        unset($s);
        // The number that matters: submissions before the deadline, marking while it's owed, then release.
        $keys = ['upcoming' => 'submitted', 'allocate' => 'submitted', 'release' => 'released', 'notsent' => 'sent',
            'released' => 'released', 'changedrel' => 'released'];
        $key = $keys[$class->reason] ?? 'marked';
        $lead = null;
        foreach ($segments as $s) {
            if ($s['key'] === $key) {
                $lead = $s;
            }
        }
        $lead = $lead ?? end($segments);
        return ['segments' => $segments, 'max' => $max, 'key' => $lead['key'],
            'text' => get_string('progress_text', $c, ['value' => $lead['value'], 'max' => $max,
                'label' => \core_text::strtolower($lead['label'])]),
            'label' => implode(', ', array_map(fn($s) => $s['label'] . ' ' . $s['value'], $segments))];
    }

    /**
     * A hub flag, in My assessments' hint shape (its template and popover behaviour).
     *
     * @param string $rowid
     * @param string $key A key of ICONS.
     * @param string $title
     * @param string $text
     * @param int|null $count
     * @return array
     */
    public static function hint(string $rowid, string $key, string $title, string $text, ?int $count = null): array {
        return ['id' => $rowid . '-hint-' . $key, 'key' => $key, 'icon' => self::ICONS[$key], 'tone' => self::TONES[$key],
            'glyph' => false, 'title' => $title, 'count' => $count, 'text' => $text, 'link' => null, 'guidance' => null];
    }

    /**
     * A counted flag: "1 individual deadline" / "3 individual deadlines", with its text.
     *
     * @param string $rowid
     * @param string $key
     * @param int $count
     * @return array
     */
    protected static function counted(string $rowid, string $key, int $count): array {
        $c = 'block_le_hub_dashboard';
        return self::hint($rowid, $key, get_string('flag_' . $key . ($count === 1 ? '_one' : '_many'), $c, $count),
            get_string('flag_' . $key . '_text', $c, $count), $count);
    }

    /**
     * The row's flags, in order.
     *
     * @param array $row
     * @param \stdClass $cm
     * @param array $notes
     * @return array
     */
    protected static function flags(array $row, \stdClass $cm, array $notes): array {
        $c = 'block_le_hub_dashboard';
        $id = $row['id'];
        $flags = [];
        if (!empty($row['hiddenhint'])) {
            $flags[] = $row['hiddenhint'];
        }
        if (maconfig::shown('hint_setup') && $setup = format::setup_hint($id, (int)$cm->cmid, (string)$cm->setup)) {
            $flags[] = $setup;
        }
        // The allocation state, before the deadline too (hub-view: Allocation).
        if (!$cm->hasallocation) {
            $flags[] = self::hint($id, 'noallocation', get_string('flag_noallocation', $c),
                get_string('flag_noallocation_text', $c));
        } else if ($cm->unallocated > 0 && maconfig::shown('hint_unallocated')) {
            $flags[] = self::counted($id, 'unallocated', $cm->unallocated);
        } else if ($cm->unallocated === 0) {
            $flags[] = self::hint($id, 'allocated', get_string('flag_allocated', $c), get_string('flag_allocated_text', $c));
        }
        if ($cm->units > 0 && $cm->unmarked > 0 && empty($cm->markingdue)) {
            $flags[] = self::hint($id, 'nomarkingdue', get_string('flag_nomarkingdue', $c),
                get_string('flag_nomarkingdue_text', $c));
        }
        if (!empty($cm->flagged)) {
            $flags[] = format::plagiarism_hint($id, (int)$cm->flagged->open, (int)$cm->flagged->withheld);
        }
        if ($cm->individual > 0 && maconfig::shown('hint_extensions')) {
            $flags[] = self::counted($id, 'individual', $cm->individual);
        }
        if ($cm->exempt > 0) {
            $flags[] = self::counted($id, 'exempt', $cm->exempt);
        }
        if ($cm->returned && maconfig::shown('hint_returned')) {
            $flags[] = self::hint($id, 'returned', get_string('flag_returned', $c), get_string('flag_returned_text', $c));
        }
        foreach (['early', 'changedsent', 'changedrel'] as $key) {
            if ((int)$cm->$key > 0) {
                $flags[] = self::counted($id, $key, (int)$cm->$key);
            }
        }
        if (array_filter($notes, fn($n) => $n['escalated'])) {
            $flags[] = self::hint($id, 'escalated', get_string('flag_escalated', $c), get_string('flag_escalated_text', $c));
        }
        if ($notes) {
            $flags[] = self::counted($id, 'notes', count($notes));
        }
        return $flags;
    }

    /**
     * The tracker's dates, in tracker order (D9): submission deadline, marking deadline, marks due to students,
     * marking finished, released.
     *
     * @param \stdClass $cm
     * @return array
     */
    protected static function dates(\stdClass $cm): array {
        $dates = [];
        if ($cm->due) {
            $dates[] = format::date('submissiondeadline', (int)$cm->due);
        }
        if ($cm->markingdue) {
            $dates[] = format::date('markingdue', (int)$cm->markingdue);
            // Hubs see when marks are due to students whatever students see: the deadline plus the site's delay.
            $mode = get_config('block_le_dashboard', 'feedbackdatemode');
            $delay = $mode === 'delayed' ? maconfig::number('feedbackdatedelay') * DAYSECS : 0;
            $date = format::date('marksdue', (int)$cm->markingdue + $delay);
            $date['label'] = get_string('date_marksdue', 'block_le_hub_dashboard');
            $dates[] = $date;
        }
        if ($cm->markingfinished) {
            $date = format::date('markingfinished', (int)$cm->markingfinished);
            $date['label'] = get_string('date_markingfinished', 'block_le_hub_dashboard');
            $dates[] = $date;
        }
        if ($cm->timereleased) {
            $date = format::date('released', (int)$cm->timereleased);
            $date['label'] = get_string('date_released', 'block_le_hub_dashboard');
            $dates[] = $date;
        }
        return $dates;
    }

    /**
     * How the work is handed in and marked (set-up flags, hub-view: Set-up): submission, group, marking stages,
     * moderation, anonymity, allocation.
     *
     * @param \stdClass $cm
     * @return array [key, label]
     */
    protected static function setup(\stdClass $cm): array {
        $c = 'block_le_hub_dashboard';
        $items = [];
        $stages = $cm->stagelist;
        if (in_array('attendance', $stages)) {
            $items[] = ['key' => 'attendance', 'label' => get_string('setup_attendance', $c)];
        } else if ($cm->nosubmission) {
            $items[] = ['key' => 'nosubmission', 'label' => get_string('setup_nosubmission', $c)];
        } else {
            $items[] = ['key' => 'online', 'label' => get_string('setup_online', $c)];
        }
        if ($cm->isgroup) {
            $items[] = ['key' => 'group', 'label' => get_string('setup_group', $c)];
        }
        $markers = count(array_filter($stages, fn($s) => str_starts_with($s, 'marker') || str_starts_with($s, 'assessor')));
        if ($markers <= 1) {
            $items[] = ['key' => 'single', 'label' => get_string('setup_single', $c)];
        } else if ($markers === 2) {
            $items[] = ['key' => 'double', 'label' => get_string('setup_double', $c)];
        } else {
            $items[] = ['key' => 'multi', 'label' => get_string('setup_multi', $c, $markers)];
        }
        if (in_array('moderated', $stages)) {
            $items[] = ['key' => 'moderated', 'label' => get_string('setup_moderated', $c)];
        }
        if ($cm->anonymous) {
            $items[] = ['key' => 'anonymous', 'label' => get_string('setup_anonymous', $c)];
        }
        if (!$cm->hasallocation) {
            $items[] = ['key' => 'noallocation', 'label' => get_string('setup_noallocation', $c)];
        } else if ($cm->unallocated > 0) {
            $items[] = ['key' => 'unallocated', 'label' => get_string('setup_unallocated', $c, $cm->unallocated)];
        } else {
            $items[] = ['key' => 'allocated', 'label' => get_string('setup_allocated', $c)];
        }
        return $items;
    }

    /**
     * The individual bars of the expanded row, as the convenor view draws them.
     *
     * @param \stdClass $cm
     * @return array
     */
    protected static function bars(\stdClass $cm): array {
        $bars = [];
        if ($cm->adapter !== 'generic') {
            $bars[] = format::bar('submitted', get_string('bar_submitted', 'block_le_dashboard'), $cm->submitted, $cm->total);
        }
        if ($cm->units > 0) {
            foreach ($cm->stagelist as $i => $stage) {
                $m = $cm->marking[$stage] ?? null;
                $owed = $i === 0 ? $cm->units : (int)($m->ready ?? 0);
                if ($owed > 0) {
                    $bars[] = format::bar($stage, format::stage($stage), (int)($m->marked ?? 0), $owed, $cm->isgroup);
                }
            }
            if ($cm->sendstep) {
                $bars[] = format::bar('sent', get_string('seg_sent', 'block_le_hub_dashboard'), $cm->sent, $cm->tosend,
                    $cm->isgroup);
            }
            if (empty($cm->norelease)) {
                $bars[] = format::bar('released', get_string('bar_released', 'block_le_dashboard'), $cm->released,
                    $cm->units, $cm->isgroup);
            }
        }
        return $bars;
    }

    /**
     * People: module leads and markers, names linked to their profiles.
     *
     * @param \stdClass $cm
     * @param int $viewerid
     * @param array $convenors
     * @return array
     */
    protected static function people(\stdClass $cm, int $viewerid, array $convenors): array {
        $markers = people::markers($cm, $viewerid);
        return ['convenors' => $convenors, 'hasconvenors' => !empty($convenors), 'markers' => $markers['markers'],
            'hasmarkers' => !empty($markers['markers']), 'outstanding' => $markers['outstanding'],
            'reallocateurl' => $cm->hasallocation ? (new \moodle_url('/mod/' . $cm->modname . '/view.php',
                ['id' => $cm->cmid, 'action' => 'grading']))->out(false) : null];
    }

    /**
     * The notes, newest first, each with its author line.
     *
     * @param array $notes
     * @param int $now
     * @return array
     */
    protected static function notes(array $notes, int $now): array {
        foreach ($notes as &$n) {
            $n['when'] = format::datetime($n['time']);
            $n['iso'] = date('c', $n['time']);
            $n['by'] = get_string('note_by', 'block_le_hub_dashboard', ['author' => $n['author'], 'when' => $n['when']]);
        }
        unset($n);
        return $notes;
    }
}
