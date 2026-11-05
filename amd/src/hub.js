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
 * Hub dashboard behaviour.
 *
 * The server renders every row of the open view; this module hides and counts (type, words, filters, reasons),
 * opens rows and groups, runs Find, the Ignore menu, notes and the view editor, and asks the services for a new
 * view when the data changes. Every hook is a data- attribute, never a styling class. Hint popovers and local
 * dates are My assessments' modules.
 *
 * @module     block_le_hub_dashboard/hub
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString, getStrings} from 'core/str';
import {setUserPreference} from 'core_user/repository';
import Pending from 'core/pending';
import {localise} from 'block_le_dashboard/localtime';
import {Tips} from 'block_le_dashboard/tips';

const COMPONENT = 'block_le_hub_dashboard';

const SELECTORS = {
    view: '[data-region="hub-view"]',
    viewRoot: '[data-region="hub-view-root"]',
    loading: '[data-region="hub-loading"]',
    status: '[data-region="hub-status"]',
    list: '[data-region="hub-list"]',
    group: '[data-region="hub-group"]',
    row: '[data-region="hub-row"]',
    rowLine: '[data-region="hub-row-line"]',
    rowDetail: '[data-region="hub-row-detail"]',
    chips: '[data-region="hub-chips"]',
    suggest: '[data-region="hub-suggest"]',
    find: '[data-action="find"]',
    filterCheck: '.hub-filter-check',
    filterMenu: '[data-region="hub-filter-menu"]',
    filterLabel: '[data-region="hub-filter-label"]',
    filterEmpty: '[data-region="hub-filter-empty"]',
    editor: '[data-region="hub-editor"]',
    scrim: '[data-region="hub-scrim"]',
    editorError: '[data-region="hub-editor-error"]',
    toast: '[data-region="hub-toast"]',
    toastText: '[data-region="hub-toast-text"]',
    ignore: '[data-region="hub-ignore"]',
    viewMenu: '[data-region="hub-viewmenu"]',
};

/** How long Find waits after a keystroke before asking the server. */
const FIND_DELAY = 250;
/** How long the editor waits after typing in a code rule before saving. */
const SAVE_DELAY = 500;
/** How long the toast stays. */
const TOAST_TIME = 8000;

/**
 * Interactive things inside a row line; clicking them never toggles the row.
 *
 * @param {Element} target
 * @returns {boolean}
 */
const interactive = target => Boolean(target.closest('a, button, input, select, textarea, label, [data-action]'));

/**
 * One hub block's state, kept while the view is re-rendered.
 */
class Hub {
    /**
     * @param {HTMLElement} root
     * @param {Object} state viewid, yearid, type, personid
     */
    constructor(root, state) {
        this.root = root;
        this.state = state;
        this.type = state.type || 'summative';
        // Word filters, each {text, mode: 'only'|'not'} (P2); never saved (P8).
        this.words = [];
        // Filter keys: "r:<reason>" or "f:<flag>" (P3).
        this.filters = new Set();
        this.expanded = new Set();
        this.collapsed = new Set(['done']);
        this.collapsedTouched = false;
        this.seq = 0;
        this.refreshing = false;
        this.panelOpen = false;
        this.panelFocus = null;
        this.undo = null;
        this.toastTimer = null;
        this.findTimer = null;
        this.saveTimer = null;
        // What a test runner waits for while a timer runs (core/pending), by timer.
        this.waiting = {};
        this.suggestIndex = -1;
        root.addEventListener('click', e => this.click(e));
        // Opening an Ignore menu or the view menu again puts an unconfirmed item back. Captured, because
        // Bootstrap stops the toggle's click once its dropdown has opened.
        root.addEventListener('click', e => {
            const toggle = e.target.closest && e.target.closest('[data-action="ignore-menu"], [data-action="viewmenu"]');
            if (toggle) {
                this.disarm(toggle.closest('.dropdown'));
            }
        }, true);
        // Ticking filters keeps the Filter list open (Bootstrap 4 closes a menu on any click inside it).
        root.addEventListener('click', e => {
            if (e.target.closest && e.target.closest(SELECTORS.filterMenu)) {
                e.stopPropagation();
            }
        });
        root.addEventListener('input', e => this.input(e));
        root.addEventListener('change', e => this.change(e));
        root.addEventListener('keydown', e => this.keydown(e));
        root.addEventListener('submit', e => this.submit(e));
        root.addEventListener('focusout', e => this.focusout(e));
        this.tips = new Tips(root);
        this.apply();
        this.renderChips().catch(Notification.exception);
        localise(root).catch(Notification.exception);
    }

    /**
     * The open view's root.
     *
     * @returns {HTMLElement|null}
     */
    get viewRoot() {
        return this.root.querySelector(SELECTORS.viewRoot);
    }

