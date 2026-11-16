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
 * A category picker: a tree of checkboxes with a search box (C2, C3). Used by the view editor and by the
 * administrator's "categories the hub works in" setting.
 *
 * Ticking a category ticks everything under it; unticking a child leaves its parent partly ticked
 * (indeterminate). The search keeps the categories whose name holds every word typed, with their ancestors
 * greyed so the path reads. The picker tells its owner about a change with a bubbling "hub-catpicker-change"
 * event, after the tree has settled. Which ids are saved (the minimal cover) is the server's rule.
 *
 * @module     block_le_hub_dashboard/catpicker
 * @copyright  2026 Pierre Camaly de Brosses - University of Exeter
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';

const SELECTORS = {
    picker: '[data-region="hub-catpicker"]',
    item: '[data-region="hub-tree-item"]',
    box: '[data-action="pick-category"]',
    search: '[data-action="catpicker-search"]',
    empty: '[data-region="hub-catpicker-empty"]',
};

/** How long after the last keystroke the search runs, in milliseconds. */
const DEBOUNCE = 300;

/**
 * One picker.
 */
class CatPicker {
    /**
     * @param {HTMLElement} root The picker's element.
     */
    constructor(root) {
        this.root = root;
        this.timer = null;
        this.items = Array.from(root.querySelectorAll(SELECTORS.item));
        this.byId = new Map();
        this.children = new Map();
        this.items.forEach(item => {
            const id = item.dataset.id;
            const parent = item.dataset.parent;
            this.byId.set(id, item);
            if (!this.children.has(parent)) {
                this.children.set(parent, []);
            }
            this.children.get(parent).push(item);
        });
        // A ticked category covers everything under it (C3): show that.
        this.items.forEach(item => {
            const box = item.querySelector(SELECTORS.box);
            if (box.checked) {
                this.descendants(item).forEach(d => {
                    d.querySelector(SELECTORS.box).checked = true;
                });
            }
        });
        this.settle();
        root.addEventListener('change', e => {
            if (e.target.matches(SELECTORS.box)) {
                this.toggled(e.target);
            }
        });
        const search = root.querySelector(SELECTORS.search);
        if (search) {
            search.addEventListener('input', () => {
                // Held as pending work, so a test that types waits for the tree to narrow.
                if (!this.pending) {
                    this.pending = new Pending('block_le_hub_dashboard/catpicker');
                }
                clearTimeout(this.timer);
                this.timer = setTimeout(() => {
                    this.filter(search.value);
                    this.pending.resolve();
                    this.pending = null;
                }, DEBOUNCE);
            });
            search.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(this.timer);
                    this.filter(search.value);
                    if (this.pending) {
                        this.pending.resolve();
                        this.pending = null;
                    }
                }
            });
        }
    }

    /**
     * The items under one, at every depth.
     *
     * @param {HTMLElement} item
     * @returns {HTMLElement[]}
     */
    descendants(item) {
        const out = [];
        const queue = [...(this.children.get(item.dataset.id) || [])];
        while (queue.length) {
            const next = queue.shift();
            out.push(next);
            queue.push(...(this.children.get(next.dataset.id) || []));
        }
        return out;
    }

    /**
     * A box was ticked or unticked by hand: everything under it follows, then the parents are worked out.
     *
     * @param {HTMLInputElement} box
     */
    toggled(box) {
        const item = box.closest(SELECTORS.item);
        box.indeterminate = false;
        this.descendants(item).forEach(d => {
            const child = d.querySelector(SELECTORS.box);
            child.checked = box.checked;
            child.indeterminate = false;
        });
        this.settle();
        this.root.dispatchEvent(new CustomEvent('hub-catpicker-change', {bubbles: true}));
    }

    /**
     * Works out every parent's state from its children: all ticked, none, or some (partly ticked).
     */
    settle() {
        // Deepest first, so a parent sees its children settled.
        const byDepth = [...this.items].sort((a, b) => this.depth(b) - this.depth(a));
        byDepth.forEach(item => {
            const kids = this.children.get(item.dataset.id) || [];
            if (!kids.length) {
                return;
            }
            const box = item.querySelector(SELECTORS.box);
            const states = kids.map(k => k.querySelector(SELECTORS.box));
            const all = states.every(b => b.checked && !b.indeterminate);
            const none = states.every(b => !b.checked && !b.indeterminate);
            if (all) {
                box.checked = true;
                box.indeterminate = false;
            } else if (none) {
                // A parent ticked by hand with children unticked would be odd; its tick stands only when it is
                // the leaf of what was ticked, which cannot happen after toggled() ticked its children.
                box.indeterminate = false;
                box.checked = false;
            } else {
                box.checked = false;
                box.indeterminate = true;
            }
            item.classList.toggle('hub-tree-item--partial', box.indeterminate);
        });
    }

    /**
     * How deep an item sits, from its class.
     *
     * @param {HTMLElement} item
     * @returns {number}
     */
    depth(item) {
        const match = item.className.match(/hub-tree-item--depth(\d+)/);
        return match ? parseInt(match[1], 10) : 1;
    }

    /**
     * Shows the categories whose name holds every word typed, their ancestors greyed; everything when empty (C2).
     *
     * @param {string} text
     */
    filter(text) {
        const words = text.toLowerCase().split(/\s+/).filter(Boolean);
        const keep = new Set();
        const dim = new Set();
        if (words.length) {
            this.items.forEach(item => {
                const name = item.querySelector('.hub-tree-name').textContent.toLowerCase();
                if (words.every(w => name.includes(w))) {
                    keep.add(item);
                    let parent = this.byId.get(item.dataset.parent);
                    while (parent) {
                        dim.add(parent);
                        parent = this.byId.get(parent.dataset.parent);
                    }
                }
            });
        }
        this.items.forEach(item => {
            const shown = !words.length || keep.has(item) || dim.has(item);
            item.hidden = !shown;
            item.classList.toggle('hub-tree-item--dim', words.length > 0 && !keep.has(item) && dim.has(item));
        });
        const empty = this.root.querySelector(SELECTORS.empty);
        if (empty) {
            empty.hidden = !words.length || keep.size > 0;
        }
    }
}

/**
 * Attaches a picker to every picker element under a root that has none yet.
 *
 * @param {HTMLElement|Document} root
 * @returns {CatPicker[]}
 */
export const attach = (root = document) => {
    const out = [];
    root.querySelectorAll(SELECTORS.picker).forEach(el => {
        if (!el.dataset.catpicker) {
            el.dataset.catpicker = '1';
            out.push(new CatPicker(el));
        }
    });
    return out;
};

/**
 * The admin setting's entry point.
 *
 * @param {string} id The picker element's id.
 */
export const init = id => {
    const el = document.getElementById(id);
    if (el) {
        attach(el.parentNode);
    }
};
