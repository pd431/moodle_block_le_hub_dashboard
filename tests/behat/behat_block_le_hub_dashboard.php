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

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;
use block_le_dashboard\fixture\unavailable;

/**
 * Hub dashboard steps. The sentences that write to the site run block_le_hub_dashboard_sentences (tests/generator),
 * shared with the PHPUnit scenario tests; the sentences that read the page use the same selectors as the PHPUnit
 * twins in tests/hub_testcase.php. My assessments' context supplies the assessments, submissions and marks.
 *
 * @package    block_le_hub_dashboard
 * @category   test
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_block_le_hub_dashboard extends behat_base {
    /** The block. */
    const BLOCK = '.block_le_hub_dashboard';

    /**
     * Loads the fixtures before every scenario.
     *
     * @BeforeScenario
     */
    public function load_fixtures(): void {
        testing_util::get_data_generator()->get_plugin_generator('block_le_hub_dashboard');
    }

    /**
     * Runs a sentence that writes to the site; a fixture that is not installed skips the scenario.
     *
     * @param string $method A method of block_le_hub_dashboard_sentences.
     * @param array $args
     */
    protected static function write(string $method, array $args): void {
        try {
            block_le_hub_dashboard_sentences::$method(...$args);
        } catch (unavailable $e) {
            throw new \Moodle\BehatExtension\Exception\SkippedException($e->getMessage());
        }
    }

    // Fixtures.

    /**
     * The hub fixtures from hub-view.feature exist.
     *
     * @Given /^the hub fixtures from hub-view\.feature exist$/
     */
    public function hub_fixtures_exist(): void {
        self::write('hub_fixtures_exist', []);
    }

    /**
     * The "<role>" role has the "<capability>" capability.
     *
     * @Given /^the "(?P<role>[^"]*)" role has the "(?P<capability>[^"]*)" capability$/
     * @param string $role
     * @param string $capability
     */
    public function role_has_capability(string $role, string $capability): void {
        self::write('role_has_capability', [$role, $capability]);
    }

    /**
     * "<user>" has the "<role>" role at system level.
     *
     * @Given /^"(?P<user>[^"]*)" has the "(?P<role>[^"]*)" role at system level$/
     * @param string $username
     * @param string $role
     */
    public function has_role_at_system(string $username, string $role): void {
        self::write('has_role_at_system', [$username, $role]);
    }

    /**
     * "<user>" has the "<role>" role in category "<category>".
     *
     * @Given /^"(?P<user>[^"]*)" has the "(?P<role>[^"]*)" role in category "(?P<category>[^"]*)"$/
     * @param string $username
     * @param string $role
     * @param string $category
     */
    public function has_role_in_category(string $username, string $role, string $category): void {
        self::write('has_role_in_category', [$username, $role, $category]);
    }

    /**
     * The "Hub dashboard" block is on the default Dashboard.
     *
     * @Given /^the "Hub dashboard" block is on the default Dashboard$/
     */
    public function block_on_dashboard(): void {
        self::write('block_on_dashboard', []);
    }

    /**
     * "<user>" picked the hub categories "<paths>".
     *
     * @Given /^"(?P<user>[^"]*)" picked the hub categories "(?P<paths>[^"]*)"$/
     * @param string $username
     * @param string $paths
     */
    public function picked_categories(string $username, string $paths): void {
        self::write('picked_categories', [$username, $paths]);
    }

    /**
     * "<user>" has the hub view "<name>" with module codes starting with "<first>" or "<second>".
     *
      * @phpcs:ignore moodle.Files.LineLength.TooLong, moodle.Files.LineLength.MaxExceeded
     * @Given /^"(?P<user>[^"]*)" has the hub view "(?P<name>[^"]*)" with module codes starting with "(?P<first>[^"]*)" or "(?P<second>[^"]*)"$/
     * @param string $username
     * @param string $name
     * @param string $first
     * @param string $second
     */
    public function has_code_view(string $username, string $name, string $first, string $second): void {
        self::write('has_code_view', [$username, $name, $first, $second]);
    }

    /**
     * "<user>" added the module "<code>" to the hub view "<name>".
     *
     * @Given /^"(?P<user>[^"]*)" added the module "(?P<code>[^"]*)" to the hub view "(?P<name>[^"]*)"$/
     * @param string $username
     * @param string $code
     * @param string $name
     */
    public function added_module(string $username, string $code, string $name): void {
        self::write('added_module', [$username, $code, $name]);
    }

    /**
     * "<user>" has the hub views "<first>" and "<second>", and last used "<last>".
     *
     * @Given /^"(?P<user>[^"]*)" has the hub views "(?P<first>[^"]*)" and "(?P<second>[^"]*)", and last used "(?P<last>[^"]*)"$/
     * @param string $username
     * @param string $first
     * @param string $second
     * @param string $last
     */
    public function has_views(string $username, string $first, string $second, string $last): void {
        self::write('has_views', [$username, $first, $second, $last]);
    }

    /**
     * "<user>" last chose "<type>" in the Hub dashboard.
     *
     * @Given /^"(?P<user>[^"]*)" last chose "(?P<type>[^"]*)" in the Hub dashboard$/
     * @param string $username
     * @param string $type
     */
    public function last_chose_type(string $username, string $type): void {
        self::write('last_chose_type', [$username, $type]);
    }

    /**
     * "<user>" added the hub note "<text>" to "<name>"<escalation>.
     *
     * @Given /^"(?P<user>[^"]*)" added the hub note "(?P<text>[^"]*)" to "(?P<name>[^"]*)"(?P<escalation> as an escalation)?$/
     * @param string $username
     * @param string $text
     * @param string $name
     * @param string $escalation
     */
    public function added_note(string $username, string $text, string $name, string $escalation = ''): void {
        self::write('added_note', [$username, $text, $name, $escalation]);
    }

    /**
     * "<user>" ignored the assessment "<name>" in the hub view "<view>".
     *
     * @Given /^"(?P<user>[^"]*)" ignored the assessment "(?P<name>[^"]*)" in the hub view "(?P<view>[^"]*)"$/
     * @param string $username
     * @param string $name
     * @param string $view
     */
    public function ignored_assessment(string $username, string $name, string $view): void {
        self::write('ignored_assessment', [$username, $name, $view]);
    }

    /**
     * An <tag> "<mod>" "<name>" in "<course>"<opts> is due "<due>" with marking due "<markingdue>".
     *
      * @phpcs:ignore moodle.Files.LineLength.TooLong, moodle.Files.LineLength.MaxExceeded
     * @Given /^an? (?P<tag>summative|formative) "(?P<mod>\w+)" "(?P<name>[^"]*)" in "(?P<course>[^"]*)"(?P<opts>.*?) (?:is|was) due "(?P<due>[^"]*)" with marking due "(?P<markingdue>[^"]*)"$/
     * @param string $tag
     * @param string $modname
     * @param string $name
     * @param string $course
     * @param string $opts
     * @param string $due
     * @param string $markingdue
     */
    public function assessment_with_marking_due(
        string $tag,
        string $modname,
        string $name,
        string $course,
        string $opts,
        string $due,
        string $markingdue
    ): void {
        self::write('assessment_with_marking_due', [$tag, $modname, $name, $course, $opts, $due, $markingdue]);
    }

    /**
     * <n> summative assessments in "<course>" were due "<due>" with marking due "<markingdue>".
     *
      * @phpcs:ignore moodle.Files.LineLength.TooLong, moodle.Files.LineLength.MaxExceeded
     * @Given /^(?P<n>\d+) summative assessments in "(?P<course>[^"]*)" were due "(?P<due>[^"]*)" with marking due "(?P<markingdue>[^"]*)"$/
     * @param string $count
     * @param string $course
     * @param string $due
     * @param string $markingdue
     */
    public function many_assessments(string $count, string $course, string $due, string $markingdue): void {
        self::write('many_assessments', [$count, $course, $due, $markingdue]);
    }

    /**
     * "<student>" has been marked on "<name>".
     *
     * @Given /^"(?P<student>[^"]*)" has been marked on "(?P<name>[^"]*)"$/
     * @param string $student
     * @param string $name
     */
    public function has_been_marked(string $student, string $name): void {
        self::write('has_been_marked', [$student, $name]);
    }

    /**
     * Both submissions of "<name>" have been marked<sent>.
     *
     * @Given /^both submissions of "(?P<name>[^"]*)" have been marked(?P<sent> and sent to hub)?$/
     * @param string $name
     * @param string $sent
     */
    public function both_marked(string $name, string $sent = ''): void {
        self::write('both_marked', [$name, $sent]);
    }

    /**
     * Both submissions of "<name>" have been released.
     *
     * @Given /^both submissions of "(?P<name>[^"]*)" have been released$/
     * @param string $name
     */
    public function both_released(string $name): void {
        self::write('both_released', [$name]);
    }

    /**
     * "<student>" has been marked and marks for "<name>" were sent to hub.
     *
     * @Given /^"(?P<student>[^"]*)" has been marked and marks for "(?P<name>[^"]*)" were sent to hub$/
     * @param string $student
     * @param string $name
     */
    public function marked_and_sent(string $student, string $name): void {
        self::write('marked_and_sent', [$student, $name]);
    }

    /**
     * Both marked, sent to hub, then the mark of "<student>" is changed.
     *
     * @Given /^both marked, sent to hub, then the mark of "(?P<student>[^"]*)" is changed$/
     * @param string $student
     */
    public function both_sent_then_changed(string $student): void {
        self::write('both_sent_then_changed', [$student]);
    }

    /**
     * Both released, then the mark of "<student>" is changed.
     *
     * @Given /^both released, then the mark of "(?P<student>[^"]*)" is changed$/
     * @param string $student
     */
    public function both_released_then_changed(string $student): void {
        self::write('both_released_then_changed', [$student]);
    }

    /**
     * "<student>" and "<second>" is allocated to "<marker>" on "<name>".
     *
      * @phpcs:ignore moodle.Files.LineLength.TooLong, moodle.Files.LineLength.MaxExceeded
     * @Given /^"(?P<student>[^"]*)"(?: and "(?P<second>[^"]*)")? (?:is|are) allocated to "(?P<marker>[^"]*)"(?: on "(?P<name>[^"]*)")?$/
     * @param string $student
     * @param string $second
     * @param string $marker
     * @param string $name
     */
    public function students_allocated(string $student, string $second, string $marker, string $name = ''): void {
        self::write('students_allocated', [$student, $second, $marker, $name]);
    }

    /**
     * Marks for "<name>" were sent to hub for "<student>" only.
     *
     * @Given /^marks for "(?P<name>[^"]*)" were sent to hub(?: for "(?P<student>[^"]*)" only)?$/
     * @param string $name
     * @param string $student
     */
    public function sent_to_hub(string $name, string $student = ''): void {
        self::write('sent_to_hub', [$name, $student]);
    }

    /**
     * The mark of "<student>" for "<name>" is changed.
     *
     * @Given /^the mark of "(?P<student>[^"]*)" for "(?P<name>[^"]*)" is changed$/
     * @param string $student
     * @param string $name
     */
    public function mark_changed(string $student, string $name): void {
        self::write('mark_changed', [$student, $name]);
    }

    /**
     * "<student>" is exempt from "<name>".
     *
     * @Given /^"(?P<student>[^"]*)" is exempt from "(?P<name>[^"]*)"$/
     * @param string $student
     * @param string $name
     */
    public function exempt(string $student, string $name): void {
        self::write('exempt', [$student, $name]);
    }

    /**
     * The results of "<student>" for "<name>" are withheld after an academic conduct check.
     *
     * @Given /^the results of "(?P<student>[^"]*)" for "(?P<name>[^"]*)" are withheld after an academic conduct check$/
     * @param string $student
     * @param string $name
     */
    public function withheld(string $student, string $name): void {
        self::write('withheld', [$student, $name]);
    }

    /**
     * The submission of "<student>" for "<name>" is flagged for an academic conduct check.
     *
     * @Given /^the submission of "(?P<student>[^"]*)" for "(?P<name>[^"]*)" is flagged for an academic conduct check$/
     * @param string $student
     * @param string $name
     */
    public function flagged_for_conduct(string $student, string $name): void {
        self::write('flagged_for_conduct', [$student, $name]);
    }

    // Reading the page.

    /**
     * The text content of every element a selector matches, as the page holds it (not as the browser shows it).
     *
     * @param string $selector A CSS selector.
     * @return string[]
     */
    protected function texts(string $selector): array {
        if (!$this->running_javascript()) {
            return array_map(fn($n) => $n->getText(), $this->find_all('css', $selector));
        }
        return $this->getSession()->evaluateScript("(function () { return Array.from(document.querySelectorAll("
            . json_encode($selector) . ")).map(function (el) { return el.textContent; }); })()");
    }

    /**
     * A hub row by name.
     *
     * @param string $name
     * @return \Behat\Mink\Element\NodeElement
     */
    protected function row(string $name): \Behat\Mink\Element\NodeElement {
        return $this->find('xpath', self::row_xpath($name), new ExpectationException("No row for '$name'", $this->getSession()));
    }

    /**
     * The XPath of a hub row.
     *
     * @param string $name
     * @return string
     */
    public static function row_xpath(string $name): string {
        return "//*[contains(@class, 'block_le_hub_dashboard')]//li[@data-region='hub-row']" .
            "[.//a[contains(@class, 'hub-row-name')][normalize-space(.) = " . behat_context_helper::escape($name) . ']]';
    }

    /**
     * I log in as "<user>" and pick the hub categories "<paths>".
     *
     * @Given /^I log in as "(?P<user>[^"]*)" and pick the hub categories "(?P<paths>[^"]*)"$/
     * @param string $username
     * @param string $paths
     */
    public function i_log_in_and_pick(string $username, string $paths): void {
        self::write('picked_categories', [$username, $paths]);
        $this->execute('behat_block_le_dashboard::i_log_in_and_view', [$username, 'Hub dashboard']);
    }

    /**
     * There should <not>be a hub row "<name>".
     *
     * @Then /^there should (?P<not>not )?be a hub row "(?P<name>[^"]*)"$/
     * @param string $not
     * @param string $name
     */
    public function there_should_be_a_hub_row(string $not, string $name): void {
        $step = $not ? 'should_not_exist' : 'should_exist';
        $this->execute('behat_general::' . $step, [self::row_xpath($name), 'xpath_element']);
    }

    /**
     * I should <not>see "<text>" in the "Hub dashboard" block.
     *
     * @Then /^I should (?P<not>not )?see "(?P<text>[^"]*)" in the "Hub dashboard" block$/
     * @param string $not
     * @param string $text
     */
    public function i_should_see_in_the_hub_block(string $not, string $text): void {
        $step = $not ? 'assert_element_not_contains_text' : 'assert_element_contains_text';
        $this->execute('behat_general::' . $step, [$text, self::BLOCK, 'css_element']);
    }

    /**
     * "<name>" should be in the "<group>" group with the reason "<reason>".
     *
     * @Then /^"(?P<name>[^"]*)" should be in the "(?P<group>[^"]*)" group(?: with the reason "(?P<reason>[^"]*)")?$/
     * @param string $name
     * @param string $group
     * @param string $reason
     */
    public function should_be_in_group(string $name, string $group, string $reason = ''): void {
        $xpath = "//*[contains(@class, 'block_le_hub_dashboard')]//section[@data-region='hub-group']" .
            "[.//*[contains(@class, 'hub-group-title')][normalize-space(.) = " . behat_context_helper::escape($group) . ']]' .
            "//li[@data-region='hub-row'][.//a[contains(@class, 'hub-row-name')][normalize-space(.) = " .
            behat_context_helper::escape($name) . ']]';
        $row = $this->getSession()->getPage()->find('xpath', $xpath);
        if (!$row) {
            throw new ExpectationException("'$name' is not in '$group'", $this->getSession());
        }
        if ($reason !== '') {
            $tag = $row->find('xpath', ".//*[contains(@class, 'hub-reason')][1]");
            if (!$tag || trim($tag->getText()) !== $reason) {
                throw new ExpectationException(
                    "'$name' leads with '" . ($tag ? $tag->getText() : '') . "', not '$reason'",
                    $this->getSession()
                );
            }
        }
    }

    /**
     * "<name>" should show the reason tag "<reason>".
     *
     * @Then /^"(?P<name>[^"]*)" should show the reason tag "(?P<reason>[^"]*)"$/
     * @param string $name
     * @param string $reason
     */
    public function should_show_reason_tag(string $name, string $reason): void {
        $this->collapsed_row_should_show_reason_tag($name, $reason);
    }

    /**
     * The collapsed row "<name>" should show the reason tag "<reason>".
     *
     * @Then /^the collapsed row "(?P<name>[^"]*)" should show the reason tag "(?P<reason>[^"]*)"$/
     * @param string $name
     * @param string $reason
     */
    public function collapsed_row_should_show_reason_tag(string $name, string $reason): void {
        foreach ($this->row($name)->findAll('xpath', ".//*[contains(@class, 'hub-reason')]") as $tag) {
            if (trim($tag->getText()) === $reason) {
                return;
            }
        }
        throw new ExpectationException("'$name' has no '$reason' tag", $this->getSession());
    }

    /**
     * The collapsed row "<name>" should show the status "<status>".
     *
     * @Then /^the collapsed row "(?P<name>[^"]*)" should show the status "(?P<status>[^"]*)"$/
     * @param string $name
     * @param string $status
     */
    public function collapsed_row_should_show_status(string $name, string $status): void {
        $node = $this->row($name)->find('xpath', ".//*[@data-region='hub-row-status']");
        if (!$node || trim(preg_replace('/\s+/', ' ', $node->getText())) !== $status) {
            throw new ExpectationException(
                "'$name' shows '" . ($node ? $node->getText() : '') . "', not '$status'",
                $this->getSession()
            );
        }
    }

    /**
     * The collapsed row "<name>" should <not>show <texts>.
     *
     * @Then /^the collapsed row "(?P<name>[^"]*)" should (?P<not>not )?show (?P<texts>"[^"]*"(?:, "[^"]*")*)$/
     * @param string $name
     * @param string $not
     * @param string $texts
     */
    public function collapsed_row_should_show(string $name, string $not, string $texts): void {
        $line = $this->row($name)->find('xpath', ".//*[@data-region='hub-row-line']");
        $shown = preg_replace('/\s+/', ' ', $line->getText());
        preg_match_all('/"([^"]*)"/', $texts, $m);
        foreach ($m[1] as $text) {
            if (str_contains($shown, $text) === (bool)$not) {
                throw new ExpectationException(
                    "The collapsed row '$name' " . ($not ? 'shows' : 'does not show') . " '$text'",
                    $this->getSession()
                );
            }
        }
    }

    /**
     * The collapsed row "<name>" should not show the text "<text>".
     *
     * @Then /^the collapsed row "(?P<name>[^"]*)" should not show the text "(?P<text>[^"]*)"$/
     * @param string $name
     * @param string $text
     */
    public function collapsed_row_should_not_show_text(string $name, string $text): void {
        $this->collapsed_row_should_show($name, 'not ', '"' . $text . '"');
    }

    /**
     * The flags' names of a row line, in order.
     *
     * @param string $name
     * @return string[]
     */
    protected function flag_names(string $name): array {
        $out = [];
        foreach (
            $this->row($name)->findAll(
                'xpath',
                ".//*[@data-region='hub-row-line']//button[@data-action='tip'][@data-hint]"
            ) as $flag
        ) {
            $out[] = $flag->getAttribute('aria-label');
        }
        return $out;
    }

    /**
     * The collapsed row "<name>" should show the icon flags "<flags>".
     *
     * @Then /^the collapsed row "(?P<name>[^"]*)" should show the icon flags "(?P<flags>[^"]*)"$/
     * @param string $name
     * @param string $flags
     */
    public function collapsed_row_should_show_icon_flags(string $name, string $flags): void {
        $expected = array_map('trim', explode(',', $flags));
        $actual = $this->flag_names($name);
        foreach ($expected as $flag) {
            if (!in_array($flag, $actual)) {
                throw new ExpectationException(
                    "'$name' has flags '" . implode(', ', $actual) . "', not '$flag'",
                    $this->getSession()
                );
            }
        }
    }

    /**
     * The collapsed row "<name>" should show an "<flag>" flag.
     *
     * @Then /^(?:the collapsed row )?"(?P<name>[^"]*)" should show an? "(?P<flag>[^"]*)" flag$/
     * @param string $name
     * @param string $flag
     */
    public function should_show_a_flag(string $name, string $flag): void {
        $this->collapsed_row_should_show_icon_flags($name, $flag);
    }

    /**
     * I click on the "<flag>" flag of "<name>".
     *
     * @When /^I click on the "(?P<flag>[^"]*)" flag of "(?P<name>[^"]*)"$/
     * @param string $flag
     * @param string $name
     */
    public function i_click_on_the_flag(string $flag, string $name): void {
        $button = $this->row($name)->find('xpath', ".//button[@data-action='tip'][@aria-label="
            . behat_context_helper::escape($flag) . ']');
        if (!$button) {
            throw new ExpectationException("No '$flag' flag on '$name'", $this->getSession());
        }
        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * The collapsed row "<name>" should show one stacked bar with segments "<segments>".
     *
     * @Then /^the collapsed row "(?P<name>[^"]*)" should show one stacked bar with segments "(?P<segments>[^"]*)"$/
     * @param string $name
     * @param string $segments
     */
    public function collapsed_row_should_show_stacked_bar(string $name, string $segments): void {
        $bars = $this->row($name)->findAll('xpath', ".//*[@data-region='hub-stack']");
        if (count($bars) !== 1) {
            throw new ExpectationException("'$name' has " . count($bars) . ' stacked bars', $this->getSession());
        }
        $label = $bars[0]->getAttribute('aria-label');
        if ($label !== $segments) {
            throw new ExpectationException("The stacked bar of '$name' reads '$label', not '$segments'", $this->getSession());
        }
    }

    /**
     * I <how> the row "<name>".
     *
     * @When /^I (?P<how>expand|collapse) the row "(?P<name>[^"]*)"$/
     * @param string $how
     * @param string $name
     */
    public function i_expand_the_row(string $how, string $name): void {
        $toggle = $this->row($name)->find('xpath', ".//button[@data-action='toggle-row']");
        $open = $toggle->getAttribute('aria-expanded') === 'true';
        if ($open !== ($how === 'expand')) {
            $toggle->click();
            $this->wait_for_pending_js();
        }
    }

    /**
     * I focus the row "<name>" and press "<key>".
     *
     * @When /^I focus the row "(?P<name>[^"]*)" and press "(?P<key>[^"]*)"$/
     * @param string $name
     * @param string $key
     */
    public function i_focus_the_row_and_press(string $name, string $key): void {
        $toggle = $this->row($name)->find('xpath', ".//button[@data-action='toggle-row']");
        $toggle->focus();
        $this->execute('behat_general::i_press_named_key', ['', $key === 'Enter' ? 'enter' : 'space']);
        $this->wait_for_pending_js();
    }

    /**
     * The expanded row "<name>" should show the set-up "<items>".
     *
     * @Then /^the expanded row "(?P<name>[^"]*)" should show the set-up "(?P<items>[^"]*)"$/
     * @param string $name
     * @param string $items
     */
    public function expanded_row_should_show_setup(string $name, string $items): void {
        $actual = array_map(fn($li) => trim($li->getText()), $this->row($name)->findAll('xpath', ".//li[@data-setup]"));
        if ($actual !== array_map('trim', explode(',', $items))) {
            throw new ExpectationException("'$name' set-up is '" . implode(', ', $actual) . "'", $this->getSession());
        }
    }

    /**
     * The expanded row "<name>" should show the dates "<labels>" in that order.
     *
     * @Then /^the expanded row "(?P<name>[^"]*)" should show the dates "(?P<labels>[^"]*)" in that order$/
     * @param string $name
     * @param string $labels
     */
    public function expanded_row_should_show_dates(string $name, string $labels): void {
        $detail = $this->row($name)->find('xpath', ".//*[@data-region='hub-row-detail']");
        if (!$detail || !$detail->isVisible()) {
            throw new ExpectationException("'$name' is not expanded", $this->getSession());
        }
        $actual = array_map(fn($n) => trim($n->getText()), $detail->findAll(
            'xpath',
            ".//*[contains(@class, 'ma-row-date-label')]"
        ));
        // In that order, with other dates (a release, an extension) allowed between.
        $at = 0;
        $expected = array_map('trim', explode(',', $labels));
        foreach ($actual as $item) {
            if (isset($expected[$at]) && $item === $expected[$at]) {
                $at++;
            }
        }
        if ($at !== count($expected)) {
            throw new ExpectationException("'$name' dates are '" . implode(', ', $actual) . "'", $this->getSession());
        }
    }

    /**
     * The expanded row "<name>" should show the bars "<labels>".
     *
     * @Then /^the expanded row "(?P<name>[^"]*)" should show the bars "(?P<labels>[^"]*)"$/
     * @param string $name
     * @param string $labels
     */
    public function expanded_row_should_show_bars(string $name, string $labels): void {
        $actual = array_map(
            fn($n) => trim($n->getText()),
            $this->row($name)->findAll('xpath', ".//*[contains(@class, 'hub-detail-bars')]//*[contains(@class, 'ma-bar-label')]")
        );
        if ($actual !== array_map('trim', explode(',', $labels))) {
            throw new ExpectationException("'$name' bars are '" . implode(', ', $actual) . "'", $this->getSession());
        }
    }

    /**
     * The expanded row "<name>" should list the convenor "<convenor>" linked to their profile.
     *
     * @Then /^the expanded row "(?P<name>[^"]*)" should list the convenor "(?P<convenor>[^"]*)" linked to their profile$/
     * @param string $name
     * @param string $convenor
     */
    public function expanded_row_should_list_convenor(string $name, string $convenor): void {
        $link = $this->row($name)->find('xpath', ".//li[@data-role='convenor']//a[normalize-space(.) = " .
            behat_context_helper::escape($convenor) . ']');
        if (!$link || !str_contains($link->getAttribute('href'), '/user/profile.php')) {
            throw new ExpectationException("'$name' does not link '$convenor' to a profile", $this->getSession());
        }
    }

    /**
     * The expanded row "<name>" should <not>show "<text>".
     *
     * @Then /^the expanded row "(?P<name>[^"]*)" should (?P<not>not )?(?:show|offer) "(?P<text>[^"]*)"$/
     * @param string $name
     * @param string $not
     * @param string $text
     */
    public function expanded_row_should_show(string $name, string $not, string $text): void {
        $detail = $this->row($name)->find('xpath', ".//*[@data-region='hub-row-detail']");
        $shown = preg_replace('/\s+/', ' ', $detail->getText());
        if (str_contains($shown, $text) === (bool)$not) {
            throw new ExpectationException(
                "The expanded row '$name' " . ($not ? 'shows' : 'does not show') . " '$text'",
                $this->getSession()
            );
        }
    }

    /**
     * The latest hub note on "<name>" should be "<text>" by "<author>" today.
     *
     * @Then /^the latest hub note on "(?P<name>[^"]*)" should be "(?P<text>[^"]*)" by "(?P<author>[^"]*)" today$/
     * @param string $name
     * @param string $text
     * @param string $author
     */
    public function latest_note_should_be(string $name, string $text, string $author): void {
        $note = $this->row($name)->find('xpath', "(.//li[@data-region='hub-note'])[1]");
        if (!$note) {
            throw new ExpectationException("'$name' has no notes", $this->getSession());
        }
        $shown = preg_replace('/\s+/', ' ', $note->getText());
        foreach ([$text, $author] as $part) {
            if (!str_contains($shown, $part)) {
                throw new ExpectationException("The latest note on '$name' reads '$shown', without '$part'", $this->getSession());
            }
        }
        $when = $note->find('css', 'time');
        if (!$when || !str_starts_with((string)$when->getAttribute('datetime'), date('Y-m-d'))) {
            throw new ExpectationException("The latest note on '$name' is not dated today", $this->getSession());
        }
    }

    /**
     * The groups should be in the order "<labels>".
     *
     * @Then /^the groups should be in the order "(?P<labels>[^"]*)"$/
     * @param string $labels
     */
    public function groups_in_order(string $labels): void {
        // Every group's title, in order, whether or not the group has rows to show (an empty group is hidden).
        $actual = array_map('trim', $this->texts(self::BLOCK . ' .hub-group-title'));
        if ($actual !== array_map('trim', explode(',', $labels))) {
            throw new ExpectationException("Groups are '" . implode(', ', $actual) . "'", $this->getSession());
        }
    }

    /**
     * The visible text of a group header: "Chase 3 · Marking late 2 · Marking at risk 1".
     *
     * @param string $group
     * @return string
     */
    protected function group_header_text(string $group): string {
        $header = $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//section[@data-region='hub-group']" .
            "[.//*[contains(@class, 'hub-group-title')][normalize-space(.) = " . behat_context_helper::escape($group) . "]]" .
            "//*[@data-region='hub-group-header']");
        $parts = [trim($header->find('xpath', ".//*[contains(@class, 'hub-group-title')]")->getText()) . ' ' .
            trim($header->find('xpath', ".//*[@data-region='hub-group-count']")->getText())];
        foreach ($header->findAll('xpath', ".//button[@data-action='reason']") as $button) {
            if ($button->isVisible()) {
                $parts[] = trim(preg_replace('/\s+/', ' ', $button->getText()));
            }
        }
        return implode(' · ', $parts);
    }

    /**
     * The "<group>" group header should read "<text>".
     *
     * @Then /^the "(?P<group>[^"]*)" group header should read "(?P<text>[^"]*)"$/
     * @param string $group
     * @param string $text
     */
    public function group_header_should_read(string $group, string $text): void {
        $actual = $this->group_header_text($group);
        if ($actual !== $text) {
            throw new ExpectationException("The '$group' header reads '$actual'", $this->getSession());
        }
    }

    /**
     * The status strip should read "<text>".
     *
     * @Then /^the status strip should read "(?P<text>[^"]*)"$/
     * @param string $text
     */
    public function strip_should_read(string $text): void {
        $parts = [];
        foreach ($this->find_all('css', self::BLOCK . ' [data-action="jump"]') as $pill) {
            $count = trim($pill->find('css', '[data-region="hub-strip-count"]')->getText());
            $label = trim(str_replace($count, '', preg_replace('/\s+/', ' ', $pill->getText())));
            $parts[] = "$label $count";
        }
        $actual = implode(', ', $parts);
        if ($actual !== $text) {
            throw new ExpectationException("The status strip reads '$actual'", $this->getSession());
        }
    }

    /**
     * I click on the reason "<reason>" in the "<group>" group header.
     *
     * @When /^I click on the reason "(?P<reason>[^"]*)" in the "(?P<group>[^"]*)" group header$/
     * @param string $reason
     * @param string $group
     */
    public function i_click_on_the_reason(string $reason, string $group): void {
        $button = $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//section[@data-region='hub-group']" .
            "[.//*[contains(@class, 'hub-group-title')][normalize-space(.) = " . behat_context_helper::escape($group) . "]]" .
            "//button[@data-action='reason'][starts-with(normalize-space(.), " . behat_context_helper::escape($reason) . ')]');
        $button->click();
        $this->wait_for_pending_js();
    }

    /**
     * The "<group>" group should list "<names>" in that order.
     *
     * @Then /^the "(?P<group>[^"]*)" group should list "(?P<names>[^"]*)" in that order$/
     * @param string $group
     * @param string $names
     */
    public function group_should_list(string $group, string $names): void {
        $rows = $this->find_all('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//section[@data-region='hub-group']" .
            "[.//*[contains(@class, 'hub-group-title')][normalize-space(.) = " . behat_context_helper::escape($group) . "]]" .
            "//li[@data-region='hub-row']//a[contains(@class, 'hub-row-name')]");
        $actual = array_map(fn($n) => trim($n->getText()), array_filter($rows, fn($n) => $n->isVisible()));
        if (array_values($actual) !== array_map('trim', explode(',', $names))) {
            throw new ExpectationException("The '$group' group lists '" . implode(', ', $actual) . "'", $this->getSession());
        }
    }

    /**
     * The "<group>" group should be collapsed.
     *
     * @Then /^the "(?P<group>[^"]*)" group should be collapsed$/
     * @param string $group
     */
    public function group_should_be_collapsed(string $group): void {
        $toggle = $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//section[@data-region='hub-group']" .
            "[.//*[contains(@class, 'hub-group-title')][normalize-space(.) = " . behat_context_helper::escape($group) . "]]" .
            "//button[@data-action='toggle-group']");
        if ($toggle->getAttribute('aria-expanded') !== 'false') {
            throw new ExpectationException("The '$group' group is open", $this->getSession());
        }
    }

    /**
     * I click on the status pill "<group>".
     *
     * @When /^I click on the status pill "(?P<group>[^"]*)"$/
     * @param string $group
     */
    public function i_click_on_the_status_pill(string $group): void {
        $pill = $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//button[@data-action='jump']" .
            "[contains(normalize-space(.), " . behat_context_helper::escape($group) . ')]');
        $pill->click();
        $this->wait_for_pending_js();
    }

    /**
     * The "<group>" group should be open and in view.
     *
     * @Then /^the "(?P<group>[^"]*)" group should be open and in view$/
     * @param string $group
     */
    public function group_should_be_open_and_in_view(string $group): void {
        $section = $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//section[@data-region='hub-group']" .
            "[.//*[contains(@class, 'hub-group-title')][normalize-space(.) = " . behat_context_helper::escape($group) . ']]');
        $toggle = $section->find('xpath', ".//button[@data-action='toggle-group']");
        if ($toggle->getAttribute('aria-expanded') !== 'true') {
            throw new ExpectationException("The '$group' group is collapsed", $this->getSession());
        }
        $visible = $this->getSession()->evaluateScript("(function () {
            var el = document.evaluate(" . json_encode($section->getXpath()) . ", document, null, 9, null).singleNodeValue;
            var r = el.getBoundingClientRect(); return r.top >= -2 && r.top < window.innerHeight; })()");
        if (!$visible) {
            throw new ExpectationException("The '$group' group is not in view", $this->getSession());
        }
    }

    /**
     * I type "<text>" in the hub "Find" box.
     *
     * @When /^I type "(?P<text>[^"]*)" in the hub "Find" box$/
     * @param string $text
     */
    public function i_type_in_find(string $text): void {
        $box = $this->find('css', self::BLOCK . ' [data-action="find"]');
        $box->focus();
        $box->setValue($text);
        // Typing fires input events in a browser; this makes sure the Find box has seen the whole text.
        $this->getSession()->executeScript("document.querySelector('" . self::BLOCK
            . " [data-action=\"find\"]').dispatchEvent(new Event('input', {bubbles: true}))");
        $this->wait_for_pending_js();
        // The Find box waits a moment after typing before asking the server.
        $this->getSession()->wait(1000, "document.querySelector('" . self::BLOCK
            . " [data-region=\"hub-suggest\"]:not([hidden])')");
    }

    /**
     * The Find suggestions should list "<name>" under "<heading>".
     *
     * @Then /^the Find suggestions should list "(?P<name>[^"]*)" under "(?P<heading>[^"]*)"$/
     * @param string $name
     * @param string $heading
     */
    public function suggestions_should_list(string $name, string $heading): void {
        $list = $this->find('css', self::BLOCK . ' [data-region="hub-suggest"]');
        $items = $list->findAll('xpath', "./*[contains(@class, 'hub-suggest-heading')][normalize-space(.) = " .
            behat_context_helper::escape($heading) . "]/following-sibling::button[contains(@class, 'hub-suggest-item')]");
        foreach ($items as $item) {
            if (str_starts_with(trim($item->getText()), $name)) {
                return;
            }
        }
        throw new ExpectationException("The Find list has no '$name' under '$heading'", $this->getSession());
    }

    /**
     * The Find suggestions should offer "<only>" and "<not>".
     *
     * @Then /^the Find suggestions should offer "(?P<only>[^"]*)" and "(?P<not>[^"]*)"$/
     * @param string $only
     * @param string $not
     */
    public function suggestions_should_offer(string $only, string $not): void {
        foreach ([$only, $not] as $label) {
            $this->find(
                'xpath',
                "//*[contains(@class, 'block_le_hub_dashboard')]//*[@data-region='hub-suggest']" .
                "//button[@data-action='suggest-word'][starts-with(normalize-space(.), "
                    . behat_context_helper::escape($label) . ')]',
                new ExpectationException("The Find list does not offer '$label'", $this->getSession())
            );
        }
    }

    /**
     * I choose "<label>" from the Find suggestions.
     *
     * @When /^I choose "(?P<label>[^"]*)" from the Find suggestions$/
     * @param string $label
     */
    public function i_choose_from_suggestions(string $label): void {
        $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//*[@data-region='hub-suggest']" .
            "//button[starts-with(normalize-space(.), " . behat_context_helper::escape($label) . ')]')->click();
        $this->wait_for_pending_js();
    }

    /**
     * I search the hub dashboard for the person "<name>".
     *
     * @When /^I search the hub dashboard for the person "(?P<name>[^"]*)"$/
     * @param string $name
     */
    public function i_search_for_the_person(string $name): void {
        $this->i_type_in_find(explode(' ', $name)[0]);
        $this->i_choose_from_suggestions($name);
    }

    /**
     * I add the word filter "<word>" as "<mode>".
     *
     * @When /^I add the word filter "(?P<word>[^"]*)" as "(?P<mode>Only|Not)"$/
     * @param string $word
     * @param string $mode
     */
    public function i_add_the_word_filter(string $word, string $mode): void {
        $this->i_type_in_find($word);
        $this->i_choose_from_suggestions("$mode $word");
    }

    /**
     * I filter the hub dashboard to exclude "<first>" and "<second>".
     *
     * @When /^I filter the hub dashboard to exclude "(?P<first>[^"]*)" and "(?P<second>[^"]*)"$/
     * @param string $first
     * @param string $second
     */
    public function i_exclude_words(string $first, string $second): void {
        $this->i_add_the_word_filter($first, 'Not');
        $this->i_add_the_word_filter($second, 'Not');
    }

    /**
     * I switch the word chip "<word>" to "<mode>".
     *
     * @When /^I switch the word (?:chip|filter) "(?P<word>[^"]*)" to "?(?P<mode>Only|Not|only|not)"?$/
     * @param string $word
     * @param string $mode
     */
    public function i_switch_the_word_chip(string $word, string $mode): void {
        $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//*[contains(@class, 'hub-chip')][@data-chip=" .
            behat_context_helper::escape(strtolower($word)) . "]//button[@data-action='chip-mode'][@data-mode='"
                . strtolower($mode) . "']")->click();
        $this->wait_for_pending_js();
    }

    /**
     * I should see the word chip "<text>" in the "Hub dashboard" block.
     *
     * @Then /^I should see the word chip "(?P<text>[^"]*)" in the "Hub dashboard" block$/
     * @param string $text
     */
    public function i_should_see_the_word_chip(string $text): void {
        [$mode, $word] = explode(' ', $text, 2);
        $this->word_chip_should_show_mode($word, $mode);
    }

    /**
     * The word chip "<word>" should show the mode "<mode>".
     *
     * @Then /^the word chip "(?P<word>[^"]*)" should show the mode "(?P<mode>Only|Not)"$/
     * @param string $word
     * @param string $mode
     */
    public function word_chip_should_show_mode(string $word, string $mode): void {
        $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//*[contains(@class, 'hub-chip')][@data-chip=" .
            behat_context_helper::escape(strtolower($word))
                . "]//button[@data-action='chip-mode'][@aria-pressed='true'][normalize-space(.) = " .
            behat_context_helper::escape($mode) . ']', new ExpectationException(
                "No '$word' chip in mode '$mode'",
                $this->getSession()
            ));
    }

    /**
     * The Filter list should have the groups "<groups>".
     *
     * @Then /^the Filter list should have the groups "(?P<groups>[^"]*)"$/
     * @param string $groups
     */
    public function filter_list_groups(string $groups): void {
        // The headings are set in capitals by CSS, which the browser reports; the words are what matter.
        $actual = array_map(
            fn($n) => \core_text::strtolower(trim($n->getText())),
            $this->find_all('css', self::BLOCK . ' .hub-filter-heading')
        );
        if ($actual !== array_map(fn($g) => \core_text::strtolower(trim($g)), explode(',', $groups))) {
            throw new ExpectationException("The Filter list has the groups '" . implode(', ', $actual) . "'", $this->getSession());
        }
    }

    /**
     * I tick "<label>" in the Filter list.
     *
     * @When /^I tick "(?P<label>[^"]*)" in the Filter list$/
     * @param string $label
     */
    public function i_tick_in_the_filter_list(string $label): void {
        $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//*[@data-region='hub-filter-menu']" .
            "//input[@data-label=" . behat_context_helper::escape($label) . ']')->click();
        $this->wait_for_pending_js();
    }

    /**
     * I filter the hub dashboard to only those with "<label>".
     *
     * @When /^I filter the hub dashboard to only those with "(?P<label>[^"]*)"$/
     * @param string $label
     */
    public function i_filter_to_only_those_with(string $label): void {
        $this->execute('behat_general::i_click_on_in_the', ['Filter', 'button', self::BLOCK, 'css_element']);
        $this->i_tick_in_the_filter_list($label);
    }

    /**
     * "<label>" should be ticked under "<group>" in the Filter list.
     *
     * @Then /^"(?P<label>[^"]*)" should be ticked under "(?P<group>[^"]*)" in the Filter list$/
     * @param string $label
     * @param string $group
     */
    public function should_be_ticked_in_the_filter_list(string $label, string $group): void {
        $box = $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//*[contains(@class, 'hub-filter-group')]" .
            "[.//*[contains(@class, 'hub-filter-heading')][normalize-space(.) = " . behat_context_helper::escape($group) . "]]" .
            "//input[@data-label=" . behat_context_helper::escape($label) . ']');
        if (!$box->isChecked()) {
            throw new ExpectationException("'$label' is not ticked", $this->getSession());
        }
    }

    /**
     * I should see the filter chip "<label>".
     *
     * @Then /^I should see the filter chip "(?P<label>[^"]*)"$/
     * @param string $label
     */
    public function i_should_see_the_filter_chip(string $label): void {
        $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//*[contains(@class, 'hub-chip')][@data-chip=" .
            behat_context_helper::escape($label) . ']', new ExpectationException("No '$label' chip", $this->getSession()));
    }

    /**
     * I remove the filter chip "<label>".
     *
     * @When /^I remove the filter chip "(?P<label>[^"]*)"$/
     * @param string $label
     */
    public function i_remove_the_filter_chip(string $label): void {
        $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//*[contains(@class, 'hub-chip')][@data-chip=" .
            behat_context_helper::escape($label) . "]//button[@data-action='chip-remove']")->click();
        $this->wait_for_pending_js();
    }

    /**
     * The "Hub dashboard" controls should be "<names>".
     *
     * @Then /^the "Hub dashboard" controls should be "(?P<names>[^"]*)"$/
     * @param string $names
     */
    public function controls_should_be(string $names): void {
        $map = ['hub-viewpick' => 'View', 'hub-year' => 'Year', 'ma-type-filter' => 'Type', 'hub-find' => 'Find',
            'hub-filter' => 'Filter', 'hub-freshness' => 'Refresh'];
        $actual = [];
        foreach ($this->find_all('css', self::BLOCK . ' [data-region="hub-controls"] > .hub-control') as $control) {
            foreach ($map as $class => $label) {
                if ($control->hasClass($class)) {
                    $actual[] = $label;
                }
            }
        }
        $expected = array_values(array_filter(array_map('trim', explode(',', $names)), fn($n) => $n !== 'Export'));
        if ($actual !== $expected) {
            throw new ExpectationException("The controls are '" . implode(', ', $actual) . "'", $this->getSession());
        }
    }

    /**
     * Lets the page use the whole window: Boost caps the Dashboard's content at 830px (body.limitedwidth), which
     * is narrower than the one-line row needs (P5), so this shows the layout a wider page gets.
     *
     * @Given /^the page is shown at its full width$/
     */
    public function page_at_full_width(): void {
        if ($this->running_javascript()) {
            $this->getSession()->executeScript("document.body.classList.remove('limitedwidth')");
            $this->wait_for_pending_js();
        }
    }

    /**
     * The column header should not be shown.
     *
     * @Then /^the column header should not be shown$/
     */
    public function column_header_not_shown(): void {
        $head = $this->find('css', self::BLOCK . ' .hub-colhead');
        if ($this->running_javascript() && $head->isVisible()) {
            throw new ExpectationException('The column header is shown', $this->getSession());
        }
    }

    /**
     * The row columns should be "<names>".
     *
     * @Then /^the row columns should be "(?P<names>[^"]*)"$/
     * @param string $names
     */
    public function row_columns_should_be(string $names): void {
        $actual = array_values(array_filter(array_map('trim', $this->texts(self::BLOCK . ' .hub-colhead > .hub-col'))));
        if ($actual !== array_map('trim', explode(',', $names))) {
            throw new ExpectationException("The columns are '" . implode(', ', $actual) . "'", $this->getSession());
        }
    }

    /**
     * The "Module" column of every collapsed row should start at the same x position.
     *
     * @Then /^the "Module" column of every collapsed row should start at the same x position$/
     */
    public function module_columns_aligned(): void {
        $lefts = $this->getSession()->evaluateScript("(function () {
            return Array.from(document.querySelectorAll('" . self::BLOCK
                . " li[data-region=\"hub-row\"]:not([hidden]) .hub-col--module'))
                .map(function (el) { return Math.round(el.getBoundingClientRect().left); }); })()");
        if (count(array_unique($lefts)) > 1) {
            throw new ExpectationException(
                'The Module column starts at ' . implode(', ', array_unique($lefts)),
                $this->getSession()
            );
        }
    }

    /**
     * The collapsed row "<name>" should link "<text>" to the <where> page.
     *
     * @Then /^the collapsed row "(?P<name>[^"]*)" should link "(?P<text>[^"]*)" to the (?P<where>activity|course) page$/
     * @param string $name
     * @param string $text
     * @param string $where
     */
    public function collapsed_row_should_link(string $name, string $text, string $where): void {
        $link = $this->row($name)->find('xpath', ".//*[@data-region='hub-row-line']//a[normalize-space(.) = " .
            behat_context_helper::escape($text) . ']');
        $needle = $where === 'activity' ? '/mod/' : '/course/view.php';
        if (!$link || !str_contains($link->getAttribute('href'), $needle)) {
            throw new ExpectationException("'$text' does not link to the $where page", $this->getSession());
        }
    }

    /**
     * The first assessment row should be within "<px>" pixels of the top of the block at "<width>" pixels wide.
     *
      * @phpcs:ignore moodle.Files.LineLength.TooLong, moodle.Files.LineLength.MaxExceeded
     * @Then /^the first assessment row should be within "(?P<px>\d+)" pixels of the top of the block(?: at "(?P<width>\d+)" pixels wide)?$/
     * @param string $px
     * @param string $width
     */
    public function first_row_within(string $px, string $width = ''): void {
        if ($width !== '') {
            $this->resize_window($width . 'x900', true);
            $this->wait_for_pending_js();
        }
        $distance = $this->getSession()->evaluateScript("(function () {
            var block = document.querySelector('" . self::BLOCK . "');
            var row = block.querySelector('li[data-region=\"hub-row\"]:not([hidden])');
            return row.getBoundingClientRect().top - block.getBoundingClientRect().top; })()");
        if ($distance > (int)$px) {
            throw new ExpectationException("The first row is $distance pixels down", $this->getSession());
        }
    }

    /**
     * The first assessment row should still be visible.
     *
     * @Then /^the first assessment row should still be visible$/
     */
    public function first_row_visible(): void {
        $visible = $this->getSession()->evaluateScript("(function () {
            var row = document.querySelector('" . self::BLOCK . " li[data-region=\"hub-row\"]:not([hidden])');
            var r = row.getBoundingClientRect();
            var panel = document.querySelector('" . self::BLOCK . " [data-region=\"hub-editor\"]').getBoundingClientRect();
            return r.left < panel.left && r.top >= 0 && r.bottom <= window.innerHeight; })()");
        if (!$visible) {
            throw new ExpectationException('The first row is hidden behind the editor', $this->getSession());
        }
    }

    /**
     * The collapsed row "<name>" should be at most "<px>" pixels tall.
     *
     * @Then /^the collapsed row "(?P<name>[^"]*)" should be at most "(?P<px>\d+)" pixels tall$/
     * @param string $name
     * @param string $px
     */
    public function collapsed_row_height(string $name, string $px): void {
        $line = $this->row($name)->find('xpath', ".//*[@data-region='hub-row-line']");
        $height = $this->getSession()->evaluateScript("(function () {
            var el = document.evaluate(" . json_encode($line->getXpath()) . ", document, null, 9, null).singleNodeValue;
            return el.getBoundingClientRect().height; })()");
        if ($height > (int)$px) {
            throw new ExpectationException("The row '$name' is $height pixels tall", $this->getSession());
        }
    }

    /**
     * The status strip should fit one line.
     *
     * @Then /^the status strip should fit one line$/
     */
    public function strip_fits_one_line(): void {
        $tops = $this->getSession()->evaluateScript("(function () {
            return Array.from(document.querySelectorAll('" . self::BLOCK . " [data-action=\"jump\"]'))
                .map(function (el) { return Math.round(el.getBoundingClientRect().top); }); })()");
        if (count(array_unique($tops)) > 1) {
            throw new ExpectationException('The status strip wraps', $this->getSession());
        }
    }

    /**
     * I scroll to the <n>st row of the "<group>" group.
     *
     * @When /^I scroll to the (?P<n>\d+)(?:st|nd|rd|th) row of the "(?P<group>[^"]*)" group$/
     * @param string $n
     * @param string $group
     */
    public function i_scroll_to_the_row(string $n, string $group): void {
        $this->getSession()->executeScript("(function () {
            var rows = Array.from(document.querySelectorAll('" . self::BLOCK . " section[data-group]')).find(function (s) {
                return s.querySelector('.hub-group-title').textContent.trim() === " . json_encode($group) . ";
            }).querySelectorAll('li[data-region=\"hub-row\"]');
            rows[" . ((int)$n - 1) . "].scrollIntoView({block: 'center'}); })()");
        $this->wait_for_pending_js();
    }

    /**
     * The "<group>" group header should be stuck to the top of the viewport.
     *
     * @Then /^the "(?P<group>[^"]*)" group header should be stuck to the top of the viewport$/
     * @param string $group
     */
    public function group_header_stuck(string $group): void {
        $top = $this->getSession()->evaluateScript("(function () {
            var s = Array.from(document.querySelectorAll('" . self::BLOCK . " section[data-group]')).find(function (s) {
                return s.querySelector('.hub-group-title').textContent.trim() === " . json_encode($group) . ";
            });
            var header = s.querySelector('[data-region=\"hub-group-header\"]');
            return {top: header.getBoundingClientRect().top, section: s.getBoundingClientRect().top}; })()");
        if ($top['top'] < 0 || $top['top'] > 80 || $top['section'] >= $top['top']) {
            throw new ExpectationException("The '$group' header is at " . $top['top'] . ' pixels', $this->getSession());
        }
    }

    /**
     * The view editor should open as a side panel.
     *
     * @Then /^the view editor should open as a side panel$/
     */
    public function editor_is_a_side_panel(): void {
        $panel = $this->find('css', self::BLOCK . ' [data-region="hub-editor"]');
        if (!$panel->isVisible()) {
            throw new ExpectationException('The view editor is not open', $this->getSession());
        }
    }

    /**
     * I set the view's allocation window to "<days>" days.
     *
     * @When /^I set the view's allocation window to "(?P<days>\d+)" days$/
     * @param string $days
     */
    public function i_set_the_allocation_window(string $days): void {
        $input = $this->find('css', self::BLOCK . ' [data-action="allocdays"]');
        $input->setValue($days);
        $this->getSession()->executeScript("document.querySelector('" . self::BLOCK . " [data-action=\"allocdays\"]')" .
            ".dispatchEvent(new Event('change', {bubbles: true}))");
        $this->wait_for_pending_js();
    }

    /**
     * I add the module code line "<kind>" "<text>" to the view editor.
     *
     * @When /^I add the module code line "(?P<kind>starts with|contains)" "(?P<text>[^"]*)" to the view editor$/
     * @param string $kind
     * @param string $text
     */
    public function i_add_the_code_line(string $kind, string $text): void {
        $this->find('css', self::BLOCK . ' [data-action="add-line"]')->click();
        $this->wait_for_pending_js();
        $lines = $this->find_all('css', self::BLOCK . ' [data-region="hub-code-line"]');
        $line = end($lines);
        $line->find('css', '[data-kind="codekind"]')->selectOption($kind === 'starts with' ? 'starts' : 'contains');
        $value = $line->find('css', '[data-kind="codevalue"]');
        $value->setValue($text);
        $this->getSession()->executeScript("document.activeElement.blur()");
        $this->getSession()->executeScript("(function () { var lines = document.querySelectorAll('" . self::BLOCK .
            " [data-region=\"hub-code-line\"] [data-kind=\"codevalue\"]'); lines[lines.length - 1].dispatchEvent(new " .
                "Event('input', {bubbles: true})); })()");
        $this->getSession()->wait(1500);
        $this->wait_for_pending_js();
    }

    /**
     * I create a hub view "<name>" with module codes starting with "<first>" or "<second>".
     *
      * @phpcs:ignore moodle.Files.LineLength.TooLong, moodle.Files.LineLength.MaxExceeded
     * @When /^I create a hub view "(?P<name>[^"]*)" with (?:module codes starting with "(?P<first>[^"]*)" or "(?P<second>[^"]*)"|the departments "(?P<cats>[^"]*)")$/
     * @param string $name
     * @param string $first
     * @param string $second
     * @param string $cats
     */
    public function i_create_a_hub_view(string $name, string $first = '', string $second = '', string $cats = ''): void {
        $this->find('css', self::BLOCK . ' [data-action="new-view"]')->click();
        $this->wait_for_pending_js();
        $nameinput = $this->find('css', self::BLOCK . ' [data-action="view-name"]');
        $nameinput->setValue($name);
        $this->getSession()->executeScript("(function () { var el = document.querySelector('" . self::BLOCK .
            " [data-action=\"view-name\"]'); el.dispatchEvent(new Event('change', {bubbles: true})); })()");
        $this->wait_for_pending_js();
        foreach (array_filter([$first, $second]) as $code) {
            $this->i_add_the_code_line('starts with', $code);
        }
        if ($cats !== '') {
            foreach (explode(',', $cats) as $cat) {
                $this->find('xpath', "//*[contains(@class, 'block_le_hub_dashboard')]//label[contains(@class, 'hub-tree-item')]" .
                    "[normalize-space(.) = " . behat_context_helper::escape(trim($cat)) . ']')->click();
                $this->wait_for_pending_js();
            }
        }
        $this->find('css', self::BLOCK . ' [data-action="close-editor"]')->click();
        $this->wait_for_pending_js();
    }

    /**
     * Types a view name into the editor's name box and lets go of it.
     *
     * @param string $name
     */
    protected function set_view_name(string $name): void {
        $this->find('css', self::BLOCK . ' [data-action="view-name"]')->setValue($name);
        $this->getSession()->executeScript("(function () { var el = document.querySelector('" . self::BLOCK .
            " [data-action=\"view-name\"]'); el.dispatchEvent(new Event('change', {bubbles: true})); })()");
        $this->wait_for_pending_js();
    }

    /**
     * I copy the hub view "<name>" as "<copy>".
     *
     * @When /^I copy the hub view "(?P<name>[^"]*)" as "(?P<copy>[^"]*)"$/
     * @param string $name
     * @param string $copy
     */
    public function i_copy_the_hub_view(string $name, string $copy): void {
        $this->execute('behat_forms::i_set_the_field_to', ['View', $name]);
        $this->wait_for_pending_js();
        $this->execute('behat_general::i_click_on_in_the', ['This view', 'button', self::BLOCK, 'css_element']);
        $this->execute('behat_general::i_click_on_in_the', ['Copy', 'link', self::BLOCK, 'css_element']);
        $this->wait_for_pending_js();
        $this->set_view_name($copy);
        $this->find('css', self::BLOCK . ' [data-action="close-editor"]')->click();
        $this->wait_for_pending_js();
    }

    /**
     * I rename the hub view "<name>" to "<newname>".
     *
     * @When /^I rename the hub view "(?P<name>[^"]*)" to "(?P<newname>[^"]*)"$/
     * @param string $name
     * @param string $newname
     */
    public function i_rename_the_hub_view(string $name, string $newname): void {
        $this->execute('behat_forms::i_set_the_field_to', ['View', $name]);
        $this->wait_for_pending_js();
        $this->execute('behat_general::i_click_on_in_the', ['This view', 'button', self::BLOCK, 'css_element']);
        $this->execute('behat_general::i_click_on_in_the', ['Rename', 'link', self::BLOCK, 'css_element']);
        $this->wait_for_pending_js();
        $this->set_view_name($newname);
        $this->find('css', self::BLOCK . ' [data-action="close-editor"]')->click();
        $this->wait_for_pending_js();
    }

    /**
     * I delete the hub view "<name>".
     *
     * @When /^I delete the hub view "(?P<name>[^"]*)"$/
     * @param string $name
     */
    public function i_delete_the_hub_view(string $name): void {
        $this->execute('behat_forms::i_set_the_field_to', ['View', $name]);
        $this->wait_for_pending_js();
        $this->execute('behat_general::i_click_on_in_the', ['This view', 'button', self::BLOCK, 'css_element']);
        $this->execute('behat_general::i_click_on_in_the', ['Delete view', 'link', self::BLOCK, 'css_element']);
        $this->execute('behat_general::i_click_on_in_the', ['Click again to confirm', 'link', self::BLOCK, 'css_element']);
        $this->wait_for_pending_js();
    }

    /**
     * The view editor should say "<text>".
     *
     * @Then /^the view editor should say "(?P<text>[^"]*)"$/
     * @param string $text
     */
    public function editor_should_say(string $text): void {
        $count = $this->find('css', self::BLOCK . ' [data-region="hub-editor-count"]');
        if (trim($count->getText()) !== $text) {
            throw new ExpectationException("The editor says '" . $count->getText() . "'", $this->getSession());
        }
    }

    /**
     * I should see "<text>" in the view editor under "<heading>".
     *
     * @Then /^I should see "(?P<text>[^"]*)" in the view editor under "(?P<heading>[^"]*)"$/
     * @param string $text
     * @param string $heading
     */
    public function i_should_see_in_the_editor_under(string $text, string $heading): void {
        $section = $this->find(
            'xpath',
            "//*[contains(@class, 'block_le_hub_dashboard')]//*[contains(@class, 'hub-panel-section')]" .
            "[.//*[contains(@class, 'hub-panel-label')][normalize-space(.) = " . behat_context_helper::escape($heading) . ']]'
        );
        if (!str_contains($section->getText(), $text)) {
            throw new ExpectationException("'$text' is not under '$heading' in the editor", $this->getSession());
        }
    }

    /**
     * I click on "<text>" "link" in the view editor.
     *
     * @When /^I click on "(?P<text>[^"]*)" "link" in the view editor$/
     * @param string $text
     */
    public function i_click_in_the_editor(string $text): void {
        $this->find(
            'xpath',
            "//*[contains(@class, 'block_le_hub_dashboard')]//*[@data-region='hub-editor']//a[normalize-space(.) = " .
            behat_context_helper::escape($text) . ']'
        )->click();
        $this->wait_for_pending_js();
    }

    /**
     * The hub view "<name>" should be open.
     *
     * @Then /^the hub view "(?P<name>[^"]*)" should be open$/
     * @param string $name
     */
    public function hub_view_should_be_open(string $name): void {
        $select = $this->find('css', self::BLOCK . ' [data-action="view"]');
        $option = $select->find('xpath', "./option[@selected]");
        if (!$option || trim($option->getText()) !== $name) {
            throw new ExpectationException("The open view is '" . ($option ? $option->getText() : '') . "'", $this->getSession());
        }
    }

    /**
     * I put "<code>" back in the hub view.
     *
     * @When /^I put "(?P<code>[^"]*)" back in the hub view$/
     * @param string $code
     */
    public function i_put_back(string $code): void {
        $this->execute('behat_general::i_click_on_in_the', ['This view', 'button', self::BLOCK, 'css_element']);
        $this->execute('behat_general::i_click_on_in_the', ['Edit what this view shows', 'link', self::BLOCK, 'css_element']);
        $this->find(
            'xpath',
            "//*[contains(@class, 'block_le_hub_dashboard')]//*[contains(@class, 'hub-exceptions')]//li[contains(., " .
            behat_context_helper::escape($code) . ")]//a[@data-action='putback']"
        )->click();
        $this->wait_for_pending_js();
        $this->find('css', self::BLOCK . ' [data-action="close-editor"]')->click();
    }

    /**
     * I add the module "<code>" to the hub view.
     *
     * @When /^I add the module "(?P<code>[^"]*)" to the hub view$/
     * @param string $code
     */
    public function i_add_the_module(string $code): void {
        $this->execute('behat_general::i_click_on_in_the', ['This view', 'button', self::BLOCK, 'css_element']);
        $this->execute('behat_general::i_click_on_in_the', ['Edit what this view shows', 'link', self::BLOCK, 'css_element']);
        $this->find('css', self::BLOCK . ' [data-region="hub-addmodule"]')->setValue($code);
        $this->find('css', self::BLOCK . ' [data-action="add-module"]')->click();
        $this->wait_for_pending_js();
        $this->find('css', self::BLOCK . ' [data-action="close-editor"]')->click();
    }

    /**
     * I add the hub note "<text>" to "<name>"<escalation>.
     *
     * @When /^I add the hub note "(?P<text>[^"]*)" to "(?P<name>[^"]*)"(?P<escalation> as an escalation)?$/
     * @param string $text
     * @param string $name
     * @param string $escalation
     */
    public function i_add_the_hub_note(string $text, string $name, string $escalation = ''): void {
        $this->i_expand_the_row('expand', $name);
        $row = $this->row($name);
        $row->find('css', '[data-action="add-note"]')->click();
        $row->find('css', '[data-region="hub-note-text"]')->setValue($text);
        if ($escalation !== '') {
            $row->find('css', '[data-region="hub-note-escalated"]')->click();
        }
        $row->find('css', '[data-action="save-note"]')->click();
        $this->wait_for_pending_js();
    }
}