    /**
     * Puts armed Ignore and Delete view items back to their labels.
     *
     * @param {HTMLElement|null} where
     */
    disarm(where) {
        (where || this.root).querySelectorAll('[data-armed]').forEach(item => {
            const label = item.querySelector('[data-region="hub-ignore-label"], [data-region="hub-delete-label"]');
            if (label && item.dataset.label) {
                label.textContent = item.dataset.label;
            }
            delete item.dataset.armed;
        });
    }

    /**
     * The first click on a destructive item asks for a second, in the same place; the second returns true.
     *
     * @param {HTMLElement} item
     * @param {string} labelRegion
     * @returns {Promise<boolean>} Whether this was the confirming click.
     */
    async arm(item, labelRegion) {
        if (item.dataset.armed) {
            return true;
        }
        this.disarm();
        item.dataset.armed = '1';
        const text = await getString('ignore_confirm', COMPONENT);
        const label = item.querySelector(`[data-region="${labelRegion}"]`);
        if (label) {
            label.textContent = text;
        }
        this.say(text);
        return false;
    }

    /**
     * Click handler.
     *
     * @param {Event} e
     */
    click(e) {
        const target = e.target.closest('[data-action]');
        if (!target || !this.root.contains(target)) {
            // A click on the row line itself (not on a link, flag or button) opens the row.
            const line = e.target.closest(SELECTORS.rowLine);
            if (line && !interactive(e.target)) {
                this.toggleRow(line.closest(SELECTORS.row));
            }
            return;
        }
        const action = target.dataset.action;
        const handlers = {
            'tip': () => this.tips.click(target),
            'filter-type': () => {
                this.type = target.dataset.type;
                setUserPreference(`${COMPONENT}_type`, this.type).catch(Notification.exception);
                this.apply();
            },
            'toggle-group': () => this.toggleGroup(target.closest(SELECTORS.group)),
            'jump': () => this.jump(target.dataset.group),
            'reason': () => this.toggleFilter(`r:${target.dataset.reason}`),
            'toggle-row': () => this.toggleRow(target.closest(SELECTORS.row)),
            'refresh': () => this.load(`${COMPONENT}_refresh`, {}),
            'clear-person': () => this.load(`${COMPONENT}_get_view`, {personid: 0}),
            'chip-mode': () => this.setWordMode(parseInt(target.dataset.index, 10), target.dataset.mode),
            'chip-remove': () => this.removeChip(target.dataset.filter, target.dataset.index),
            'suggest-word': () => this.addWord(target.dataset.word, target.dataset.mode),
            'suggest-person': () => this.pickPerson(parseInt(target.dataset.person, 10)),
            'add-note': () => this.showNoteForm(target.closest(SELECTORS.row), true),
            'cancel-note': () => this.showNoteForm(target.closest(SELECTORS.row), false),
            'ignore': () => {
                e.preventDefault();
                // The first click arms the item in place: the menu must stay open for the second.
                e.stopPropagation();
                this.ignore(target);
            },
            'undo': () => this.undoIgnore(),
            'edit-view': () => {
                e.preventDefault();
                this.openEditor();
            },
            'rename-view': () => {
                e.preventDefault();
                this.openEditor('[data-action="view-name"]');
            },
            'copy-view': () => {
                e.preventDefault();
                this.copyView();
            },
            'delete-view': () => {
                e.preventDefault();
                e.stopPropagation();
                this.deleteView(target);
            },
            'new-view': () => this.newView(),
            'close-editor': () => this.closeEditor(),
            'add-line': () => this.addLine(),
            'remove-line': () => {
                target.closest('[data-region="hub-code-line"]').remove();
                this.saveShape();
            },
            'add-module': () => this.addModule(),
            'remove-added': () => {
                e.preventDefault();
                this.removeAdded(target.dataset.value);
            },
            'putback': () => {
                e.preventDefault();
                this.load(`${COMPONENT}_ignore`, {
                    kind: target.dataset.kind,
                    value: parseInt(target.dataset.value, 10),
                    undo: true,
                });
            },
        };
        if (handlers[action]) {
            handlers[action]();
        }
        if (target.closest(SELECTORS.scrim)) {
            this.closeEditor();
        }
    }

    /**
     * Input handler: Find typing, editor code rules.
     *
     * @param {Event} e
     */
    input(e) {
        const action = e.target.dataset.action;
        if (action === 'find') {
            clearTimeout(this.findTimer);
            this.settle('find');
            const text = e.target.value.trim();
            if (text.length < 2) {
                this.closeSuggest();
                return;
            }
            this.wait('find');
            this.findTimer = setTimeout(() => this.suggest(text).finally(() => this.settle('find')), FIND_DELAY);
        } else if (action === 'shape' && e.target.dataset.kind === 'codevalue') {
            clearTimeout(this.saveTimer);
            this.settle('save');
            this.wait('save');
            this.saveTimer = setTimeout(() => {
                this.settle('save');
                this.saveShape();
            }, SAVE_DELAY);
        }
    }

    /**
     * Marks a timer as work in progress, so a test runner waits for it (core/pending).
     *
     * @param {string} key
     */
    wait(key) {
        this.settle(key);
        this.waiting[key] = new Pending(`${COMPONENT}/${key}`);
    }

