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

namespace block_le_hub_dashboard\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use block_le_dashboard\local\config as maconfig;
use block_le_dashboard\local\index\indexer;
use block_le_hub_dashboard\local\config;

/**
 * Re-indexes what the dashboard shows, inline when small enough, else in the background, as My assessments' Refresh.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh extends base {
    /** Seconds between two refreshes of the same view that do any work. */
    const MIN_INTERVAL = 60;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(self::state_parameters());
    }

    /**
     * Refreshes and renders.
     *
     * @param int $viewid
     * @param int $yearid
     * @param string $type
     * @param int $personid
     * @return array
     */
    public static function execute(int $viewid = -1, int $yearid = -1, string $type = '', int $personid = 0): array {
        $state = self::validate_parameters(self::execute_parameters(), ['viewid' => $viewid, 'yearid' => $yearid, 'type' => $type,
            'personid' => $personid]);
        $userid = self::user();
        $dashboard = self::dashboard($userid, $state);
        $cmids = $dashboard->cmids();
        $key = $state['personid'] ? 'p' . $state['personid'] : 'v' . $dashboard->state()['viewid'];
        $last = json_decode((string)config::preference('lastrefresh', $userid, ''), true);
        $last = is_array($last) ? $last : [];
        $ok = true;
        if ((int)($last[$key] ?? 0) > time() - self::MIN_INTERVAL || !$cmids) {
            $status = 'done';
        } else if (count($cmids) <= maconfig::inline_refresh_limit()) {
            try {
                (new indexer())->index_cms($cmids);
            } catch (\Throwable $e) {
                $ok = false;
            }
            $status = 'done';
        } else {
            config::set_preference('refreshing', time(), $userid);
            $task = new \block_le_dashboard\task\reindex();
            $task->set_custom_data(['cmids' => array_values($cmids), 'userid' => $userid]);
            \core\task\manager::queue_adhoc_task($task, true);
            $status = 'queued';
        }
        if ($ok && $status === 'done' && $cmids) {
            $last[$key] = time();
            config::set_preference('lastrefresh', json_encode($last), $userid);
        }
        return ['status' => $status] + self::render(self::dashboard($userid, $state));
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $structure = self::render_returns();
        $structure->keys['status'] = new \core_external\external_value(PARAM_ALPHA, 'done or queued');
        return $structure;
    }
}
