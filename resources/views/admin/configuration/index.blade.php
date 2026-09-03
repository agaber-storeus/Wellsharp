@extends('layouts.admin')

@section('admin-content')
@php
    $labels = ['levels' => 'Subject Levels', 'stacks' => 'Stacks', 'supplements' => 'Supplements', 'languages' => 'Languages'];
    $configurationSections = collect($items)->map(function ($values, string $type) use ($labels): array {
        return [
            'type' => $type,
            'label' => $labels[$type] ?? ucfirst($type),
            'store_url' => route('admin.configuration.store', $type),
            'reorder_url' => route('admin.configuration.reorder', $type),
            'rows' => collect($values)->map(fn ($item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'sort_order' => $item->sort_order,
                'active' => $item->is_active,
                'saving' => false,
                'toggling' => false,
                'update_url' => route('admin.configuration.update', [$type, $item->id]),
                'toggle_url' => route('admin.configuration.toggle', [$type, $item->id]),
            ])->values(),
        ];
    })->values();
@endphp

<div class="page-head"><div><span class="admin-kicker">Settings</span><h1>Subject Configuration</h1><p>Manage the reference values used when configuring Subjects.</p></div></div>

<div class="card" x-data="configurationTable(@js($configurationSections))">
    <div class="admin-section-head"><div><h2>Reference Values</h2><p class="configuration-order-help muted">New values are added first. Drag the handle to change the order.</p></div></div>
    <div class="alert" x-show="notice" x-bind:class="noticeType === 'error' ? 'error' : 'success'" x-text="notice" x-cloak role="status"></div>

    <div class="configuration-sections">
        <template x-for="section in sections" :key="section.type">
            <section class="configuration-section">
                <div class="admin-section-head"><h2 x-text="section.label"></h2><span class="badge active" x-text="section.rows.length + ' values'"></span></div>
                <form class="configuration-create" x-on:submit.prevent="create(section)">
                    <input x-model="section.newName" placeholder="Add new value" required>
                    <button class="btn" type="submit" x-bind:disabled="section.saving || !section.newName.trim()" x-text="section.saving ? 'Adding...' : 'Add'"></button>
                </form>
                <div class="table-wrap"><table class="table"><thead><tr><th class="configuration-order-heading">Order</th><th>Name</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                    <template x-for="row in section.rows" :key="row.id">
                        <tr x-bind:class="dragging && dragging.id === row.id ? 'configuration-row-dragging' : ''" x-on:dragover.prevent="dragOver(section, row, $event)" x-on:drop="drop(section, row, $event)">
                            <td class="configuration-order-cell"><span class="configuration-drag-handle" draggable="true" x-on:dragstart.stop="dragStart(section, row, $event)" title="Drag to reorder" aria-label="Drag to reorder">::</span></td>
                            <td>
                                <input class="configuration-name-input" x-model="row.name" required x-on:blur="queueSave(section, row)" x-on:keydown.enter.prevent="save(section, row)">
                                <small class="configuration-save-state muted" x-show="row.saving" x-cloak>Saving...</small>
                            </td>
                            <td><div class="admin-status-cell"><span class="badge" x-bind:class="row.active ? 'active' : 'archived'" x-text="row.active ? 'Active' : 'Inactive'"></span></div></td>
                            <td>
                                <div class="actions configuration-actions admin-actions-cell">
                                    <button class="btn secondary small" type="button" x-on:click.prevent="toggle(section, row)" x-bind:aria-busy="row.toggling ? 'true' : 'false'" x-text="row.toggling ? 'Updating...' : (row.active ? 'Deactivate' : 'Activate')"></button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="section.rows.length === 0" x-cloak><td colspan="4"><div class="admin-empty-row"><span class="admin-empty-row-icon">🗂️</span>No values found.</div></td></tr>
                </tbody></table></div>
            </section>
        </template>
    </div>
</div>