    /**
     * Marks a timer's work as done.
     *
     * @param {string} key
     */
    settle(key) {
        if (this.waiting[key]) {
            this.waiting[key].resolve();
            delete this.waiting[key];
        }
    }

    /**
     * Change handler: selects, checkboxes, the allocation window.
     *
     * @param {Event} e
     */
    change(e) {
        const target = e.target;
        const action = target.dataset.action;
        if (action === 'year') {
            this.load(`${COMPONENT}_get_view`, {yearid: parseInt(target.value, 10)});
        } else if (action === 'view') {
            this.load(`${COMPONENT}_get_view`, {viewid: parseInt(target.value, 10), personid: 0});
        } else if (target.matches(SELECTORS.filterCheck)) {
            this.toggleFilter(target.dataset.filter, target.checked);
        } else if (action === 'shape' && target.dataset.kind !== 'codevalue') {
            clearTimeout(this.saveTimer);
            this.settle('save');
            this.saveShape();
        } else if (action === 'allocdays') {
            const days = Math.max(0, Math.min(365, parseInt(target.value, 10) || 0));
            this.load(`${COMPONENT}_save_view`, {action: 'allocdays', viewid: this.state.viewid, allocdays: days},
                '[data-action="allocdays"]');
        } else if (action === 'view-name') {
            this.renameView(target.value.trim());
        }
    }

    /**
     * Keyboard: the Find list, Escape closing the editor, Enter in the editor's inputs.
     *
     * @param {KeyboardEvent} e
     */
    keydown(e) {
        const target = e.target;
        if (target.matches(SELECTORS.find)) {
            const items = this.suggestItems();
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                if (!items.length) {
                    return;
                }
                e.preventDefault();
                this.suggestIndex = e.key === 'ArrowDown'
                    ? Math.min(items.length - 1, this.suggestIndex + 1) : Math.max(0, this.suggestIndex - 1);
                this.markSuggest(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const item = items[this.suggestIndex] || items[0];
                if (item) {
                    item.click();
                } else if (target.value.trim()) {
                    this.addWord(target.value.trim(), 'only');
                }
            } else if (e.key === 'Escape') {
                this.closeSuggest();
            }
        } else if (e.key === 'Escape' && this.panelOpen && target.closest(SELECTORS.editor)) {
            this.closeEditor();
        } else if (e.key === 'Enter' && target.matches('[data-region="hub-addmodule"]')) {
            e.preventDefault();
            this.addModule();
        } else if (e.key === 'Enter' && target.matches('[data-action="view-name"], [data-kind="codevalue"]')) {
            e.preventDefault();
            target.blur();
        }
    }

    /**
     * Closes the Find list once focus has left the box and the list.
     *
     * @param {FocusEvent} e
     */
    focusout(e) {
        const find = e.target.closest && e.target.closest('[data-region="hub-find"]');
        if (find && !(e.relatedTarget && find.contains(e.relatedTarget))) {
            this.closeSuggest();
        }
    }

    /**
     * Saving a note.
     *
     * @param {Event} e
     */
    submit(e) {
        const form = e.target.closest('[data-region="hub-noteform"]');
        if (!form) {
            return;
        }
        e.preventDefault();
        const row = form.closest(SELECTORS.row);
        const text = form.querySelector('[data-region="hub-note-text"]').value.trim();
        if (!text) {
            return;
        }
        const escalated = form.querySelector('[data-region="hub-note-escalated"]').checked;
        this.load(`${COMPONENT}_add_note`, {cmid: parseInt(row.dataset.cmid, 10), note: text, escalated},
            `#${row.id} [data-action="add-note"]`);
    }

    /**
     * Shows or hides a row's note form.
     *
     * @param {HTMLElement} row
     * @param {boolean} show
     */
    showNoteForm(row, show) {
        const form = row.querySelector('[data-region="hub-noteform"]');
        const button = row.querySelector('[data-action="add-note"]');
        form.hidden = !show;
        button.hidden = show;
        if (show) {
            form.querySelector('textarea').focus();
        } else {
            button.focus();
        }
    }

    /**
     * Opens or closes a row's detail.
     *
     * @param {HTMLElement} row
     * @param {boolean|null} open
     */
    toggleRow(row, open = null) {
        const cmid = row.dataset.cmid;
        const show = open === null ? !this.expanded.has(cmid) : open;
        if (show) {
            this.expanded.add(cmid);
        } else {
            this.expanded.delete(cmid);
        }
        this.applyRow(row);
    }

    /**
     * Draws a row's open or closed state.
     *
     * @param {HTMLElement} row
     */
    applyRow(row) {
        const open = this.expanded.has(row.dataset.cmid);
        row.classList.toggle('hub-row--open', open);
        const detail = row.querySelector(SELECTORS.rowDetail);
        const toggle = row.querySelector('[data-action="toggle-row"]');
        if (detail) {
            detail.hidden = !open;
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            const name = row.querySelector('.hub-row-name');
            const label = toggle.querySelector('[data-region="hub-toggle-label"]');
            if (label && name) {
                getString(open ? 'collapse' : 'expand', COMPONENT, name.textContent.trim()).then(text => {
                    label.textContent = text;
                    return text;
                }).catch(Notification.exception);
            }
        }
    }

