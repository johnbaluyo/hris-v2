import { Modal } from 'bootstrap';
import { notify, confirmAction } from './ui';
import { initializeOfficePunch } from './office-punch';

export const feedback = notify;

async function refreshNotificationCount() {
    const badge = document.querySelector('#notification-unread');
    if (!badge) return;
    try {
        const result = await request('/api/notifications/count');
        badge.textContent = String(result.unread);
        badge.hidden = result.unread === 0;
    } catch { badge.hidden = true; }
}
window.addEventListener('notifications:changed', refreshNotificationCount);
refreshNotificationCount();

export async function request(url, options = {}) {
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, ...options.headers };
    if (options.body && !(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(response.status === 419 ? 'Your session expired. Refresh the page and try again.' : response.status === 401 ? 'Please sign in again.' : data.message || 'The request failed. Please try again.');
        error.errors = data.errors;
        throw error;
    }
    return data;
}

function display(value) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (typeof value === 'object') return Array.isArray(value) ? value.map(display).join('; ') : Object.entries(value).map(([key, val]) => `${key.replaceAll('_', ' ')}: ${display(val)}`).join(' · ');
    if (/^\d{4}-\d{2}-\d{2}T/.test(String(value))) return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Manila' }).format(new Date(value));
    return String(value);
}

export class AsyncTable {
    constructor(element) {
        this.el = element;
        this.page = 1;
        this.lastPage = 1;
        this.rows = new Map();
        this.columns = JSON.parse(element.dataset.columns);
        this.actions = JSON.parse(element.dataset.actions);
        this.params = new URLSearchParams();
        this.sequence = 0;
        this.el.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (!button) return;
            if (button.dataset.page) {
                this.page += button.dataset.page === 'next' ? 1 : -1;
                this.load();
            } else if (button.hasAttribute('data-retry')) this.load();
            else if (button.dataset.action) this.el.dispatchEvent(new CustomEvent('table:action', { detail: { action: button.dataset.action, row: this.rows.get(button.dataset.id), table: this } }));
        });
        let timer;
        this.el.querySelectorAll('[data-table-filter]').forEach(input => {
            const filter = () => { this.page = 1; this.load(); };
            input.addEventListener(input.type === 'search' ? 'keyup' : 'change', () => { clearTimeout(timer); timer = setTimeout(filter, input.type === 'search' ? 250 : 0); });
            if (input.type === 'search') input.addEventListener('search', filter);
        });
        element.table = this;
        if (!element.closest('.modal')) this.load();
    }

    async load() {
        this.controller?.abort();
        this.controller = new AbortController();
        const sequence = ++this.sequence;
        this.el.setAttribute('aria-busy', 'true');
        this.el.querySelector('.table-loading').classList.remove('d-none');
        this.el.querySelector('.table-feedback').classList.add('d-none');
        this.el.querySelectorAll('[data-page]').forEach(button => button.disabled = true);
        this.el.querySelectorAll('[data-action]').forEach(button => button.disabled = true);
        const params = new URLSearchParams(this.params);
        this.el.querySelectorAll('[data-table-filter]').forEach(input => params.set(input.name, input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value));
        params.set('page', this.page);
        const separator = this.el.dataset.endpoint.includes('?') ? '&' : '?';
        try {
            const result = await request(`${this.el.dataset.endpoint}${separator}${params}`, { signal: this.controller.signal });
            if (sequence !== this.sequence) return;
            const rows = result.data || [];
            this.rows.clear();
            this.page = result.current_page || 1;
            this.lastPage = result.last_page || 1;
            if (rows.length === 0 && this.page > this.lastPage) { this.page = this.lastPage; this.load(); return; }
            const body = this.el.querySelector('tbody');
            body.replaceChildren();
            rows.forEach(row => {
                this.rows.set(String(row.id), row);
                const tr = document.createElement('tr');
                this.columns.forEach(column => {
                    const cell = document.createElement('td');
                    const text = display(column.key.split('.').reduce((value, key) => value?.[key], row));
                    if (column.avatar && row.avatar_url) {
                        const wrapper = document.createElement('div'); wrapper.className = 'd-flex align-items-center gap-2';
                        const image = document.createElement('img'); image.src = row.avatar_url; image.dataset.avatar = row.avatar_url;
                        image.alt = `${row.name} profile picture`; image.width = image.height = 40; image.loading = 'lazy'; image.className = 'account-avatar';
                        const label = document.createElement('span'); label.textContent = text;
                        wrapper.append(image, label); cell.append(wrapper);
                    } else cell.textContent = text.length > 180 ? `${text.slice(0, 180)}…` : text;
                    tr.append(cell);
                });
                if (this.actions.length) {
                    const cell = document.createElement('td');
                    const actions = document.createElement('div');
                    actions.className = 'table-actions';
                    this.actions.forEach(action => {
                        if (action.editable && row.can_edit === false) return;
                        if (action.when && !row[action.when]) return;
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = `btn btn-sm ${action.danger ? 'btn-outline-danger' : 'btn-outline-primary'}`;
                        button.textContent = action.label;
                        const icons = { edit: 'pen-to-square', details: 'circle-info', delete: 'trash-can', profile: 'address-card', attendance: 'clock', 'service-record': 'briefcase', files: 'folder-open', archive: 'box-archive', download: 'download' };
                        if (icons[action.name]) { const icon = document.createElement('i'); icon.className = `fa-solid fa-${icons[action.name]} me-1`; icon.setAttribute('aria-hidden', 'true'); button.prepend(icon); }
                        button.dataset.action = action.name;
                        button.dataset.id = row.id;
                        actions.append(button);
                    });
                    cell.append(actions);
                    tr.append(cell);
                }
                body.append(tr);
            });
            if (!rows.length) {
                const cell = document.createElement('td'); cell.colSpan = this.columns.length + (this.actions.length ? 1 : 0); cell.className = 'text-center py-5 text-body-secondary'; cell.textContent = 'No records found.';
                const tr = document.createElement('tr'); tr.append(cell); body.append(tr);
            }
            this.el.querySelector('.table-count').textContent = `${result.total ?? rows.length} records · 10 rows per page`;
            this.el.querySelector('.table-page').textContent = `Page ${this.page} of ${this.lastPage}`;
            this.el.querySelector('[data-page="previous"]').disabled = this.page <= 1;
            this.el.querySelector('[data-page="next"]').disabled = this.page >= this.lastPage;
            this.el.querySelector('[data-retry]').classList.add('d-none');
            this.el.dispatchEvent(new CustomEvent('table:loaded', { detail: result }));
        } catch (error) {
            if (error.name === 'AbortError' || sequence !== this.sequence) return;
            const box = this.el.querySelector('.table-feedback'); box.textContent = error.message; box.classList.remove('d-none');
            this.el.querySelector('[data-retry]').classList.remove('d-none');
        } finally {
            if (sequence === this.sequence) { this.el.setAttribute('aria-busy', 'false'); this.el.querySelector('.table-loading').classList.add('d-none'); }
        }
    }
}

