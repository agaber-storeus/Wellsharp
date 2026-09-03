(function () {
    'use strict';

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const weekDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    function isoDate(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    function parseDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
        const [year, month, day] = value.split('-').map(Number);
        return new Date(year, month - 1, day);
    }

    function enhance(input) {
        if (input.dataset.adminDateEnhanced) return;
        input.dataset.adminDateEnhanced = 'true';

        const wrapper = document.createElement('div');
        wrapper.className = 'admin-date-picker';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        input.classList.add('admin-date-native');
        if (input.closest('.system-log-search') && input.getAttribute('aria-label')) {
            const fieldLabel = document.createElement('span');
            fieldLabel.className = 'admin-control-label';
            fieldLabel.textContent = input.getAttribute('aria-label');
            wrapper.insertBefore(fieldLabel, input);
        }

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'admin-date-trigger';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');
        wrapper.insertBefore(trigger, input);

        const label = document.createElement('span');
        const icon = document.createElement('span');
        icon.className = 'admin-date-icon';
        icon.setAttribute('aria-hidden', 'true');
        trigger.append(label, icon);

        const popover = document.createElement('div');
        popover.className = 'admin-date-popover';
        popover.setAttribute('role', 'dialog');
        popover.setAttribute('aria-label', 'Choose date');
        popover.hidden = true;
        wrapper.appendChild(popover);

        let view = parseDate(input.value) || new Date();
        view = new Date(view.getFullYear(), view.getMonth(), 1);

        function close() {
            popover.hidden = true;
            wrapper.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        function updateLabel() {
            const date = parseDate(input.value);
            label.textContent = date ? String(date.getMonth() + 1).padStart(2, '0') + '/' + String(date.getDate()).padStart(2, '0') + '/' + date.getFullYear() : (input.placeholder || 'mm/dd/yyyy');
            label.classList.toggle('is-placeholder', !date);
            trigger.disabled = input.disabled;
        }

        function choose(value) {
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            updateLabel();
            close();
            trigger.focus();
        }

        function render() {
            popover.replaceChildren();
            const header = document.createElement('div');
            header.className = 'admin-date-header';
            const title = document.createElement('strong');
            title.textContent = monthNames[view.getMonth()];
            const year = document.createElement('select');
            year.className = 'admin-date-year';
            year.setAttribute('aria-label', 'Choose year');
            const minimumYear = input.min ? (parseDate(input.min)?.getFullYear() || view.getFullYear() - 100) : view.getFullYear() - 100;
            const maximumYear = input.max ? (parseDate(input.max)?.getFullYear() || view.getFullYear() + 20) : view.getFullYear() + 20;
            for (let value = minimumYear; value <= maximumYear; value++) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                option.selected = value === view.getFullYear();
                year.appendChild(option);
            }
            year.addEventListener('change', (event) => {
                event.stopPropagation();
                view = new Date(Number(event.target.value), view.getMonth(), 1);
                render();
            });
            const previous = document.createElement('button');
            previous.type = 'button'; previous.className = 'admin-date-nav'; previous.innerHTML = '&#8249;'; previous.setAttribute('aria-label', 'Previous month');
            const next = document.createElement('button');
            next.type = 'button'; next.className = 'admin-date-nav'; next.innerHTML = '&#8250;'; next.setAttribute('aria-label', 'Next month');
            previous.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                view = new Date(view.getFullYear(), view.getMonth() - 1, 1);
                render();
            });
            next.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                view = new Date(view.getFullYear(), view.getMonth() + 1, 1);
                render();
            });
            header.append(title, year, previous, next);
            popover.appendChild(header);

            const grid = document.createElement('div');
            grid.className = 'admin-date-grid';
            weekDays.forEach((day) => { const cell = document.createElement('span'); cell.className = 'admin-date-weekday'; cell.textContent = day; grid.appendChild(cell); });
            const firstDay = new Date(view.getFullYear(), view.getMonth(), 1).getDay();
            const daysInMonth = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
            const selected = input.value;
            for (let i = 0; i < firstDay; i++) grid.appendChild(document.createElement('span'));
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(view.getFullYear(), view.getMonth(), day);
                const value = isoDate(date);
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'admin-date-day'; button.textContent = day; button.dataset.value = value;
                if (value === selected) button.classList.add('is-selected');
                if (value === isoDate(new Date())) button.classList.add('is-today');
                if ((input.min && value < input.min) || (input.max && value > input.max)) button.disabled = true;
                button.addEventListener('click', () => choose(value));
                grid.appendChild(button);
            }
            popover.appendChild(grid);

            const footer = document.createElement('div');
            footer.className = 'admin-date-footer';
            const clear = document.createElement('button'); clear.type = 'button'; clear.className = 'admin-date-action'; clear.textContent = 'Clear';
            const today = document.createElement('button'); today.type = 'button'; today.className = 'admin-date-action'; today.textContent = 'Today';
            clear.addEventListener('click', () => choose(''));
            today.addEventListener('click', () => { const now = new Date(); view = new Date(now.getFullYear(), now.getMonth(), 1); choose(isoDate(now)); });
            footer.append(clear, today); popover.appendChild(footer);
        }

        trigger.addEventListener('click', () => {
            if (input.disabled) return;
            if (wrapper.classList.contains('is-open')) { close(); return; }
            document.querySelectorAll('.admin-date-picker.is-open').forEach((item) => item.querySelector('.admin-date-trigger')?.click());
            const selected = parseDate(input.value);
            if (selected) view = new Date(selected.getFullYear(), selected.getMonth(), 1);
            render(); popover.hidden = false; wrapper.classList.add('is-open'); trigger.setAttribute('aria-expanded', 'true');
        });
        input.addEventListener('change', updateLabel);
        input.form?.addEventListener('reset', () => window.setTimeout(updateLabel));
        updateLabel();
    }

    function init() {
        document.querySelectorAll('.admin-content input[type="date"]').forEach(enhance);
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.admin-date-picker')) document.querySelectorAll('.admin-date-picker.is-open').forEach((item) => item.querySelector('.admin-date-trigger')?.click());
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