    /**
     * Opens or closes a group.
     *
     * @param {HTMLElement} group
     * @param {boolean|null} open
     */
    toggleGroup(group, open = null) {
        const key = group.dataset.group;
        const show = open === null ? this.collapsed.has(key) : open;
        this.collapsedTouched = true;
        if (show) {
            this.collapsed.delete(key);
        } else {
            this.collapsed.add(key);
        }
        this.apply();
    }

    /**
     * A status strip pill: opens its group and brings it into view.
     *
     * @param {string} key
     */
    jump(key) {
        const group = this.root.querySelector(`${SELECTORS.group}[data-group="${key}"]`);
        if (!group) {
            return;
        }
        this.toggleGroup(group, true);
        group.scrollIntoView({block: 'start', behavior: 'smooth'});
        const toggle = group.querySelector('[data-action="toggle-group"]');
        if (toggle) {
            toggle.focus({preventScroll: true});
        }
    }

    // --- Find, words and filters -------------------------------------------------------------------------

    /**
     * Asks the server who matches, and draws the list: the word as Not or Only, then staff and students.
     *
     * @param {string} text
     */
    async suggest(text) {
        try {
            const [result] = Ajax.call([{methodname: `${COMPONENT}_find`, args: {text}}]);
            const people = await result;
            const box = this.root.querySelector(SELECTORS.find);
            if (!box || box.value.trim() !== text) {
                return;
            }
            const [words, only, not, onlyhint, nothint, staff, students, nobody] = await getStrings([
                {key: 'suggest_words', component: COMPONENT},
                {key: 'only_word', component: COMPONENT, param: text},
                {key: 'not_word', component: COMPONENT, param: text},
                {key: 'suggest_only', component: COMPONENT},
                {key: 'suggest_not', component: COMPONENT},
                {key: 'suggest_staff', component: COMPONENT},
                {key: 'suggest_students', component: COMPONENT},
                {key: 'nobody', component: COMPONENT},
            ]);
            const list = this.root.querySelector(SELECTORS.suggest);
            list.textContent = '';
            const heading = label => {
                const div = document.createElement('div');
                div.className = 'hub-suggest-heading';
                div.textContent = label;
                list.appendChild(div);
            };
            const item = (label, kind, data) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'hub-suggest-item';
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');
                Object.assign(button.dataset, data);
                const main = document.createElement('span');
                main.textContent = label;
                const side = document.createElement('span');
                side.className = 'hub-suggest-kind';
                side.textContent = kind;
                button.append(main, side);
                list.appendChild(button);
            };
            heading(words);
            item(not, nothint, {action: 'suggest-word', word: text, mode: 'not'});
            item(only, onlyhint, {action: 'suggest-word', word: text, mode: 'only'});
            if (people.staff.length) {
                heading(staff);
                people.staff.forEach(p => item(p.name, staff, {action: 'suggest-person', person: p.id}));
            }
            if (people.students.length) {
                heading(students);
                people.students.forEach(p => item(p.name, students, {action: 'suggest-person', person: p.id}));
            }
            if (!people.staff.length && !people.students.length) {
                const empty = document.createElement('div');
                empty.className = 'hub-suggest-empty';
                empty.textContent = nobody.replace('{$a}', text);
                list.appendChild(empty);
            }
            list.hidden = false;
            box.setAttribute('aria-expanded', 'true');
            this.suggestIndex = -1;
        } catch (error) {
            Notification.exception(error);
        }
    }

    /**
     * The Find list's options.
     *
     * @returns {HTMLElement[]}
     */
    suggestItems() {
        const list = this.root.querySelector(SELECTORS.suggest);
        return list && !list.hidden ? Array.from(list.querySelectorAll('[role="option"]')) : [];
    }

    /**
     * Marks the keyboard-selected option.
     *
     * @param {HTMLElement[]} items
     */
    markSuggest(items) {
        items.forEach((item, i) => item.setAttribute('aria-selected', i === this.suggestIndex ? 'true' : 'false'));
    }

    /**
     * Closes the Find list.
     */
    closeSuggest() {
        const list = this.root.querySelector(SELECTORS.suggest);
        const box = this.root.querySelector(SELECTORS.find);
        if (list) {
            list.hidden = true;
            list.textContent = '';
        }
        if (box) {
            box.setAttribute('aria-expanded', 'false');
        }
        this.suggestIndex = -1;
    }

    /**
     * Adds a word chip (P2).
     *
     * @param {string} text
     * @param {string} mode only or not
     */
    addWord(text, mode) {
        const lower = text.toLowerCase();
        const existing = this.words.find(w => w.text === lower);
        if (existing) {
            existing.mode = mode;
        } else {
            this.words.push({text: lower, mode});
        }
        const box = this.root.querySelector(SELECTORS.find);
        if (box) {
            box.value = '';
            box.focus();
        }
        this.closeSuggest();
        this.renderChips().catch(Notification.exception);
        this.apply();
    }

    /**
     * Switches a chip between Only and Not in place.
     *
     * @param {number} index
     * @param {string} mode
     */
    setWordMode(index, mode) {
        if (this.words[index]) {
            this.words[index].mode = mode;
            this.renderChips().catch(Notification.exception);
            this.apply();
        }
    }

    /**
     * Removes a word or filter chip.
     *
     * @param {string|undefined} filter The filter key, for a filter chip.
     * @param {string|undefined} index The word's index, for a word chip.
     */
    removeChip(filter, index) {
        if (filter) {
            this.toggleFilter(filter, false);
        } else {
            this.words.splice(parseInt(index, 10), 1);
            this.renderChips().catch(Notification.exception);
            this.apply();
        }
    }

    /**
     * A person from the Find list: the list becomes theirs across the institution (P2).
     *
     * @param {number} personid
     */
    pickPerson(personid) {
        this.closeSuggest();
        this.load(`${COMPONENT}_get_view`, {personid}, '[data-action="clear-person"]');
    }

    /**
     * Turns a filter on or off (P3), from the Filter list, a chip or a reason in a group header.
     *
     * @param {string} key "r:<reason>" or "f:<flag>"
     * @param {boolean|null} on
     */
    toggleFilter(key, on = null) {
        const want = on === null ? !this.filters.has(key) : on;
        if (want) {
            this.filters.add(key);
        } else {
            this.filters.delete(key);
        }
        this.renderChips().catch(Notification.exception);
        this.apply();
    }

    /**
     * Draws the chips under the control bar: words with their Only/Not toggle, then the filters.
     */
    async renderChips() {
        const chips = this.root.querySelector(SELECTORS.chips);
        if (!chips) {
            return;
        }
        const labels = {};
        this.root.querySelectorAll(SELECTORS.filterCheck).forEach(check => {
            labels[check.dataset.filter] = check.dataset.label;
        });
        const [only, not] = await getStrings([{key: 'only', component: COMPONENT}, {key: 'not', component: COMPONENT}]);
        const removes = await getStrings([...this.words.map(w => ({key: 'chip_remove', component: COMPONENT, param: w.text})),
            ...Array.from(this.filters).map(f => ({key: 'chip_remove', component: COMPONENT, param: labels[f] || f}))]);
        chips.textContent = '';
        const chip = (text, remove, extra) => {
            const span = document.createElement('span');
            span.className = 'hub-chip';
            span.dataset.chip = text;
            const label = document.createElement('span');
            label.textContent = text;
            if (extra) {
                span.appendChild(extra);
            }
            span.appendChild(label);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'hub-chip-remove';
            button.setAttribute('aria-label', remove);
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-xmark';
            icon.setAttribute('aria-hidden', 'true');
            button.appendChild(icon);
            span.appendChild(button);
            chips.appendChild(span);
            return button;
        };
        this.words.forEach((word, i) => {
            const modes = document.createElement('span');
            modes.className = 'hub-chip-modes';
            modes.setAttribute('role', 'group');
            [['only', only], ['not', not]].forEach(([mode, label]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'hub-chip-mode';
                button.textContent = label;
                button.dataset.action = 'chip-mode';
                button.dataset.index = i;
                button.dataset.mode = mode;
                button.setAttribute('aria-pressed', word.mode === mode ? 'true' : 'false');
                modes.appendChild(button);
            });
            const remove = chip(word.text, removes[i], modes);
            remove.dataset.action = 'chip-remove';
            remove.dataset.index = i;
        });
        Array.from(this.filters).forEach((key, i) => {
            const remove = chip(labels[key] || key, removes[this.words.length + i]);
            remove.dataset.action = 'chip-remove';
            remove.dataset.filter = key;
        });
    }

    // --- Showing what matches --------------------------------------------------------------------------

    /**
     * Whether a row passes the type, the words and the flag filters (not the reason filters).
     *
     * @param {HTMLElement} row
     * @returns {boolean}
     */
    matchesBase(row) {
        if (this.type !== 'both' && row.dataset.assesstype !== this.type) {
            return false;
        }
        const search = row.dataset.search || '';
        for (const word of this.words) {
            const has = search.includes(word.text);
            if (word.mode === 'only' ? !has : has) {
                return false;
            }
        }
        const flags = (row.dataset.flags || '').split(' ');
        for (const key of this.filters) {
            if (key.startsWith('f:') && !flags.includes(key.slice(2))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether a row carries one of the reasons picked, or none is picked.
     *
     * @param {HTMLElement} row
     * @returns {boolean}
     */
    matchesReason(row) {
        const picked = Array.from(this.filters).filter(k => k.startsWith('r:')).map(k => k.slice(2));
        if (!picked.length) {
            return true;
        }
        const reasons = (row.dataset.reasons || '').split(' ');
        return picked.some(r => reasons.includes(r));
    }

    /**
     * Shows the rows that match, the counts, the open rows and groups, and the controls' states.
     */
    apply() {
        const view = this.viewRoot;
        if (!view) {
            return;
        }
        view.querySelectorAll('[data-action="filter-type"]').forEach(button => {
            const active = button.dataset.type === this.type;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        view.querySelectorAll(SELECTORS.filterCheck).forEach(check => {
            check.checked = this.filters.has(check.dataset.filter);
        });
        const label = view.querySelector(SELECTORS.filterLabel);
        if (label) {
            const n = this.filters.size;
            (n ? getString('filter_n', COMPONENT, n) : getString('filter', COMPONENT)).then(text => {
                label.textContent = text;
                return text;
            }).catch(Notification.exception);
        }
        const filtering = this.type !== 'both' || this.words.length > 0 || this.filters.size > 0;
        let anyMatch = false;
        view.querySelectorAll(SELECTORS.group).forEach(group => {
            const key = group.dataset.group;
            const collapsed = this.collapsed.has(key);
            const reasons = {};
            let matches = 0;
            group.querySelectorAll(SELECTORS.row).forEach(row => {
                const base = this.matchesBase(row);
                if (base) {
                    (row.dataset.reasons || '').split(' ').filter(Boolean).forEach(r => {
                        reasons[r] = (reasons[r] || 0) + 1;
                    });
                }
                const match = base && this.matchesReason(row);
                matches += match ? 1 : 0;
                row.hidden = !match;
                this.applyRow(row);
            });
            anyMatch = anyMatch || matches > 0;
            group.hidden = matches === 0;
            group.classList.toggle('hub-group--collapsed', collapsed);
            const toggle = group.querySelector('[data-action="toggle-group"]');
            if (toggle) {
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            }
            const count = group.querySelector('[data-region="hub-group-count"]');
            if (count) {
                count.textContent = matches;
            }
            group.querySelectorAll('[data-action="reason"]').forEach(button => {
                const n = reasons[button.dataset.reason] || 0;
                button.hidden = n === 0;
                button.querySelector('[data-region="hub-reason-count"]').textContent = n;
                button.setAttribute('aria-pressed', this.filters.has(`r:${button.dataset.reason}`) ? 'true' : 'false');
            });
            const pill = view.querySelector(`[data-action="jump"][data-group="${key}"] [data-region="hub-strip-count"]`);
            if (pill) {
                pill.textContent = matches;
            }
        });
        const empty = view.querySelector(SELECTORS.filterEmpty);
        if (empty) {
            empty.hidden = !filtering || anyMatch;
        }
    }

    // --- Ignore and undo -------------------------------------------------------------------------------

    /**
     * An Ignore item (P6): the first click arms it, the second takes the module or assessment out of the view.
     *
     * @param {HTMLElement} item
     */
    async ignore(item) {
        if (!(await this.arm(item, 'hub-ignore-label'))) {
            return;
        }
        const args = {kind: item.dataset.kind, value: parseInt(item.dataset.value, 10), undo: false};
        this.undo = {args: {...args, undo: true}, text: item.dataset.done};
        await this.load(`${COMPONENT}_ignore`, args);
        this.toast(item.dataset.done, true);
    }

    /**
     * Puts back what the last Ignore took out.
     */
    undoIgnore() {
        if (!this.undo) {
            return;
        }
        const {args} = this.undo;
        this.undo = null;
        this.hideToast();
        this.load(`${COMPONENT}_ignore`, args);
    }

    /**
     * Shows the toast.
     *
     * @param {string} text
     * @param {boolean} undo Whether Undo is offered.
     */
    toast(text, undo) {
        const toast = this.root.querySelector(SELECTORS.toast);
        toast.querySelector(SELECTORS.toastText).textContent = text;
        toast.querySelector('[data-action="undo"]').hidden = !undo;
        toast.hidden = false;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => this.hideToast(), TOAST_TIME);
    }

    /**
     * Hides the toast.
     */
    hideToast() {
        clearTimeout(this.toastTimer);
        const toast = this.root.querySelector(SELECTORS.toast);
        if (toast) {
            toast.hidden = true;
        }
    }

    // --- Views and the editor --------------------------------------------------------------------------

    /**
     * Makes a view and opens its editor on the name.
     */
    async newView() {
        await this.load(`${COMPONENT}_save_view`, {action: 'create', viewid: 0, name: ''});
        this.openEditor('[data-action="view-name"]', true);
    }

    /**
     * Copies the open view.
     */
    async copyView() {
        const select = this.root.querySelector('[data-action="view"]');
        const current = select ? select.options[select.selectedIndex].textContent : '';
        const name = await getString('copyname', COMPONENT, current);
        await this.load(`${COMPONENT}_save_view`, {action: 'copy', viewid: this.state.viewid, name});
        this.openEditor('[data-action="view-name"]', true);
    }

    /**
     * Renames the open view.
     *
     * @param {string} name
     */
    renameView(name) {
        if (!name) {
            return;
        }
        this.load(`${COMPONENT}_save_view`, {action: 'rename', viewid: this.state.viewid, name}, '[data-action="view-name"]');
    }

    /**
     * Deletes the open view after a second click.
     *
     * @param {HTMLElement} item
     */
    async deleteView(item) {
        if (!(await this.arm(item, 'hub-delete-label'))) {
            return;
        }
        this.panelOpen = false;
        this.load(`${COMPONENT}_save_view`, {action: 'delete', viewid: this.state.viewid});
    }

    /**
     * Opens the editor panel (P7).
     *
     * @param {string|null} focus A selector inside the panel to focus.
     * @param {boolean} select Whether to select that control's text.
     */
    openEditor(focus = null, select = false) {
        const panel = this.root.querySelector(SELECTORS.editor);
        const scrim = this.root.querySelector(SELECTORS.scrim);
        if (!panel) {
            return;
        }
        this.panelOpen = true;
        panel.hidden = false;
        if (scrim) {
            scrim.hidden = false;
        }
        const target = focus ? panel.querySelector(focus) : null;
        (target || panel).focus();
        if (target && select && target.select) {
            target.select();
        }
    }

    /**
     * Closes the editor panel.
     */
    closeEditor() {
        const panel = this.root.querySelector(SELECTORS.editor);
        const scrim = this.root.querySelector(SELECTORS.scrim);
        this.panelOpen = false;
        if (panel) {
            panel.hidden = true;
        }
        if (scrim) {
            scrim.hidden = true;
        }
        const menu = this.root.querySelector('[data-action="viewmenu"]');
        if (menu) {
            menu.focus();
        }
    }

    /**
     * Adds an empty code rule line and focuses it.
     */
    addLine() {
        const template = this.root.querySelector('[data-region="hub-code-template"]');
        const lines = this.root.querySelector('[data-region="hub-editor-lines"]');
        if (!template || !lines) {
            return;
        }
        lines.appendChild(template.content.cloneNode(true));
        lines.lastElementChild.querySelector('[data-kind="codevalue"]').focus();
    }

    /**
     * The editor's rules as the service takes them: departments, code rules and modules added by id.
     *
     * @returns {Object}
     */
    rules() {
        const panel = this.root.querySelector(SELECTORS.editor);
        const rules = {cat: [], starts: [], contains: [], course: []};
        panel.querySelectorAll('[data-kind="cat"]:checked').forEach(check => rules.cat.push(check.value));
        panel.querySelectorAll('[data-region="hub-code-line"]').forEach(line => {
            const kind = line.querySelector('[data-kind="codekind"]');
            const value = line.querySelector('[data-kind="codevalue"]');
            if (kind && value && value.value.trim()) {
                rules[kind.value].push(value.value.trim());
            }
        });
        panel.querySelectorAll('[data-action="remove-added"]').forEach(link => rules.course.push(link.dataset.value));
        return rules;
    }

    /**
     * Saves the editor's rules and redraws the list behind it.
     *
     * @param {Object|null} rules
     */
    saveShape(rules = null) {
        const focused = document.activeElement;
        let focus = null;
        if (focused && focused.closest(SELECTORS.editor)) {
            if (focused.dataset.kind === 'cat') {
                focus = `[data-kind="cat"][value="${focused.value}"]`;
            } else if (focused.dataset.kind === 'codevalue' || focused.dataset.kind === 'codekind') {
                const lines = Array.from(this.root.querySelectorAll('[data-region="hub-code-line"]'));
                const i = lines.indexOf(focused.closest('[data-region="hub-code-line"]'));
                focus = `[data-region="hub-code-line"]:nth-of-type(${i + 1}) [data-kind="${focused.dataset.kind}"]`;
            } else if (focused.dataset.action) {
                focus = `[data-action="${focused.dataset.action}"]`;
            }
        }
        const shape = JSON.stringify(rules || this.rules());
        this.load(`${COMPONENT}_save_view`, {action: 'shape', viewid: this.state.viewid, rules: shape},
            focus);
    }

    /**
     * Adds a module by its code (editor, Exceptions).
     */
    addModule() {
        const input = this.root.querySelector('[data-region="hub-addmodule"]');
        const code = input ? input.value.trim() : '';
        if (!code) {
            return;
        }
        const rules = this.rules();
        rules.course.push(code);
        this.saveShape(rules);
    }

    /**
     * Takes a module added by code out again.
     *
     * @param {string} courseid
     */
    removeAdded(courseid) {
        const rules = this.rules();
        rules.course = rules.course.filter(id => id !== courseid);
        this.saveShape(rules);
    }

    // --- Loading ---------------------------------------------------------------------------------------

    /**
     * The view's state as the server last sent it.
     *
     * @returns {Object}
     */
    readState() {
        const view = this.viewRoot;
        if (view && view.dataset.state) {
            try {
                this.state = JSON.parse(view.dataset.state);
            } catch (e) {
                // Keep the state we had.
            }
        }
        return this.state;
    }

    /**
     * A short message for screen readers.
     *
     * @param {string} text
     */
    say(text) {
        const status = this.root.querySelector(SELECTORS.status);
        if (status) {
            status.textContent = text;
        }
    }

    /**
     * Calls a service and puts the view it returns in place, keeping what the user had open.
     *
     * @param {string} methodname
     * @param {Object} args The call's own arguments; the state (view, year, type, person) is added.
     * @param {string|null} focus A selector to focus afterwards, else the control that had focus is found again.
     */
    async load(methodname, args, focus = null) {
        const isrefresh = methodname === `${COMPONENT}_refresh`;
        if (isrefresh && this.refreshing) {
            return;
        }
        const pending = new Pending(`${COMPONENT}/load`);
        const seq = ++this.seq;
        this.refreshing = this.refreshing || isrefresh;
        const state = this.readState();
        const call = {viewid: state.viewid, yearid: state.yearid, type: this.type, personid: state.personid, ...args};
        const kept = this.keep(focus);
        this.busy(true);
        try {
            const [result] = Ajax.call([{methodname, args: call}]);
            const response = await result;
            if (seq === this.seq) {
                this.show(response.html, kept);
                this.say(await getString(isrefresh ? 'statusrefreshed' : 'statusloaded', COMPONENT));
            }
        } catch (error) {
            this.fail(error);
        } finally {
            if (isrefresh) {
                this.refreshing = false;
            }
            if (seq === this.seq) {
                this.busy(false);
            }
            pending.resolve();
        }
    }

    /**
     * What to put back after a load: the control to focus and how far the editor was scrolled.
     *
     * @param {string|null} focus A selector to focus afterwards.
     * @returns {Object} focus, action (the focused control's data-action), panelScroll
     */
    keep(focus) {
        const focused = document.activeElement;
        const panel = this.root.querySelector(SELECTORS.editor);
        return {
            focus,
            action: focused && this.root.contains(focused) ? focused.dataset.action : null,
            panelScroll: panel ? (panel.querySelector('.hub-panel-body') || panel).scrollTop : 0,
        };
    }

    /**
     * Shows or hides the loading state.
     *
     * @param {boolean} on
     */
    busy(on) {
        const loading = this.root.querySelector(SELECTORS.loading);
        const container = this.root.querySelector(SELECTORS.view);
        if (on) {
            this.say('');
            container.setAttribute('aria-busy', 'true');
        } else {
            container.removeAttribute('aria-busy');
        }
        loading.hidden = !on;
        this.root.querySelectorAll('[data-action="refresh"]').forEach(button => {
            button.disabled = on;
        });
    }

    /**
     * Puts a freshly rendered view in place and restores what the user had open.
     *
     * @param {string} html
     * @param {Object} kept From keep().
     */
    show(html, kept) {
        const container = this.root.querySelector(SELECTORS.view);
        container.innerHTML = html;
        this.readState();
        if (this.type !== this.state.type) {
            this.type = this.state.type;
        }
        if (!this.collapsedTouched) {
            this.collapsed = new Set(['done']);
        }
        this.apply();
        this.renderChips().catch(Notification.exception);
        localise(this.root).catch(Notification.exception);
        this.restore(kept);
    }

    /**
     * Focuses what was focused before a load, and scrolls the editor back to where it was.
     *
     * @param {Object} kept From keep().
     */
    restore(kept) {
        if (this.panelOpen) {
            this.openEditor(kept.focus);
            const body = this.root.querySelector(`${SELECTORS.editor} .hub-panel-body`);
            if (body) {
                body.scrollTop = kept.panelScroll;
            }
            return;
        }
        let target = null;
        if (kept.focus) {
            target = this.root.querySelector(kept.focus);
        } else if (kept.action) {
            target = this.root.querySelector(`[data-action="${kept.action}"]:not([hidden])`) || this.viewRoot;
        }
        if (target) {
            target.focus();
        }
    }

    /**
     * Shows a load's error: in the editor when it is open (a code that matches nothing), else as a notification.
     *
     * @param {Error} error
     */
    fail(error) {
        const box = this.root.querySelector(SELECTORS.editorError);
        if (this.panelOpen && box && error && error.message) {
            box.textContent = error.message;
            box.hidden = false;
        } else {
            Notification.exception(error);
        }
    }
}

/**
 * Starts the hub dashboard.
 *
 * @param {string} uniqid The block's root id.
 * @param {string} state The state as JSON: viewid, yearid, type, personid.
 */
export const init = (uniqid, state) => {
    const root = document.getElementById(uniqid);
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';
    let parsed = {};
    try {
        parsed = JSON.parse(state) || {};
    } catch (e) {
        parsed = {};
    }
    new Hub(root, parsed);
};
