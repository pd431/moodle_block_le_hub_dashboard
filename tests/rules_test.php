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

namespace block_le_hub_dashboard;

use block_le_hub_dashboard\local\rules;

/**
 * The grouping rules (P9, P10) on their own: a table of aggregates to the group and reasons they give, the order
 * within a group, and the SQL and PHP forms of each unit rule agreeing on every combination of a row's fields.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_le_hub_dashboard\local\rules
 */
final class rules_test extends \advanced_testcase {
    /** A fixed clock: a Wednesday at noon. */
    const NOW = 1770206400;

    /**
     * Aggregates for one assessment, with every field present.
     *
     * @param array $set Fields to set.
     * @return \stdClass
     */
    protected static function aggregates(array $set): \stdClass {
        return (object)($set + ['due' => self::NOW - 10 * DAYSECS, 'markingdue' => self::NOW + 10 * DAYSECS,
            'units' => 2, 'unmarked' => 2, 'released' => 0, 'norelease' => 0, 'hasallocation' => 0, 'unallocated' => 0,
            'sendstep' => 0, 'tosend' => 0, 'sent' => 0, 'early' => 0, 'changedsent' => 0, 'changedrel' => 0,
            'timereleased' => null]);
    }

    /**
     * Each situation and where it lands.
     *
     * @return array[] label, fields, group, reasons in order
     */
    public static function situations(): array {
        $d = DAYSECS;
        return [
            'unmarked, deadline far off' => [[], rules::PROGRESS, ['ontrack']],
            'unmarked, deadline inside the risk window' => [['markingdue' => self::NOW + 2 * $d], rules::CHASE, ['risk']],
            'unmarked, deadline passed' => [['markingdue' => self::NOW - $d], rules::CHASE, ['late']],
            'unmarked, no marking deadline' => [['markingdue' => null], rules::PROGRESS, ['ontrack']],
            'nothing handed in yet' => [['units' => 0, 'unmarked' => 0], rules::PROGRESS, ['ontrack']],
            'not yet due' => [['due' => self::NOW + 20 * $d], rules::PROGRESS, ['upcoming']],
            'not yet due, allocation outside the window' => [['due' => self::NOW + 40 * $d, 'hasallocation' => 1,
                'unallocated' => 3], rules::PROGRESS, ['upcoming']],
            'not yet due, allocation inside the window' => [['due' => self::NOW + 20 * $d, 'hasallocation' => 1,
                'unallocated' => 3], rules::READY, ['allocate']],
            'not yet due, everyone allocated' => [['due' => self::NOW + 20 * $d, 'hasallocation' => 1, 'unallocated' => 0],
                rules::PROGRESS, ['upcoming']],
            'fully marked, no send step' => [['unmarked' => 0], rules::READY, ['release']],
            'fully marked, all sent' => [['unmarked' => 0, 'sendstep' => 1, 'tosend' => 2, 'sent' => 2], rules::READY, ['release']],
            'fully marked, not all sent' => [['unmarked' => 0, 'sendstep' => 1, 'tosend' => 2, 'sent' => 1], rules::CHASE,
                ['notsent']],
            'sent before marking finished' => [['unmarked' => 1, 'markingdue' => self::NOW - $d, 'sendstep' => 1, 'tosend' => 2,
                'sent' => 2, 'early' => 1], rules::CHASE, ['late', 'early']],
            'sent then changed' => [['unmarked' => 0, 'sendstep' => 1, 'tosend' => 2, 'sent' => 2, 'changedsent' => 1],
                rules::CHASE, ['changedsent']],
            'released' => [['unmarked' => 0, 'released' => 2, 'timereleased' => self::NOW - $d], rules::DONE, ['released']],
            'released with no release step' => [['unmarked' => 0, 'norelease' => 1], rules::DONE, ['released']],
            'released then changed' => [['unmarked' => 0, 'released' => 2, 'changedrel' => 1], rules::DONE,
                ['changedrel', 'released']],
            'released but a unit sent early is still Done' => [['unmarked' => 0, 'released' => 2, 'early' => 1], rules::DONE,
                ['early', 'released']],
        ];
    }

    /**
     * The group and reasons of each situation.
     *
     * @dataProvider situations
     * @param array $set
     * @param string $group
     * @param string[] $reasons
     */
    public function test_classify(array $set, string $group, array $reasons): void {
        $class = rules::classify(self::aggregates($set), self::NOW, 5, 30);
        $this->assertSame($group, $class->group);
        $this->assertSame($reasons, $class->reasons);
        $this->assertSame($reasons[0], $class->reason);
    }

