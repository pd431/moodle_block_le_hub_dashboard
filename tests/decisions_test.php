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

/**
 * Every decision key a code comment cites resolves in DECISIONS.md (the hub's own, or My assessments' HB series),
 * and no comment dates a rule: the why lives in the decisions file, the code says which decision it follows.
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class decisions_test extends \basic_testcase {
    /**
     * The keys cited under classes/ each have a line in a decisions file, and the dates live there, not in the code.
     */
    public function test_every_key_in_a_comment_resolves(): void {
        global $CFG;
        $root = $CFG->dirroot . '/blocks/le_hub_dashboard';
        $decisions = file_get_contents("$root/DECISIONS.md");
        $ma = $CFG->dirroot . '/blocks/le_dashboard/DECISIONS.md';
        if (is_readable($ma)) {
            $decisions .= file_get_contents($ma);
        }
        preg_match_all('~^- \*\*((?:D|P|HB)\d+)\*\*~m', $decisions, $m);
        $listed = array_flip($m[1]);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/classes", \FilesystemIterator::SKIP_DOTS));
        $missing = [];
        $dated = [];
        foreach ($files as $file) {
            $path = substr($file->getPathname(), strlen($root) + 1);
            foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
                if (!is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT])) {
                    continue;
                }
                if (preg_match('~\b20\d\d-\d\d-\d\d\b~', $token[1])) {
                    $dated[] = "$path:{$token[2]}";
                }
                preg_match_all('~(?<![A-Za-z0-9])(D|P|HB)(\d+)\b~', $token[1], $codes, PREG_SET_ORDER);
                foreach ($codes as $code) {
                    if (!isset($listed[$code[0]])) {
                        $missing[$code[0]] = "{$code[0]} ($path:{$token[2]})";
                    }
                }
            }
        }
        $this->assertSame([], array_values($missing), "Keys without a line in DECISIONS.md:\n" . implode("\n", $missing));
        $this->assertSame([], $dated, "Dated rules belong in DECISIONS.md:\n" . implode("\n", $dated));
    }
}
