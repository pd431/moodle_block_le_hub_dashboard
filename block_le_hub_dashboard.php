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

/**
 * The Hub dashboard block: what hubs (education support teams) owe, chase and have done across the assessments of the
 * modules they look after, read from My assessments' index (block_le_dashboard).
 *
 * @package    block_le_hub_dashboard
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_le_hub_dashboard extends block_base {
    /**
     * Sets the block title.
     */
    public function init() {
        $this->title = get_string('blocktitle', 'block_le_hub_dashboard');
    }

    #[\Override]
    public function has_config() {
        return true;
    }

    #[\Override]
    public function instance_allow_multiple() {
        return false;
    }

    #[\Override]
    public function applicable_formats() {
        return ['my' => true, 'site-index' => true, 'course-view' => false, 'mod' => false];
    }

    #[\Override]
    public function get_content() {
        global $USER;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object)['text' => '', 'footer' => ''];
        // People without the hub capability never see the block, even when it is on their page (D1).
        if (!isloggedin() || isguestuser() || !\block_le_hub_dashboard\local\access::allowed((int)$USER->id)) {
            return $this->content;
        }
        $output = $this->page->get_renderer('core');
        $dashboard = new \block_le_hub_dashboard\output\dashboard((int)$USER->id);
        $context = $dashboard->export_for_template($output);
        $this->content->text = $output->render_from_template('block_le_hub_dashboard/dashboard', $context);
        $this->page->requires->js_call_amd('block_le_hub_dashboard/hub', 'init', [$context['uniqid'], $context['state']]);
        return $this->content;
    }

    #[\Override]
    public function is_empty() {
        $content = $this->get_content();
        return empty($content->text);
    }
}
