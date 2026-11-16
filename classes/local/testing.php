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
 * The hub's test seams, behind one gate: a slow row fetch, so the tests can see the frame arrive first and the
 * load budget run out. Production never reads a test config key.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class testing {
    /**
     * Whether a test is running.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (defined('PHPUNIT_TEST') && PHPUNIT_TEST) || (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING);
    }

    /**
     * Whether a test has slowed the rows down, so the page must not hold Behat until they land: the test is
     * watching the frame fill in.
     *
     * @return bool
     */
    public static function watching_rows(): bool {
        return self::enabled() && (int)get_config('block_le_hub_dashboard', 'testslowrows') > 0;
    }

    /**
     * Holds a row fetch for the seconds a test asked for (`testslowrows`).
     */
    public static function slow_rows(): void {
        if (!self::enabled()) {
            return;
        }
        $seconds = (int)get_config('block_le_hub_dashboard', 'testslowrows');
        if ($seconds <= 0) {
            return;
        }
        sleep($seconds);
    }
}