export function fillForm(form, values) {
    form.reset();
    for (const element of form.elements) {
        if (!element.name || element.type === 'file') continue;
        const value = element.name.split('.').reduce((data, key) => data?.[key], values);
        if (value === undefined) continue;
        if (element.type === 'checkbox') element.checked = Boolean(value);
        else if (element.multiple) [...element.options].forEach(option => option.selected = (value || []).map(String).includes(option.value));
        else if (['date', 'datetime-local'].includes(element.type)) {
            if (!value) element.value = '';
            else if (!String(value).includes('T') && element.type === 'date') element.value = String(value).slice(0, 10);
            else {
                const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date(value)).map(part => [part.type, part.value]));
                element.value = `${parts.year}-${parts.month}-${parts.day}${element.type === 'datetime-local' ? `T${parts.hour}:${parts.minute}` : ''}`;
            }
        }
        else if (element.type === 'time') element.value = value ? String(value).slice(0, 5) : '';
        else element.value = typeof value === 'object' && value !== null ? JSON.stringify(value) : value ?? '';
    }
    form.querySelector('[data-form-errors]')?.classList.add('d-none');
}

export function formData(form) {
    const result = {};
    for (const element of form.elements) {
        if (!element.name || element.disabled || ['submit', 'file'].includes(element.type)) continue;
        let value = element.type === 'checkbox' ? element.checked : element.multiple ? [...element.selectedOptions].map(option => Number(option.value)) : element.value === '' ? null : element.value;
        if (element.dataset.json && value) { try { value = JSON.parse(value); } catch { throw new Error(`${element.labels?.[0]?.textContent || element.name} must contain a valid JSON list.`); } }
        if (element.type === 'select-one' && ['true', 'false'].includes(value)) value = value === 'true';
        if (element.type === 'select-one' && ['1','0'].includes(value) && element.dataset.boolean) value = value === '1';
        const keys = element.name.split('.');
        let target = result;
        keys.forEach((key, index) => { if (index === keys.length - 1) target[key] = value; else target = target[key] ??= {}; });
    }
    return result;
}

