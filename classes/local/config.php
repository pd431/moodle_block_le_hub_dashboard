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
 * The block's own settings, read in one place. Everything else (hint switches, the default marking period, the
 * feedback delay) is My assessments' and is read through its config class.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class config {
    /** Marking is at risk this many days before its deadline with work still unmarked (D4). */
    const DEFAULT_RISK_DAYS = 5;

    /** A new view flags unallocated work from this many days before the submission deadline (P10). */
    const DEFAULT_ALLOC_DAYS = 30;

    /** The user preferences the block keeps. */
    const PREFERENCES = ['view', 'type', 'year', 'lastrefresh', 'refreshing'];

    /**
     * Days before the marking deadline from which unmarked work is at risk (D4).
     *
     * @return int
     */
    public static function risk_days(): int {
        return self::number('riskdays', self::DEFAULT_RISK_DAYS);
    }

    /**
     * The allocation window a new view starts with (P10).
     *
     * @return int
     */
    public static function alloc_days(): int {
        return self::number('allocdays', self::DEFAULT_ALLOC_DAYS);
    }

    /**
     * A whole-number setting, or its default when unset.
     *
     * @param string $name
     * @param int $default
     * @return int
     */
    protected static function number(string $name, int $default): int {
        $value = get_config('block_le_hub_dashboard', $name);
        return $value === false || $value === '' ? $default : max(0, (int)$value);
    }

    /**
     * A user preference of the block.
     *
     * @param string $name One of PREFERENCES.
     * @param int $userid
     * @param mixed $default
     * @return mixed
     */
    public static function preference(string $name, int $userid, $default = null) {
        return get_user_preferences('block_le_hub_dashboard_' . $name, $default, $userid);
    }

    /**
     * Saves a user preference of the block.
     *
     * @param string $name One of PREFERENCES.
     * @param mixed $value
     * @param int $userid
     */
    public static function set_preference(string $name, $value, int $userid): void {
        set_user_preference('block_le_hub_dashboard_' . $name, $value, $userid);
    }
}