<style>
    .configuration-sections{display:grid;grid-template-columns:minmax(0,1fr);gap:24px}.configuration-section{margin-top:0;border:1px solid var(--admin-line);border-radius:7px;padding:18px;background:#fff}.configuration-create{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:12px 0 16px}.configuration-create input:first-of-type{flex:1;min-width:160px}.configuration-create input,.configuration-name-input{border:1px solid #b9c7d2;border-radius:5px;padding:9px 10px;background:#fff;color:var(--admin-ink);font:inherit}.configuration-order-heading{width:64px}.configuration-order-cell{text-align:center!important}.configuration-drag-handle{display:inline-grid;place-items:center;width:30px;height:30px;border:1px solid var(--admin-line);border-radius:5px;background:#f5f8fa;color:var(--admin-blue);font:700 16px/1 Arial,sans-serif;letter-spacing:-2px;cursor:grab;user-select:none}.configuration-drag-handle:active{cursor:grabbing}.configuration-name-input{display:block;width:100%;min-width:0}.configuration-save-state{display:block;margin-top:4px;font-size:11px}.configuration-section .table td{vertical-align:middle}.configuration-section .table td:last-child{white-space:nowrap}.configuration-actions{position:relative;z-index:2;display:flex;align-items:center;gap:8px;pointer-events:auto}.configuration-actions .btn{position:relative;z-index:3;cursor:pointer!important;pointer-events:auto!important;touch-action:manipulation}.configuration-actions .btn:disabled{cursor:pointer!important}.configuration-row-dragging{opacity:.45;background:#eef6fb!important}.configuration-section .btn:disabled{opacity:.6}
</style>

<script>
    window.configurationTable = function (initialSections) {
        return {
            sections: (initialSections || []).map((section) => ({ ...section, rows: (section.rows || []).map((row) => ({ ...row, savedName: row.name })), newName: '', saving: false, ordering: false })),
            dragging: null,
            notice: '',
            noticeType: 'success',
            notify(message, type = 'success') { this.notice = message; this.noticeType = type; window.clearTimeout(this.noticeTimer); this.noticeTimer = window.setTimeout(() => { this.notice = ''; }, 10000); },
            queueSave(section, row) {
                window.clearTimeout(row.saveTimer);
                if (row.name.trim() === row.savedName) return;
                row.saveTimer = window.setTimeout(() => { row.saveTimer = null; this.save(section, row); }, 150);
            },
            async request(url, method, body = {}) {
                const controller = new AbortController();
                const timeout = window.setTimeout(() => controller.abort(), 10000);
                try {
                    const response = await fetch(url, { method, credentials: 'same-origin', signal: controller.signal, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(body) });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(payload.message || 'The configuration change could not be saved.');
                    return payload;
                } catch (error) {
                    if (error.name === 'AbortError') throw new Error('The configuration request timed out. Please try again.');
                    throw error;
                } finally {
                    window.clearTimeout(timeout);
                }
            },
            async create(section) {
                const name = section.newName.trim();
                if (!name) return;
                section.saving = true;
                try { const payload = await this.request(section.store_url, 'POST', { name }); section.rows.unshift({ ...payload.row, saving: false, toggling: false, savedName: payload.row.name }); section.newName = ''; this.notify(payload.message); }
                catch (error) { this.notify(error.message, 'error'); }
                finally { section.saving = false; }
            },
            async save(section, row) {
                const name = row.name.trim();
                if (!name) { this.notify('Name is required.', 'error'); return; }
                if (name === row.savedName) return;
                row.saving = true;
                try { const payload = await this.request(row.update_url, 'PATCH', { name }); Object.assign(row, payload.row); row.savedName = row.name; this.notify(payload.message); }
                catch (error) { this.notify(error.message, 'error'); }
                finally { row.saving = false; }
            },
            async toggle(section, row) {
                if (row.toggling) return;
                row.toggling = true;
                try { const payload = await this.request(row.toggle_url, 'PATCH'); if (!payload.row) throw new Error('The configuration status response was invalid.'); Object.assign(row, payload.row); row.savedName = row.name; this.notify(payload.message); }
                catch (error) { this.notify(error.message, 'error'); }
                finally { row.toggling = false; }
            },
            dragStart(section, row, event) { this.dragging = { type: section.type, id: row.id }; event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', String(row.id)); },
            dragOver(section, row, event) { if (!this.dragging || this.dragging.type !== section.type || this.dragging.id === row.id) return; event.dataTransfer.dropEffect = 'move'; },
            async drop(section, row, event) {
                event.preventDefault();
                if (!this.dragging || this.dragging.type !== section.type || this.dragging.id === row.id) { this.dragging = null; return; }
                const from = section.rows.findIndex((item) => item.id === this.dragging.id); const to = section.rows.findIndex((item) => item.id === row.id);
                if (from < 0 || to < 0) { this.dragging = null; return; }
                const previousRows = [...section.rows]; const [moved] = section.rows.splice(from, 1); section.rows.splice(to, 0, moved); this.dragging = null; section.ordering = true;
                try { const payload = await this.request(section.reorder_url, 'PATCH', { order: section.rows.map((item) => item.id) }); section.rows = payload.rows.map((item) => ({ ...item, saving: false, toggling: false, savedName: item.name })); this.notify(payload.message); }
                catch (error) { section.rows = previousRows; this.notify(error.message, 'error'); }
                finally { section.ordering = false; }
            },
        };
    };
</script>
@endsection
