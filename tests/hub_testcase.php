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

use block_le_dashboard\fixture\failed;
use block_le_dashboard\fixture\steps;
use block_le_dashboard\fixture\unavailable;
use block_le_dashboard\view_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/le_dashboard/tests/view_testcase.php');

/**
 * Runs the hub scopes' sentences from PHPUnit, as My assessments' view_testcase runs its own: the hub's sentences
 * are matched against the hub Behat context first (tests/behat/behat_block_le_hub_dashboard.php), then My
 * assessments'. A sentence that writes runs block_le_hub_dashboard_sentences, as the context does; one that reads
 * the Hub dashboard is checked here against the block's rendered HTML, with the selectors the Behat steps use.
 *
 * @package    block_le_hub_dashboard
 * @category   test
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class hub_testcase extends view_testcase {
    /** @var string The hub context, relative to the hub plugin. */
    const HUB_CONTEXT = 'tests/behat/behat_block_le_hub_dashboard.php';

    /** @var array[]|null The hub context's sentences: regex => method. */
    private static ?array $hubsentences = null;

    /** @var bool Whether the rendered dashboard is the hub's. */
    protected bool $hub = false;

    /** @var int|null A person whose assessments the hub shows (P2). */
    protected ?int $hubperson = null;

    /** @var string|null The type filter the hub shows. */
    protected ?string $hubtype = null;

    /** @var array[] Core sentences the hub scopes use, with a twin here: regex => method. */
    const HUB_CORE = [
        '/^I click on "(?P<text>[^"]*)" "(?P<type>[^"]*)" in the "Hub dashboard" "block"$/' => 'i_click_in_the_hub_block',
        '/^I reload the page$/' => 'i_view_my_dashboard_again',
    ];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->getDataGenerator()->get_plugin_generator('block_le_hub_dashboard');
        $this->hub = false;
        $this->hubperson = null;
        $this->hubtype = null;
    }

    #[\Override]
    protected function step(string $sentence, array $table = []): void {
        $text = preg_replace('/^(Given|When|Then|And|But) /', '', trim($sentence));
        foreach (self::HUB_CORE + self::hub_sentences() as $regex => $method) {
            if (!preg_match($regex, $text, $m)) {
                continue;
            }
            $args = array_values(array_filter($m, 'is_int', ARRAY_FILTER_USE_KEY));
            array_shift($args);
            if ($table) {
                $args[] = $table;
            }
            try {
                if (method_exists($this, $method)) {
                    $this->$method(...$args);
                } else if (method_exists(\block_le_hub_dashboard_sentences::class, $method)) {
                    \block_le_hub_dashboard_sentences::$method(...$args);
                } else {
                    throw new \coding_exception("No twin for the hub sentence '$text'");
                }
            } catch (unavailable $e) {
                $this->markTestSkipped($e->getMessage());
            } catch (failed $e) {
                $this->fail($e->getMessage() . "\n  at: $text");
            }
            $this->resetDebugging();
            $this->addToAssertionCount(1);
            return;
        }
        parent::step($sentence, $table);
    }

    /**
     * The hub context's sentences.
     *
     * @return string[] regex => method
     */
    private static function hub_sentences(): array {
        global $CFG;
        if (self::$hubsentences === null) {
            $src = file_get_contents($CFG->dirroot . '/blocks/le_hub_dashboard/' . self::HUB_CONTEXT);
            preg_match_all(
                '~@(?:Given|When|Then) (/.*?/)\n(?:[^\n]*\n)*?\s*public function (\w+)\(~',
                $src,
                $matches,
                PREG_SET_ORDER
            );
            self::$hubsentences = [];
            foreach ($matches as $match) {
                self::$hubsentences[$match[1]] = $match[2];
            }
        }
        return self::$hubsentences;
    }

    #[\Override]
    protected function the_following_exist(string $type, array $rows): void {
        if ($type !== 'categories') {
            parent::the_following_exist($type, $rows);
            return;
        }
        global $DB;
        foreach ($rows as $row) {
            $parent = empty($row['category']) || $row['category'] === '0' ? 0
                : (int)$DB->get_field('course_categories', 'id', ['idnumber' => $row['category']], MUST_EXIST);
            $this->getDataGenerator()->create_category(['name' => $row['name'], 'idnumber' => $row['idnumber'] ?? '',
                'parent' => $parent]);
        }
    }

    // Opening the hub.

    #[\Override]
    protected function i_log_in_and_view(string $username, string $view, string $year = ''): void {
        if ($view !== get_string('blocktitle', 'block_le_hub_dashboard')) {
            parent::i_log_in_and_view($username, $view, $year);
            return;
        }
        $this->i_log_in_as($username);
        // A fresh page: the type comes from the saved preference and no person is searched for.
        $this->hubtype = null;
        $this->hubperson = null;
        $this->render_hub();
    }

    /**
     * Logs in with a view of the categories and opens the hub.
     *
     * @param string $username
     * @param string $paths
     */
    protected function i_log_in_and_pick(string $username, string $paths): void {
        \block_le_hub_dashboard_sentences::picked_categories($username, $paths);
        $this->i_log_in_as($username);
        // A fresh page: the type comes from the saved preference and no person is searched for.
        $this->hubtype = null;
        $this->hubperson = null;
        $this->render_hub();
    }

    /**
     * Renders the Hub dashboard as the block does, for the viewer, after the index catches up.
     */
    protected function render_hub(): void {
        global $PAGE, $USER;
        $viewer = $this->viewer ?? (int)$USER->id;
        steps::instance()->catch_up();
        $this->setUser($viewer);
        $output = $PAGE->get_renderer('core');
        $context = (new local\dashboard($viewer, null, null, $this->hubtype, $this->hubperson))->export();
        $html = $output->render_from_template('block_le_hub_dashboard/dashboard', $context);
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div class="block_le_hub_dashboard">' . $html . '</div>');
        libxml_clear_errors();
        $this->dom = new \DOMXPath($doc);
        // The type filter runs in the browser on each row's type; here the rows it would hide go.
        $type = $context['type'] ?? 'both';
        if ($type !== 'both') {
            foreach ($this->dom->query("//li[@data-region='hub-row'][@data-assesstype != '$type']") as $row) {
                $row->parentNode->removeChild($row);
            }
        }
        $this->hub = true;
    }

    /**
     * What a person sees of a node: its text less anything hidden or inside a template.
     *
     * @param \DOMNode $node
     * @return string
     */
    protected function visible_text(\DOMNode $node): string {
        $parts = [];
        foreach ($this->dom->query('.//text()[not(ancestor::*[@hidden]) and not(ancestor::template)]', $node) as $text) {
            $parts[] = $text->textContent;
        }
        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }

    /**
     * Whether the expected items appear in the actual list in that order, others allowed between.
     *
     * @param string[] $expected
     * @param string[] $actual
     * @return bool
     */
    protected static function in_order(array $expected, array $actual): bool {
        $at = 0;
        foreach ($actual as $item) {
            if (isset($expected[$at]) && $item === $expected[$at]) {
                $at++;
            }
        }
        return $at === count($expected);
    }

    /**
     * The rendered dashboard again.
     */
    protected function i_view_my_dashboard_again(): void {
        if ($this->hub) {
            $this->render_hub();
            return;
        }
        parent::i_view_my_dashboard_again();
    }

    /**
     * Buttons and links of the hub: Refresh, Clear, the type buttons and the view menu's items.
     *
     * @param string $text
     * @param string $type
     */
    protected function i_click_in_the_hub_block(string $text, string $type): void {
        $c = 'block_le_hub_dashboard';
        if ($text === get_string('refresh', $c)) {
            $this->render_hub();
        } else if ($text === get_string('clear', $c)) {
            $this->hubperson = null;
            $this->render_hub();
        } else if (in_array(strtolower($text), local\dashboard::TYPES)) {
            $this->hubtype = strtolower($text);
            $this->render_hub();
        } else if (!in_array($text, [get_string('thisview', $c), get_string('editview', $c)])) {
            throw new \coding_exception("No twin clicks '$text'");
        }
    }

    /**
     * The hub for one person (P2).
     *
     * @param string $name
     */
    protected function i_search_for_the_person(string $name): void {
        global $DB;
        [$first, $last] = explode(' ', $name, 2);
        $this->hubperson = (int)$DB->get_field('user', 'id', ['firstname' => $first, 'lastname' => $last], MUST_EXIST);
        $this->render_hub();
    }

    /**
     * The open view's allocation window (P10).
     *
     * @param string $days
     */
    protected function i_set_the_allocation_window(string $days): void {
        $view = local\views::current($this->viewer);
        local\views::set_alloc_days((int)$view->id, $this->viewer, (int)$days);
        $this->render_hub();
    }

    /**
     * Text under a heading of the editor panel.
     *
     * @param string $text
     * @param string $heading
     */
    protected function i_should_see_in_the_editor_under(string $text, string $heading): void {
        $section = $this->find("//*[contains(@class, 'block_le_hub_dashboard')]//*[contains(@class, 'hub-panel-section')]" .
            "[.//*[contains(@class, 'hub-panel-label')][normalize-space(.) = " . self::literal($heading) . ']]');
        if (!$section || !str_contains(self::text($section), $text)) {
            throw new failed("'$text' is not under '$heading' in the editor");
        }
    }

    /**
     * A link in the editor: Put back is the only one the scenarios click.
     *
     * @param string $text
     */
    protected function i_click_in_the_editor(string $text): void {
        $link = $this->find("//*[contains(@class, " .
            "'block_le_hub_dashboard')]//*[@data-region='hub-editor']//a[normalize-space(.) = " .
            self::literal($text) . ']');
        if (!$link || $link->getAttribute('data-action') !== 'putback') {
            throw new failed("No '$text' link in the editor");
        }
        $view = local\views::current($this->viewer);
        local\views::remove_exception(
            (int)$view->id,
            $this->viewer,
            $link->getAttribute('data-kind'),
            (int)$link->getAttribute('data-value')
        );
        $this->render_hub();
    }

    /**
     * The editor's module count.
     *
     * @param string $text
     */
    protected function editor_should_say(string $text): void {
        $count = $this->find("//*[contains(@class, 'block_le_hub_dashboard')]//*[@data-region='hub-editor-count']");
        if (!$count || self::text($count) !== $text) {
            throw new failed("The editor says '" . ($count ? self::text($count) : '') . "'");
        }
    }

    // Reading the hub.

    #[\Override]
    protected function row(string $name): \DOMElement {
        if (!$this->hub) {
            return parent::row($name);
        }
        return $this->find("//*[contains(@class, 'block_le_hub_dashboard')]//li[@data-region='hub-row']" .
            "[.//a[contains(@class, 'hub-row-name')][normalize-space(.) = " . self::literal($name) . ']]')
            ?? throw new failed("No row for '$name'");
    }

    /**
     * A group's section by its title.
     *
     * @param string $group
     * @return \DOMElement
     */
    protected function group(string $group): \DOMElement {
        return $this->find("//*[contains(@class, 'block_le_hub_dashboard')]//section[@data-region='hub-group']" .
            "[.//*[contains(@class, 'hub-group-title')][normalize-space(.) = " . self::literal($group) . ']]')
            ?? throw new failed("No group '$group'");
    }

    /**
     * Text in the hub block.
     *
     * @param string $not
     * @param string $text
     */
    protected function i_should_see_in_the_hub_block(string $not, string $text): void {
        $shown = $this->visible_text($this->find("//*[contains(@class, 'block_le_hub_dashboard')]"));
        if (str_contains($shown, $text) === (bool)$not) {
            throw new failed('The Hub dashboard ' . ($not ? 'shows' : 'does not show') . " '$text'");
        }
    }

    /**
     * A row is in a group, leading with a reason.
     *
     * @param string $name
     * @param string $group
     * @param string $reason
     */
    protected function should_be_in_group(string $name, string $group, string $reason = ''): void {
        $row = $this->find(".//li[@data-region='hub-row'][.//a[contains(@class, 'hub-row-name')][normalize-space(.) = " .
            self::literal($name) . ']]', $this->group($group));
        if (!$row) {
            $where = $this->find("ancestor::section//*[contains(@class, 'hub-group-title')]", $this->row($name));
            throw new failed("'$name' is not in '$group'" . ($where ? ' but in ' . self::text($where) : ''));
        }
        if ($reason !== '') {
            $tag = $this->find(".//*[contains(@class, 'hub-reason')][1]", $row);
            if (!$tag || self::text($tag) !== $reason) {
                throw new failed("'$name' leads with '" . ($tag ? self::text($tag) : '') . "', not '$reason'");
            }
        }
    }

    /**
     * A row carries a reason tag.
     *
     * @param string $name
     * @param string $reason
     */
    protected function should_show_reason_tag(string $name, string $reason): void {
        $this->collapsed_row_should_show_reason_tag($name, $reason);
    }

    /**
     * A row carries a reason tag.
     *
     * @param string $name
     * @param string $reason
     */
    protected function collapsed_row_should_show_reason_tag(string $name, string $reason): void {
        foreach ($this->find_all(".//*[contains(@class, 'hub-reason')]", $this->row($name)) as $tag) {
            if (self::text($tag) === $reason) {
                return;
            }
        }
        throw new failed("'$name' has no '$reason' tag");
    }

    /**
     * The status sentence.
     *
     * @param string $name
     * @param string $status
     */
    protected function collapsed_row_should_show_status(string $name, string $status): void {
        $node = $this->find(".//*[@data-region='hub-row-status']", $this->row($name));
        if (!$node || self::text($node) !== $status) {
            throw new failed("'$name' shows '" . ($node ? self::text($node) : '') . "', not '$status'");
        }
    }

    #[\Override]
    protected function should_show_the_status(string $name, string $status): void {
        if ($this->hub) {
            $this->collapsed_row_should_show_status($name, $status);
            return;
        }
        parent::should_show_the_status($name, $status);
    }

    /**
     * Texts on the collapsed line.
     *
     * @param string $name
     * @param string $not
     * @param string $texts
     */
    protected function collapsed_row_should_show(string $name, string $not, string $texts): void {
        $shown = $this->row_text($this->find(".//*[@data-region='hub-row-line']", $this->row($name)));
        preg_match_all('/"([^"]*)"/', $texts, $m);
        foreach ($m[1] as $text) {
            if (str_contains($shown, $text) === (bool)$not) {
                throw new failed("The collapsed row '$name' " . ($not ? 'shows' : 'does not show') . " '$text' in '$shown'");
            }
        }
    }

    /**
     * A text absent from the collapsed line.
     *
     * @param string $name
     * @param string $text
     */
    protected function collapsed_row_should_not_show_text(string $name, string $text): void {
        $this->collapsed_row_should_show($name, 'not ', '"' . $text . '"');
    }

    /**
     * The flags on the collapsed line, by their names.
     *
     * @param string $name
     * @return string[]
     */
    protected function flag_names(string $name): array {
        return array_map(
            fn($b) => $b->getAttribute('aria-label'),
            $this->find_all(".//*[@data-region='hub-row-line']//button[@data-action='tip'][@data-hint]", $this->row($name))
        );
    }

    /**
     * Flags present on the collapsed line.
     *
     * @param string $name
     * @param string $flags
     */
    protected function collapsed_row_should_show_icon_flags(string $name, string $flags): void {
        $actual = $this->flag_names($name);
        foreach (array_map('trim', explode(',', $flags)) as $flag) {
            if (!in_array($flag, $actual)) {
                throw new failed("'$name' has flags '" . implode(', ', $actual) . "', not '$flag'");
            }
        }
    }

    /**
     * One flag present.
     *
     * @param string $name
     * @param string $flag
     */
    protected function should_show_a_flag(string $name, string $flag): void {
        $this->collapsed_row_should_show_icon_flags($name, $flag);
    }

    /**
     * Clicking a flag: its popover's text is in the page already.
     *
     * @param string $flag
     * @param string $name
     */
    protected function i_click_on_the_flag(string $flag, string $name): void {
        $button = $this->find(".//button[@data-action='tip'][@aria-label=" . self::literal($flag) . ']', $this->row($name));
        if (!$button) {
            throw new failed("No '$flag' flag on '$name'");
        }
        $tip = $this->find("//*[@id=" . self::literal($button->getAttribute('aria-controls')) . ']');
        if ($tip) {
            $tip->removeAttribute('hidden');
        }
    }

    /**
     * The stacked bar's segments.
     *
     * @param string $name
     * @param string $segments
     */
    protected function collapsed_row_should_show_stacked_bar(string $name, string $segments): void {
        $bars = $this->find_all(".//*[@data-region='hub-stack']", $this->row($name));
        if (count($bars) !== 1) {
            throw new failed("'$name' has " . count($bars) . ' stacked bars');
        }
        $label = $bars[0]->getAttribute('aria-label');
        if ($label !== $segments) {
            throw new failed("The stacked bar of '$name' reads '$label', not '$segments'");
        }
    }

    /**
     * Expanding a row: its detail is in the HTML; the browser shows it.
     *
     * @param string $how
     * @param string $name
     */
    protected function i_expand_the_row(string $how, string $name): void {
        $detail = $this->find(".//*[@data-region='hub-row-detail']", $this->row($name));
        if ($how === 'expand') {
            $detail->removeAttribute('hidden');
        } else {
            $detail->setAttribute('hidden', '');
        }
    }

    /**
     * Keyboard expansion: the same detail.
     *
     * @param string $name
     * @param string $key
     */
    protected function i_focus_the_row_and_press(string $name, string $key): void {
        $this->i_expand_the_row('expand', $name);
    }

    /**
     * The set-up items.
     *
     * @param string $name
     * @param string $items
     */
    protected function expanded_row_should_show_setup(string $name, string $items): void {
        $actual = array_map([self::class, 'text'], $this->find_all(".//li[@data-setup]", $this->row($name)));
        if ($actual !== array_map('trim', explode(',', $items))) {
            throw new failed("'$name' set-up is '" . implode(', ', $actual) . "'");
        }
    }

    /**
     * The dates' labels, in order.
     *
     * @param string $name
     * @param string $labels
     */
    protected function expanded_row_should_show_dates(string $name, string $labels): void {
        $detail = $this->find(".//*[@data-region='hub-row-detail']", $this->row($name));
        if ($detail->hasAttribute('hidden')) {
            throw new failed("'$name' is not expanded");
        }
        $actual = array_map([self::class, 'text'], $this->find_all(".//*[contains(@class, 'ma-row-date-label')]", $detail));
        if (!self::in_order(array_map('trim', explode(',', $labels)), $actual)) {
            throw new failed("'$name' dates are '" . implode(', ', $actual) . "'");
        }
    }

    /**
     * The individual bars' labels.
     *
     * @param string $name
     * @param string $labels
     */
    protected function expanded_row_should_show_bars(string $name, string $labels): void {
        $actual = array_map(
            [self::class, 'text'],
            $this->find_all(".//*[contains(@class, 'hub-detail-bars')]//*[contains(@class, 'ma-bar-label')]", $this->row($name))
        );
        if ($actual !== array_map('trim', explode(',', $labels))) {
            throw new failed("'$name' bars are '" . implode(', ', $actual) . "'");
        }
    }

    /**
     * A convenor linked to their profile.
     *
     * @param string $name
     * @param string $convenor
     */
    protected function expanded_row_should_list_convenor(string $name, string $convenor): void {
        $link = $this->find(
            ".//li[@data-role='convenor']//a[normalize-space(.) = " . self::literal($convenor) . ']',
            $this->row($name)
        );
        if (!$link || !str_contains($link->getAttribute('href'), '/user/profile.php')) {
            throw new failed("'$name' does not link '$convenor' to a profile");
        }
    }

    /**
     * Text in the detail.
     *
     * @param string $name
     * @param string $not
     * @param string $text
     */
    protected function expanded_row_should_show(string $name, string $not, string $text): void {
        $shown = self::text($this->find(".//*[@data-region='hub-row-detail']", $this->row($name)));
        if (str_contains($shown, $text) === (bool)$not) {
            throw new failed("The expanded row '$name' " . ($not ? 'shows' : 'does not show') . " '$text'");
        }
    }

    /**
     * The latest note.
     *
     * @param string $name
     * @param string $text
     * @param string $author
     */
    protected function latest_note_should_be(string $name, string $text, string $author): void {
        $note = $this->find("(.//li[@data-region='hub-note'])[1]", $this->row($name));
        if (!$note) {
            throw new failed("'$name' has no notes");
        }
        $shown = self::text($note);
        foreach ([$text, $author] as $part) {
            if (!str_contains($shown, $part)) {
                throw new failed("The latest note on '$name' reads '$shown', without '$part'");
            }
        }
        $when = $this->find('.//time', $note);
        if (!$when || !str_starts_with($when->getAttribute('datetime'), date('Y-m-d'))) {
            throw new failed("The latest note on '$name' is not dated today");
        }
    }

    /**
     * The groups' order; empty groups are hidden, so only those with rows count.
     *
     * @param string $labels
     */
    protected function groups_in_order(string $labels): void {
        $actual = array_map(
            [self::class, 'text'],
            $this->find_all("//*[contains(@class, " .
            "'block_le_hub_dashboard')]//section[@data-region='hub-group']//*[contains(@class, 'hub-group-title')]")
        );
        if ($actual !== array_map('trim', explode(',', $labels))) {
            throw new failed("Groups are '" . implode(', ', $actual) . "'");
        }
    }

    /**
     * A group header's text.
     *
     * @param string $group
     * @param string $text
     */
    protected function group_header_should_read(string $group, string $text): void {
        $header = $this->find(".//*[@data-region='hub-group-header']", $this->group($group));
        $parts = [self::text($this->find(".//*[contains(@class, 'hub-group-title')]", $header)) . ' ' .
            self::text($this->find(".//*[@data-region='hub-group-count']", $header))];
        foreach ($this->find_all(".//button[@data-action='reason']", $header) as $button) {
            $parts[] = self::text($button);
        }
        $actual = implode(' · ', $parts);
        if ($actual !== $text) {
            throw new failed("The '$group' header reads '$actual'");
        }
    }

    /**
     * The status strip.
     *
     * @param string $text
     */
    protected function strip_should_read(string $text): void {
        $parts = [];
        foreach ($this->find_all("//*[contains(@class, 'block_le_hub_dashboard')]//button[@data-action='jump']") as $pill) {
            $count = self::text($this->find(".//*[@data-region='hub-strip-count']", $pill));
            $parts[] = trim(str_replace($count, '', self::text($pill))) . " $count";
        }
        $actual = implode(', ', $parts);
        if ($actual !== $text) {
            throw new failed("The status strip reads '$actual'");
        }
    }

    /**
     * A group's rows, in order.
     *
     * @param string $group
     * @param string $names
     */
    protected function group_should_list(string $group, string $names): void {
        $actual = array_map(
            [self::class, 'text'],
            $this->find_all(".//li[@data-region='hub-row']//a[contains(@class, 'hub-row-name')]", $this->group($group))
        );
        if ($actual !== array_map('trim', explode(',', $names))) {
            throw new failed("The '$group' group lists '" . implode(', ', $actual) . "'");
        }
    }

    /**
     * A group starts collapsed.
     *
     * @param string $group
     */
    protected function group_should_be_collapsed(string $group): void {
        $toggle = $this->find(".//button[@data-action='toggle-group']", $this->group($group));
        if ($toggle->getAttribute('aria-expanded') !== 'false') {
            throw new failed("The '$group' group is open");
        }
    }

    /**
     * The column header.
     *
     * @param string $names
     */
    protected function row_columns_should_be(string $names): void {
        $actual = array_values(array_filter(array_map(
            [self::class, 'text'],
            $this->find_all("//*[contains(@class, 'block_le_hub_dashboard')]//*[contains(@class, " .
            "'hub-colhead')]/*[contains(@class, 'hub-col')]")
        )));
        if ($actual !== array_map('trim', explode(',', $names))) {
            throw new failed("The columns are '" . implode(', ', $actual) . "'");
        }
    }

    /**
     * The control bar's parts.
     *
     * @param string $names
     */
    protected function controls_should_be(string $names): void {
        $map = ['hub-viewpick' => 'View', 'hub-year' => 'Year', 'ma-type-filter' => 'Type', 'hub-find' => 'Find',
            'hub-filter' => 'Filter', 'hub-freshness' => 'Refresh'];
        $actual = [];
        foreach ($this->find_all("//*[@data-region='hub-controls']/*[contains(@class, 'hub-control')]") as $control) {
            foreach ($map as $class => $label) {
                if (self::has_class($control, $class)) {
                    $actual[] = $label;
                }
            }
        }
        // Export is not built (NOTES: flags).
        $expected = array_values(array_filter(array_map('trim', explode(',', $names)), fn($n) => $n !== 'Export'));
        if ($actual !== $expected) {
            throw new failed("The controls are '" . implode(', ', $actual) . "'");
        }
    }

    /**
     * The Filter list's groups.
     *
     * @param string $groups
     */
    protected function filter_list_groups(string $groups): void {
        $actual = array_map(
            [self::class, 'text'],
            $this->find_all("//*[contains(@class, 'block_le_hub_dashboard')]//*[contains(@class, 'hub-filter-heading')]")
        );
        if ($actual !== array_map('trim', explode(',', $groups))) {
            throw new failed("The Filter list has the groups '" . implode(', ', $actual) . "'");
        }
    }

    /**
     * Links on the collapsed line.
     *
     * @param string $name
     * @param string $text
     * @param string $where
     */
    protected function collapsed_row_should_link(string $name, string $text, string $where): void {
        $link = $this->find(
            ".//*[@data-region='hub-row-line']//a[normalize-space(.) = " . self::literal($text) . ']',
            $this->row($name)
        );
        $needle = $where === 'activity' ? '/mod/' : '/course/view.php';
        if (!$link || !str_contains($link->getAttribute('href'), $needle)) {
            throw new failed("'$text' does not link to the $where page");
        }
    }

    /**
     * The open view.
     *
     * @param string $name
     */
    protected function hub_view_should_be_open(string $name): void {
        $option = $this->find("//*[contains(@class, 'block_le_hub_dashboard')]//select[@data-action='view']/option[@selected]");
        if (!$option || self::text($option) !== $name) {
            throw new failed("The open view is '" . ($option ? self::text($option) : '') . "'");
        }
    }
}
