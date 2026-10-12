import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// Minimal DOM fixture tests the enhancement without browser or database access.
class Element {
    constructor(tagName = 'div') {
        this.tagName = tagName.toUpperCase();
        this.children = [];
        this.dataset = {};
        this.attributes = {};
        this.listeners = {};
        this.textContent = '';
        this.className = '';
        this.classList = {
            add: name => this.classList.toggle(name, true),
            contains: name => this.className.split(' ').includes(name),
            toggle: (name, enabled) => {
                const names = new Set(this.className.split(' ').filter(Boolean));
                enabled ? names.add(name) : names.delete(name);
                this.className = [...names].join(' ');
            },
            remove: name => this.classList.toggle(name, false),
        };
    }
    appendChild(child) {
        if (child.parentNode) child.parentNode.children = child.parentNode.children.filter(item => item !== child);
        this.children.push(child);
        child.parentNode = child.parentElement = this;
    }
    insertBefore(child, before) {
        this.children.splice(this.children.indexOf(before), 0, child);
        child.parentNode = child.parentElement = this;
    }
    setAttribute(name, value) { this.attributes[name] = value; }
    getAttribute(name) { return this.attributes[name] ?? null; }
    hasAttribute(name) { return name in this.attributes; }
    addEventListener(name, callback) { (this.listeners[name] ||= []).push(callback); }
    dispatchEvent(event) { (this.listeners[event.type] || []).forEach(callback => callback(event)); }
    click() { if (!this.disabled) this.dispatchEvent({ type: 'click' }); }
    focus() { this.focused = true; }
    closest() { return null; }
    replaceChildren() { this.children = []; }
    querySelectorAll(selector) {
        const children = this.children.flatMap(child => [child, ...child.querySelectorAll('*')]);
        if (selector === '*') return children;
        if (selector === '[role="option"]') return children.filter(child => child.getAttribute('role') === 'option');
        if (selector.includes('.admin-dropdown-option')) return children.filter(child => child.classList.contains('admin-dropdown-option') && !child.disabled);
        return [];
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}

function fixture() {
    const select = new Element('select');
    select.id = 'course_id';
    select.value = '2';
    select.required = true;
    select.labels = [new Element('label')];
    const empty = new Element('option');
    empty.value = '';
    empty.textContent = 'Select Subject';
    const group = new Element('optgroup');
    group.label = 'Drilling Operations';
    const first = new Element('option');
    first.value = '1';
    first.textContent = 'Driller';
    group.appendChild(first);
    const legacy = new Element('optgroup');
    legacy.label = 'Uncategorized';
    const second = new Element('option');
    second.value = '2';
    second.textContent = 'Legacy subject';
    legacy.appendChild(second);
    select.appendChild(empty);
    select.appendChild(group);
    select.appendChild(legacy);
    select.options = [empty, first, second];
    Object.defineProperty(select, 'selectedIndex', { get: () => select.options.findIndex(option => option.value === select.value) });
    const parent = new Element();
    parent.appendChild(select);
    const effects = [];
    select._x_model = { get: () => select.value };
    const document = {
        readyState: 'complete',
        createElement: tag => new Element(tag),
        querySelectorAll: selector => selector === '.admin-content select' ? [select] : [],
        addEventListener() {},
    };
    const observers = [];
    vm.runInNewContext(readFileSync(new URL('../../public/js/admin-dropdowns.js', import.meta.url), 'utf8'), {
        document,
        window: { setTimeout: callback => callback(), Alpine: { effect: callback => { effects.push(callback); callback(); }, nextTick: callback => callback() } },
        Event: class { constructor(type) { this.type = type; } },
        MutationObserver: class { constructor(callback) { observers.push(callback); } observe() {} },
    });
    const wrapper = parent.children[0];
    return { select, group, wrapper, effects, observers, trigger: wrapper.children[0], menu: wrapper.children[2] };
}

test('keeps grouped options, selection, accessible label and original value', () => {
    const { select, menu, trigger } = fixture();
    assert.equal(select.value, '2');
    assert.equal(trigger.children[0].textContent, 'Legacy subject');
    assert.equal(trigger.getAttribute('aria-labelledby'), 'course_id-label-0');
    assert.equal(select.tabIndex, -1);
    assert.equal(trigger.getAttribute('aria-required'), 'true');
    assert.deepEqual(menu.children.slice(1).map(group => group.getAttribute('aria-label')), ['Drilling Operations', 'Uncategorized']);
    assert.equal(menu.querySelectorAll('[role="option"]').length, 3);
});

test('choosing an option dispatches the existing change event', () => {
    const { select, menu } = fixture();
    let changes = 0;
    select.addEventListener('change', () => changes++);
    menu.querySelectorAll('[role="option"]')[1].click();
    assert.equal(select.value, '1');
    assert.equal(changes, 1);
    assert.equal(menu.hidden, true);
});

test('Alpine resets refresh the displayed selection without changing requests', () => {
    const { select, trigger, effects } = fixture();
    select.value = '';
    effects[0]();
    assert.equal(trigger.children[0].textContent, 'Select Subject');
});

test('nested option mutations preserve disabled category behavior', () => {
    const { group, observers, menu } = fixture();
    group.disabled = true;
    observers[0]();
    assert.equal(menu.querySelectorAll('[role="option"]')[1].disabled, true);
});

test('invalid native controls focus the visible trigger', () => {
    const { select, trigger } = fixture();
    let prevented = false;
    select.dispatchEvent({ type: 'invalid', preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(trigger.focused, true);
    assert.equal(trigger.getAttribute('aria-invalid'), 'true');
});