    /**
     * Every reason has a group, and the reasons list in group order so the first one always names the group.
     */
    public function test_reasons_are_in_group_order(): void {
        $last = 0;
        foreach (rules::REASONS as $reason => $group) {
            $at = array_search($group, rules::GROUPS);
            $this->assertNotFalse($at, "$reason has a group");
            $this->assertGreaterThanOrEqual($last, $at, "$reason is in group order");
            $last = $at;
        }
        $this->assertSame(array_values(array_unique(array_values(rules::REASONS))), rules::GROUPS);
    }

    /**
     * Order within a group: Chase by marking deadline, Ready to go by deadline, Done latest release first.
     */
    public function test_sort_key(): void {
        $d = DAYSECS;
        $late = self::aggregates(['markingdue' => self::NOW - 3 * $d]);
        $later = self::aggregates(['markingdue' => self::NOW - $d]);
        $this->assertLessThan(rules::sort_key($later, rules::classify($later, self::NOW, 5, 30)),
            rules::sort_key($late, rules::classify($late, self::NOW, 5, 30)), 'the latest marking first in Chase');

        $soon = self::aggregates(['unmarked' => 0, 'due' => self::NOW - 5 * $d]);
        $sooner = self::aggregates(['unmarked' => 0, 'due' => self::NOW - 8 * $d]);
        $this->assertLessThan(rules::sort_key($soon, rules::classify($soon, self::NOW, 5, 30)),
            rules::sort_key($sooner, rules::classify($sooner, self::NOW, 5, 30)), 'the earliest deadline first in Ready');

        $old = self::aggregates(['unmarked' => 0, 'released' => 2, 'timereleased' => self::NOW - 9 * $d]);
        $new = self::aggregates(['unmarked' => 0, 'released' => 2, 'timereleased' => self::NOW - $d]);
        $this->assertLessThan(rules::sort_key($old, rules::classify($old, self::NOW, 5, 30)),
            rules::sort_key($new, rules::classify($new, self::NOW, 5, 30)), 'the latest release first in Done');
    }

    /**
     * The SQL and PHP forms of the unit rules agree on every combination of the fields they read.
     */
    public function test_sql_and_php_forms_agree(): void {
        global $DB;
        $this->resetAfterTest();
        $cmid = 990001;
        $DB->insert_record('block_le_dashboard_cm', (object)['cmid' => $cmid, 'courseid' => 1, 'modname' => 'assign',
            'adapter' => 'assign', 'name' => 'Twin', 'assesstype' => 'summative', 'stages' => 'marked']);
        $rows = [];
        $userid = 0;
        foreach ([0, 1] as $marked) {
            foreach ([0, 1] as $released) {
                foreach ([null, 100, 200, 300] as $timemarked) {
                    foreach ([null, 200] as $timesent) {
                        foreach ([null, 200] as $timereleased) {
                            $userid++;
                            $DB->insert_record('block_le_dashboard_mark', (object)['cmid' => $cmid, 'unitid' => $userid,
                                'unittype' => 'user', 'stage' => 'marked', 'submitted' => 1, 'marked' => $marked,
                                'timemarked' => $timemarked, 'released' => $released, 'timesent' => $timesent]);
                            $DB->insert_record('block_le_dashboard_stu', (object)['cmid' => $cmid, 'userid' => $userid,
                                'submitted' => 1, 'timereleased' => $timereleased]);
                            $rows[$userid] = [(object)['marked' => $marked, 'released' => $released, 'timemarked' => $timemarked,
                                'timesent' => $timesent], (object)['timereleased' => $timereleased]];
                        }
                    }
                }
            }
        }
        $this->assertCount(64, $rows);
        $early = $DB->get_fieldset_sql("SELECT m.unitid FROM {block_le_dashboard_mark} m WHERE m.cmid = :cmid AND " .
            rules::EARLY_SQL, ['cmid' => $cmid]);
        $changedsent = $DB->get_fieldset_sql("SELECT m.unitid FROM {block_le_dashboard_mark} m WHERE m.cmid = :cmid AND " .
            rules::CHANGED_SENT_SQL, ['cmid' => $cmid]);
        $changedrel = $DB->get_fieldset_sql("SELECT m.unitid FROM {block_le_dashboard_mark} m
                                               JOIN {block_le_dashboard_stu} s ON s.cmid = m.cmid AND s.userid = m.unitid
                                              WHERE m.cmid = :cmid AND " . rules::CHANGED_RELEASED_SQL, ['cmid' => $cmid]);
        foreach ($rows as $id => [$m, $s]) {
            $label = json_encode($m) . json_encode($s);
            $this->assertSame(rules::is_early($m), in_array($id, $early), "early: $label");
            $this->assertSame(rules::is_changed_since_sent($m), in_array($id, $changedsent), "changed since sent: $label");
            $this->assertSame(rules::is_changed_since_released($m, $s), in_array($id, $changedrel),
                "changed since released: $label");
        }
        $this->assertNotEmpty($early);
        $this->assertNotEmpty($changedsent);
        $this->assertNotEmpty($changedrel);
    }
}
