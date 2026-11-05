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
 * Whose turn it is, and why (P9, P10): the one place that turns an assessment's counts into its group, its reasons
 * and its order. Pure functions over the aggregates query::aggregates() builds, so the rules are tested without a
 * database.
 *
 * Groups, in the order the hub acts on them: Ready to go (the hub's own next step), Chase (someone else owes a step
 * and the clock is against it), In progress (nothing owed yet), Done (released). Every row carries at least one
 * reason; with several, the earliest group wins and the first reason in REASONS order leads.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rules {
    /** Group: the hub's own next step. */
    const READY = 'ready';
    /** Group: someone else owes a step and the clock is against it. */
    const CHASE = 'chase';
    /** Group: nothing owed yet. */
    const PROGRESS = 'progress';
    /** Group: released. */
    const DONE = 'done';

    /** Groups in the order the hub acts on them. */
    const GROUPS = [self::READY, self::CHASE, self::PROGRESS, self::DONE];

    /** Reasons, in the order they lead a row, each with its group. */
    const REASONS = [
        'release' => self::READY,
        'allocate' => self::READY,
        'late' => self::CHASE,
        'risk' => self::CHASE,
        'notsent' => self::CHASE,
        'early' => self::CHASE,
        'changedsent' => self::CHASE,
        'upcoming' => self::PROGRESS,
        'ontrack' => self::PROGRESS,
        // A mark changed since release leads, so the header and the row say what changed (P9).
        'changedrel' => self::DONE,
        'released' => self::DONE,
    ];

    /**
     * An assessment's group and reasons.
     *
     * @param \stdClass $a Aggregates: due, markingdue (effective, null for none), units (ready to mark), unmarked
     *     (units with marking outstanding at some stage), released, norelease, hasallocation, unallocated (students
     *     without a marker), sendstep, tosend (units the send step should cover), sent, early (sent before marked),
     *     changedsent, changedrel.
     * @param int $now
     * @param int $riskdays Days before the marking deadline from which unmarked work is at risk (D4).
     * @param int $allocdays The view's allocation window in days (P10).
     * @return \stdClass group (a GROUPS value), reasons (REASONS keys in order), reason (the leading one).
     */
    public static function classify(\stdClass $a, int $now, int $riskdays, int $allocdays): \stdClass {
        $reasons = [];
        $units = (int)$a->units;
        $unmarked = (int)$a->unmarked;
        $marked = $units > 0 && $unmarked === 0;
        $due = empty($a->due) ? null : (int)$a->due;
        if ($marked && ((int)$a->released >= $units || !empty($a->norelease))) {
            // Released is the end state the block can see (D5); a mark changed afterwards is tagged.
            $reasons[] = 'released';
            if ((int)$a->changedrel > 0) {
                $reasons[] = 'changedrel';
            }
        } else if ($due !== null && $due > $now) {
            // Before the deadline: unallocated students are the hub's job inside the view's window (P10).
            $reasons[] = !empty($a->hasallocation) && (int)$a->unallocated > 0 && $due - $now <= $allocdays * DAYSECS
                ? 'allocate' : 'upcoming';
        } else if ($marked) {
            // Fully marked: ready to release once the send step, where there is one, covers every unit (D10).
            $reasons[] = !empty($a->sendstep) && (int)$a->sent < (int)$a->tosend ? 'notsent' : 'release';
        } else if ($unmarked > 0 && !empty($a->markingdue)) {
            $left = (int)$a->markingdue - $now;
            $reasons[] = $left < 0 ? 'late' : ($left <= $riskdays * DAYSECS ? 'risk' : 'ontrack');
        } else {
            // Nothing to mark yet, or no marking deadline to judge by.
            $reasons[] = 'ontrack';
        }
        // Work sent before it was marked never counts as ready, and a mark changed after sending is chased (D10).
        if ((int)$a->early > 0) {
            $reasons[] = 'early';
        }
        if ((int)$a->changedsent > 0) {
            $reasons[] = 'changedsent';
        }
        if (array_intersect(['early', 'changedsent'], $reasons)) {
            // Not ready to release while any unit was sent unmarked or changed since (D10).
            $reasons = array_diff($reasons, ['release']);
        }
        $reasons = array_values(array_intersect(array_keys(self::REASONS), $reasons));
        $group = self::DONE;
        foreach ($reasons as $reason) {
            if (array_search(self::REASONS[$reason], self::GROUPS) < array_search($group, self::GROUPS)) {
                $group = self::REASONS[$reason];
            }
        }
        // Released work stays Done whatever else is tagged; a Ready row with a Chase tag is chased first.
        if (in_array('released', $reasons)) {
            $group = self::DONE;
        }
        return (object)['group' => $group, 'reasons' => $reasons, 'reason' => $reasons[0]];
    }

    /**
     * Where a row sorts in its group: Chase late first then by days left (both by marking deadline), Ready to go and
     * In progress soonest deadline first, Done latest release first. Ties go by name, at the caller.
     *
     * @param \stdClass $a Aggregates as classify(), with timereleased (the latest release).
     * @param \stdClass $class From classify().
     * @return int
     */
    public static function sort_key(\stdClass $a, \stdClass $class): int {
        switch ($class->group) {
            case self::DONE:
                return -(int)($a->timereleased ?? 0);
            case self::CHASE:
                return (int)($a->markingdue ?? $a->due ?? PHP_INT_MAX);
            case self::READY:
                return (int)($a->due ?? $a->markingdue ?? PHP_INT_MAX);
            default:
                return $class->reason === 'upcoming' ? (int)($a->due ?? PHP_INT_MAX)
                    : (int)($a->markingdue ?? $a->due ?? PHP_INT_MAX);
        }
    }

    /**
     * Whether a unit's marks were sent to the hub before it was marked (D10): sent, and not marked.
     *
     * @param \stdClass $m Mark index row.
     * @return bool
     */
    public static function is_early(\stdClass $m): bool {
        return $m->timesent !== null && empty($m->marked);
    }

    /** SQL for is_early() on a mark index row (alias m). */
    const EARLY_SQL = '(m.timesent IS NOT NULL AND m.marked = 0)';

    /**
     * Whether a unit's mark changed after its marks were sent (D10).
     *
     * @param \stdClass $m Mark index row.
     * @return bool
     */
    public static function is_changed_since_sent(\stdClass $m): bool {
        return $m->timesent !== null && !empty($m->marked) && (int)$m->timemarked > (int)$m->timesent;
    }

    /** SQL for is_changed_since_sent() on a mark index row (alias m). */
    const CHANGED_SENT_SQL = '(m.timesent IS NOT NULL AND m.marked = 1 AND m.timemarked > m.timesent)';

    /**
     * Whether a student's mark changed after it was released to them (D10).
     *
     * @param \stdClass $m Mark index row.
     * @param \stdClass $s The student's index row.
     * @return bool
     */
    public static function is_changed_since_released(\stdClass $m, \stdClass $s): bool {
        return !empty($m->marked) && !empty($m->released) && $s->timereleased !== null
            && (int)$m->timemarked > (int)$s->timereleased;
    }

    /**
     * SQL on the index (alias c, parameter :latenow) for an assessment with submitted work still unmarked after its
     * marking deadline: "Marking late" (D4) as a filter, so late marking stays in view whatever year is picked.
     */
    const LATE_ANY_YEAR_SQL = "c.cmid IN (SELECT lm.cmid
                                            FROM {block_le_dashboard_mark} lm
                                            JOIN {block_le_dashboard_cm} lc ON lc.cmid = lm.cmid
                                           WHERE lm.marked = 0 AND lm.submitted = 1 AND lm.counted = 1
                                                 AND lc.markingdue > 0 AND lc.markingdue < :latenow)";

    /** SQL for is_changed_since_released() on a mark row (m) joined to its student's row (s). */
    const CHANGED_RELEASED_SQL = '(m.marked = 1 AND m.released = 1 AND s.timereleased IS NOT NULL'
        . ' AND m.timemarked > s.timereleased)';
}
