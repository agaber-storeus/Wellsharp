@extends('layouts.admin')

@section('admin-content')
<div class="page-head"><div><span class="admin-kicker">Settings</span><h1>Exam Translation Languages</h1><p>Control which languages Students may use to take Exams. Question and Option text is translated once per language and reused across every Exam that shares the Question.</p></div></div>

<div class="card" x-data="examLanguages(@js($languages), @js(route('admin.settings.exam-languages.sync')), @js(route('admin.settings.exam-languages.update')))">
    <div class="admin-section-head">
        <div>
            <h2>Translation Provider</h2>
            <p class="muted">{{ ucfirst($providerName) }} &middot; Source language: {{ strtoupper($sourceLanguage) }}</p>
        </div>
        <span class="badge" x-bind:class="providerAvailable ? 'active' : 'archived'" x-text="providerAvailable ? 'Connected' : 'Unavailable'">{{ $providerAvailable ? 'Connected' : 'Unavailable' }}</span>
    </div>

    <div class="alert" x-show="notice" x-bind:class="noticeType === 'error' ? 'error' : 'success'" x-text="notice" x-cloak role="status"></div>

    <div class="exam-languages-toolbar">
        <input type="search" x-model="search" placeholder="Search languages...">
        <span class="muted">Enabled: <strong x-text="enabledCount"></strong></span>
        <span class="muted">Last synchronized: <span x-text="lastSyncedLabel"></span></span>
        <div class="exam-languages-actions">
            <button class="btn secondary" type="button" x-on:click="sync()" x-bind:disabled="syncing" x-text="syncing ? 'Syncing...' : 'Sync Languages'"></button>
            <button class="btn" type="button" x-on:click="save()" x-bind:disabled="saving || !dirty" x-text="saving ? 'Saving...' : 'Save Changes'"></button>
        </div>
    </div>

    <div class="table-wrap"><table class="table"><thead><tr><th></th><th>Language</th><th>Code</th><th>Direction</th></tr></thead><tbody>
        <template x-for="language in filteredLanguages" :key="language.id">
            <tr>
                <td><input type="checkbox" x-model="enabled[language.id]" x-on:change="dirty = true" x-bind:aria-label="'Enable ' + language.name"></td>
                <td>{{-- --}}<span x-text="language.name"></span> <small class="muted" x-show="language.native_name" x-text="language.native_name"></small></td>
                <td><code x-text="language.code"></code></td>
                <td><span class="badge" x-bind:class="language.direction === 'rtl' ? 'archived' : 'active'" x-text="language.direction.toUpperCase()"></span></td>
            </tr>
        </template>
        <tr x-show="filteredLanguages.length === 0" x-cloak><td colspan="4"><div class="admin-empty-row"><span class="admin-empty-row-icon">🌐</span>No languages found. Sync the language catalog from the provider first.</div></td></tr>
    </tbody></table></div>
</div>

<style>
    .exam-languages-toolbar{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin:12px 0 16px}
    .exam-languages-toolbar input[type=search]{flex:1;min-width:200px;border:1px solid #b9c7d2;border-radius:5px;padding:9px 10px;background:#fff;color:var(--admin-ink);font:inherit}
    .exam-languages-actions{display:flex;gap:10px;margin-left:auto}
</style>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('examLanguages', (initialLanguages, syncUrl, saveUrl) => ({
            languages: initialLanguages || [],
            enabled: Object.fromEntries((initialLanguages || []).map((l) => [l.id, l.enabled])),
            search: '',
            dirty: false,
            syncing: false,
            saving: false,
            providerAvailable: @js($providerAvailable),
            lastSyncedAt: @js($lastSyncedAt?->toIso8601String()),
            notice: '',
            noticeType: 'success',
            notify(message, type = 'success') { this.notice = message; this.noticeType = type; window.clearTimeout(this.noticeTimer); this.noticeTimer = window.setTimeout(() => { this.notice = ''; }, 10000); },
            get filteredLanguages() {
                const term = this.search.trim().toLowerCase();
                if (!term) return this.languages;
                return this.languages.filter((l) => l.name.toLowerCase().includes(term) || l.code.toLowerCase().includes(term) || (l.native_name || '').toLowerCase().includes(term));
            },
            get enabledCount() { return Object.values(this.enabled).filter(Boolean).length; },
            get lastSyncedLabel() { return this.lastSyncedAt ? new Date(this.lastSyncedAt).toLocaleString() : 'Never'; },
            applyRows(rows) {
                this.languages = rows;
                this.enabled = Object.fromEntries(rows.map((l) => [l.id, l.enabled]));
                this.dirty = false;
                const latest = rows.map((l) => l.last_synced_at).filter(Boolean).sort().pop();
                if (latest) this.lastSyncedAt = latest;
            },
            async request(url, method, body) {
                const controller = new AbortController();
                const timeout = window.setTimeout(() => controller.abort(), 15000);
                try {
                    const response = await fetch(url, { method, credentials: 'same-origin', signal: controller.signal, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()), 'X-Requested-With': 'XMLHttpRequest' }, body: body ? JSON.stringify(body) : undefined });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(payload.message || 'The request could not be completed.');
                    return payload;
                } catch (error) {
                    if (error.name === 'AbortError') throw new Error('The request timed out. Please try again.');
                    throw error;
                } finally {
                    window.clearTimeout(timeout);
                }
            },
            async sync() {
                this.syncing = true;
                try {
                    const payload = await this.request(syncUrl, 'POST');
                    this.applyRows(payload.rows);
                    this.providerAvailable = true;
                    this.notify(payload.message);
                } catch (error) {
                    this.providerAvailable = false;
                    this.notify(error.message, 'error');
                } finally {
                    this.syncing = false;
                }
            },
            async save() {
                this.saving = true;
                try {
                    const enabledIds = Object.entries(this.enabled).filter(([, v]) => v).map(([id]) => Number(id));
                    const payload = await this.request(saveUrl, 'PATCH', { enabled_ids: enabledIds });
                    this.applyRows(payload.rows);
                    this.notify(payload.message);
                } catch (error) {
                    this.notify(error.message, 'error');
                } finally {
                    this.saving = false;
                }
            },
        }));
    });
</script>
@endsection