export function bindForm(form, options = {}) {
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = event.submitter ?? form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
        const errors = form.querySelector('[data-form-errors]');
        if (button?.disabled) return;
        if (button) button.disabled = true;
        errors?.classList.add('d-none');
        try {
            const body = form.enctype === 'multipart/form-data' ? new FormData(form) : formData(form);
            options.prepare?.(body);
            const result = await request(form.dataset.endpoint, { method: form.dataset.method || 'POST', body });
            if (body instanceof FormData && body.get('purpose') === 'profile') window.AppUI.refreshAvatars();
            feedback(result.message || 'Saved.');
            const modal = form.closest('.modal');
            if (modal && !options.keepOpen) Modal.getInstance(modal)?.hide();
            options.success?.(result, body);
            form.dispatchEvent(new CustomEvent('form:saved', { detail: result }));
        } catch (error) {
            const text = error.errors ? Object.values(error.errors).flat().join(' ') : error.message;
            if (errors) { errors.textContent = text; errors.classList.remove('d-none'); errors.focus(); }
            feedback(text, true);
        } finally { if (button) button.disabled = false; }
    });
}

export function openModal(id) { Modal.getOrCreateInstance(document.getElementById(id)).show(); }

export function showDetails(title, data) {
    let modal = document.getElementById('record-details');
    if (!modal) {
        modal = document.createElement('div'); modal.className = 'modal fade'; modal.id = 'record-details'; modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'record-detail-title');
        modal.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="h5 modal-title" id="record-detail-title"></h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"></div></div></div>';
        document.body.append(modal);
    }
    modal.querySelector('h2').textContent = title;
    const dl = document.createElement('dl'); dl.className = 'record-grid row';
    Object.entries(data).forEach(([key, value]) => { const dt = document.createElement('dt'); dt.className = 'col-sm-4'; dt.textContent = key.replaceAll('_', ' '); const dd = document.createElement('dd'); dd.className = 'col-sm-8'; dd.textContent = display(value); dl.append(dt, dd); });
    modal.querySelector('.modal-body').replaceChildren(dl);
    openModal('record-details');
}

document.querySelectorAll('[data-table]').forEach(element => new AsyncTable(element));
document.querySelectorAll('[data-employee-picker]').forEach(picker => {
    let page = 1, lastPage = 1, timer, controller;
    const select = picker.querySelector('select');
    const search = picker.querySelector('[data-employee-search]');
    const status = picker.querySelector('[data-picker-status]');
    const previous = picker.querySelector('[data-picker-prev]');
    const next = picker.querySelector('[data-picker-next]');
    async function load() {
        controller?.abort();
        controller = new AbortController();
        const active = controller;
        previous.disabled = next.disabled = true;
        status.textContent = 'Loading names…';
        try {
            const data = await request(`/employees/lookup?${new URLSearchParams({ search: search.value, page })}`, { signal: active.signal });
            const selected = [...select.options].filter(option => option.selected && option.value);
            select.replaceChildren();
            if (!select.multiple) select.append(new Option('Choose an employee…', ''));
            selected.forEach(option => select.append(option));
            data.data.forEach(person => { if (!selected.some(option => option.value === String(person.id))) select.append(new Option(person.name, person.id)); });
            page = data.current_page;
            lastPage = data.last_page;
            status.textContent = `${data.total} matching names · page ${page} of ${lastPage}`;
            previous.disabled = page <= 1;
            next.disabled = page >= lastPage;
        } catch (error) { if (error.name !== 'AbortError') status.textContent = error.message; }
    }
    search.addEventListener('keyup', () => { clearTimeout(timer); timer = setTimeout(() => { page = 1; load(); }, 250); });
    search.addEventListener('search', () => { page = 1; load(); });
    previous.addEventListener('click', () => { page--; load(); });
    next.addEventListener('click', () => { page++; load(); });
    load();
});
document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));

// Page modules use the same request, error, form and table behaviors.
window.HRIS = { request, feedback, fillForm, bindForm, openModal, showDetails, confirm: confirmAction,
    mountCalendar: async (element, options) => (await import('./calendar')).mountCalendar(element, options) };
initializeOfficePunch();
document.dispatchEvent(new Event('hris:ready'));
