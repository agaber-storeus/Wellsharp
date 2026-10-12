(function () {
    'use strict';

    let dropdownNumber = 0;

    function enhance(select) {
        if (select.dataset.adminDropdownEnhanced || select.multiple || select.size > 1) return;

        select.dataset.adminDropdownEnhanced = 'true';

        const wrapper = document.createElement('div');
        wrapper.className = 'admin-dropdown';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.classList.add('admin-dropdown-native');
        if (select.closest('.system-log-search') && select.getAttribute('aria-label')) {
            const fieldLabel = document.createElement('span');
            fieldLabel.className = 'admin-control-label';
            fieldLabel.textContent = select.getAttribute('aria-label');
            wrapper.insertBefore(fieldLabel, select);
        }

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'admin-dropdown-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        const controlId = select.id || `admin-select-${++dropdownNumber}`;
        trigger.id = `${controlId}-trigger`;
        const labels = Array.from(select.labels || []);
        if (labels.length) {
            labels.forEach((item, index) => {
                item.id ||= `${controlId}-label-${index}`;
                item.addEventListener('click', (event) => { event.preventDefault(); trigger.focus(); });
            });
            trigger.setAttribute('aria-labelledby', labels.map(item => item.id).join(' '));
        } else {
            trigger.setAttribute('aria-label', select.getAttribute('aria-label') || select.options[0]?.textContent.trim() || 'Select an option');
        }
        select.tabIndex = -1;

        const label = document.createElement('span');
        label.className = 'admin-dropdown-label';
        trigger.appendChild(label);

        const menu = document.createElement('div');
        menu.className = 'admin-dropdown-menu';
        menu.setAttribute('role', 'listbox');
        menu.id = `${controlId}-options`;
        trigger.setAttribute('aria-controls', menu.id);
        menu.setAttribute('aria-labelledby', trigger.id);
        menu.hidden = true;

        wrapper.insertBefore(trigger, select);
        wrapper.appendChild(menu);

        function close() {
            wrapper.classList.remove('is-open');
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }

        function sync() {
            const option = select.options[select.selectedIndex];
            label.textContent = option ? option.textContent.trim() : 'Select an option';
            trigger.title = label.textContent;
            trigger.disabled = select.disabled;
            trigger.setAttribute('aria-required', select.required ? 'true' : 'false');
            trigger.setAttribute('aria-invalid', select.getAttribute('aria-invalid') || 'false');
            if (select.hasAttribute('aria-describedby')) trigger.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
            wrapper.classList.toggle('is-disabled', select.disabled);
            menu.querySelectorAll('[role="option"]').forEach((item) => {
                const selected = item.dataset.value === select.value;
                item.classList.toggle('is-selected', selected);
                item.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
        }

        function choose(value) {
            if (select.value !== value) {
                select.value = value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            sync();
            close();
            trigger.focus();
        }

        function rebuild() {
            menu.replaceChildren();
            let lastGroup = null;
            let optionContainer = menu;
            Array.from(select.options).forEach((option) => {
                const group = option.parentElement?.tagName === 'OPTGROUP' ? option.parentElement : null;
                if (group && group !== lastGroup) {
                    optionContainer = document.createElement('div');
                    optionContainer.setAttribute('role', 'group');
                    optionContainer.setAttribute('aria-label', group.label);
                    const heading = document.createElement('div');
                    heading.className = 'admin-dropdown-group-label';
                    heading.textContent = group.label;
                    heading.setAttribute('aria-hidden', 'true');
                    optionContainer.appendChild(heading);
                    menu.appendChild(optionContainer);
                }
                if (!group) optionContainer = menu;
                lastGroup = group;
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'admin-dropdown-option';
                item.textContent = option.textContent.trim();
                item.dataset.value = option.value;
                item.disabled = option.disabled || Boolean(group?.disabled);
                item.setAttribute('role', 'option');
                item.setAttribute('aria-selected', 'false');
                item.addEventListener('click', () => choose(option.value));
                optionContainer.appendChild(item);
            });
            sync();
        }

        function open() {
            if (select.disabled) return;
            document.querySelectorAll('.admin-dropdown.is-open').forEach((item) => {
                if (item !== wrapper) item.querySelector('.admin-dropdown-trigger')?.click();
            });
            wrapper.classList.add('is-open');
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            const selected = menu.querySelector('.is-selected:not(:disabled)') || menu.querySelector('.admin-dropdown-option:not(:disabled)');
            selected?.focus();
        }

        trigger.addEventListener('click', () => wrapper.classList.contains('is-open') ? close() : open());
        trigger.addEventListener('keydown', (event) => {
            if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
                event.preventDefault();
                open();
            }
        });
        menu.addEventListener('keydown', (event) => {
            const options = Array.from(menu.querySelectorAll('.admin-dropdown-option:not(:disabled)'));
            const index = options.indexOf(document.activeElement);
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(); trigger.focus(); }
            if (event.key === 'ArrowDown') { event.preventDefault(); options[(index + 1) % options.length]?.focus(); }
            if (event.key === 'ArrowUp') { event.preventDefault(); options[(index - 1 + options.length) % options.length]?.focus(); }
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); document.activeElement?.click(); }
        });
        select.addEventListener('change', sync);
        select.addEventListener('input', sync);
        select.addEventListener('invalid', (event) => {
            event.preventDefault();
            trigger.setAttribute('aria-invalid', 'true');
            trigger.focus();
        });
        wrapper.addEventListener('focusout', () => window.setTimeout(() => {
            if (!wrapper.contains(document.activeElement)) close();
        }));
        function bindModel() {
            if (!select._x_model || select.dataset.adminDropdownModelBound) return;
            select.dataset.adminDropdownModelBound = 'true';
            window.Alpine.effect(() => {
                select._x_model.get();
                window.Alpine.nextTick(sync);
            });
        }
        document.addEventListener('alpine:initialized', bindModel, { once: true });
        bindModel();
        select.form?.addEventListener('reset', () => window.setTimeout(sync));
        new MutationObserver(rebuild).observe(select, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'selected', 'label', 'aria-invalid', 'aria-describedby'] });
        rebuild();
    }

    function init() {
        document.querySelectorAll('.admin-content select').forEach(enhance);
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.admin-dropdown')) {
                document.querySelectorAll('.admin-dropdown.is-open').forEach((item) => item.querySelector('.admin-dropdown-trigger')?.click());
            }
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
