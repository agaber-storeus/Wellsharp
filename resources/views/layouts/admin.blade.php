<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive"><meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="same-origin">
    <meta name="theme-color" content="#0b1728">
    <title>{{ $title ?? 'Administration | WellSharp' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}"><link rel="stylesheet" href="{{ asset('css/admin-sidebar.css') }}?v={{ filemtime(public_path('css/admin-sidebar.css')) }}"><link rel="stylesheet" href="{{ asset('css/admin-modern.css') }}?v={{ filemtime(public_path('css/admin-modern.css')) }}"><link rel="stylesheet" href="{{ asset('css/admin-layout-fix.css') }}?v={{ filemtime(public_path('css/admin-layout-fix.css')) }}"><link rel="stylesheet" href="{{ asset('css/alerts.css') }}?v={{ filemtime(public_path('css/alerts.css')) }}">
    @if(request()->routeIs('admin.dashboard'))<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}?v={{ filemtime(public_path('css/admin-dashboard.css')) }}">@endif
    @if(request()->routeIs('certificates.*', 'admin.certificates.*'))
        @if(!auth()->user()->isAdmin())<link rel="stylesheet" href="{{ asset('css/proctor.css') }}?v={{ filemtime(public_path('css/proctor.css')) }}">@endif
        <link rel="stylesheet" href="{{ asset('css/certificate-documents.css') }}?v={{ filemtime(public_path('css/certificate-documents.css')) }}">
    @endif
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    @if(request()->routeIs('admin.questions.create', 'admin.courses.questions.create', 'admin.courses.questions.edit'))<link rel="stylesheet" href="{{ asset('css/admin-question-wizard.css') }}">@endif
    @if(request()->routeIs('admin.providers.create', 'admin.providers.edit', 'admin.providers.show'))<link rel="stylesheet" href="{{ asset('css/provider-location-picker.css') }}">@vite('resources/js/maps.js')@endif
    {{-- Always last: the global design-system layer overrides tokens/typography/badges/etc. declared by every stylesheet above, including the page-specific ones loaded conditionally just above. --}}
    <link rel="stylesheet" href="{{ asset('css/admin-system.css') }}?v={{ filemtime(public_path('css/admin-system.css')) }}">
</head>
<body class="admin-body" x-data="{ sidebarOpen: false, sidebarCollapsed: localStorage.getItem('wellsharp.admin.sidebarCollapsed') === '1', toggleSidebar() { this.sidebarCollapsed = ! this.sidebarCollapsed; localStorage.setItem('wellsharp.admin.sidebarCollapsed', this.sidebarCollapsed ? '1' : '0'); } }" x-on:keydown.escape.window="sidebarOpen = false">
<div class="admin-shell" x-bind:class="{ 'sidebar-collapsed': sidebarCollapsed }">
<aside class="admin-sidebar" id="admin-sidebar" aria-label="Administration navigation" x-bind:class="{ open: sidebarOpen }"><div class="admin-brand"><img src="{{ asset('images/ws-login_SG-Logo.png') }}" alt="WellSharp" width="150" height="42"><span>Administration</span><button class="admin-sidebar-collapse" type="button" x-on:click="toggleSidebar()" x-bind:aria-expanded="(! sidebarCollapsed).toString()" aria-label="Collapse administration sidebar" title="Collapse sidebar"><span aria-hidden="true">&lsaquo;</span><span class="collapse-label">Collapse</span></button></div>
<nav class="admin-nav" aria-label="Administration navigation">
    <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.3 10 3.2l7 6.1"/><path d="M4.6 8.4V16a1 1 0 0 0 1 1H8v-4.2h4V17h2.4a1 1 0 0 0 1-1V8.4"/></svg></span><span class="nav-label">Dashboard</span></a>
    @php($studentManagement = request()->routeIs('admin.groups.*', 'admin.students.*'))
    <div class="admin-nav-group" x-data="{ open: @js($studentManagement) }" x-bind:class="{ open }"><button class="admin-nav-category" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()"><span class="nav-category-label"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.3" cy="6.6" r="2.6"/><path d="M2.6 16.4c0-2.8 2-4.5 4.7-4.5s4.7 1.7 4.7 4.5"/><circle cx="14.6" cy="7.4" r="1.9"/><path d="M12.9 11.7c1.8-.4 3.8 0 4.4 2.2.2.7.3 1.5.3 2.5"/></svg></span><span class="nav-label">Student Management</span></span><span class="nav-chevron">⌄</span></button><div class="admin-subnav"><a class="{{ request()->routeIs('admin.groups.*') ? 'active' : '' }}" href="{{ route('admin.groups.index') }}">Groups</a><a class="{{ request()->routeIs('admin.students.*') ? 'active' : '' }}" href="{{ route('admin.students.index') }}">Students</a></div></div>
    @php($examManagement = request()->routeIs('admin.exams.*', 'admin.courses.exams.*', 'admin.exam-schedules.*', 'admin.classes.*'))
    <div class="admin-nav-group" x-data="{ open: @js($examManagement) }" x-bind:class="{ open }"><button class="admin-nav-category" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()"><span class="nav-category-label"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3.6" width="12" height="13.8" rx="1.6"/><path d="M7.4 3.3h5.2a1 1 0 0 1 1 1v1H6.4v-1a1 1 0 0 1 1-1Z"/><path d="M6.8 10.8l1.9 1.9L12.6 8"/></svg></span><span class="nav-label">Exam Management</span></span><span class="nav-chevron">⌄</span></button><div class="admin-subnav"><a class="{{ request()->routeIs('admin.exams.*', 'admin.courses.exams.*', 'admin.classes.*') ? 'active' : '' }}" href="{{ route('admin.exams.index') }}">Exams</a><a class="{{ request()->routeIs('admin.exam-schedules.*') ? 'active' : '' }}" href="{{ route('admin.exam-schedules.index') }}">Exam Schedules</a></div></div>
    @php($certificateManagement = request()->routeIs('admin.certificates.*'))
    <div class="admin-nav-group" x-data="{ open: @js($certificateManagement) }" x-bind:class="{ open }"><button class="admin-nav-category" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()"><span class="nav-category-label"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7.6" r="4.2"/><path d="M7.4 11.3 6.1 17l3.9-2 3.9 2-1.3-5.7"/></svg></span><span class="nav-label">Certificate Management</span></span><span class="nav-chevron">⌄</span></button><div class="admin-subnav"><a class="{{ $certificateManagement ? 'active' : '' }}" href="{{ route('admin.certificates.index') }}">Certificates</a></div></div>
    @php($subjectManagement = request()->routeIs('admin.subjects.*', 'admin.courses.*', 'admin.questions.*'))
    <div class="admin-nav-group" x-data="{ open: @js($subjectManagement) }" x-bind:class="{ open }"><button class="admin-nav-category" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()"><span class="nav-category-label"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 5.4C8.5 4.1 6.3 3.6 3.5 3.8v10.7c2.8-.2 5 .3 6.5 1.6 1.5-1.3 3.7-1.8 6.5-1.6V3.8c-2.8-.2-5 .3-6.5 1.6Z"/><path d="M10 5.4v10.7"/></svg></span><span class="nav-label">Subject Management</span></span><span class="nav-chevron">⌄</span></button><div class="admin-subnav"><a class="{{ $subjectManagement && ! request()->routeIs('admin.questions.*', 'admin.courses.questions.*') ? 'active' : '' }}" href="{{ route('admin.subjects.index') }}">Subjects</a><a class="{{ request()->routeIs('admin.questions.*', 'admin.courses.questions.*') ? 'active' : '' }}" href="{{ route('admin.questions.index') }}">Questions</a></div></div>
    @php($settings = request()->routeIs('admin.users.*', 'admin.providers.*', 'admin.configuration.*'))
    <div class="admin-nav-group" x-data="{ open: @js($settings) }" x-bind:class="{ open }"><button class="admin-nav-category" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()"><span class="nav-category-label"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="2.5"/><path d="M10 3.4v1.7M10 14.9v1.7M16.6 10h-1.7M5.1 10H3.4M14.7 5.3l-1.2 1.2M6.5 13.5l-1.2 1.2M14.7 14.7l-1.2-1.2M6.5 6.5 5.3 5.3"/></svg></span><span class="nav-label">Settings</span></span><span class="nav-chevron">⌄</span></button><div class="admin-subnav"><a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a><a class="{{ request()->routeIs('admin.providers.*') ? 'active' : '' }}" href="{{ route('admin.providers.index') }}">Training Providers</a><a class="{{ request()->routeIs('admin.configuration.*') ? 'active' : '' }}" href="{{ route('admin.configuration.index') }}">Subject Configuration</a></div></div>
</nav><div class="admin-sidebar-footer">System administration<br><span>WellSharp workspace</span></div></aside>
<button class="admin-drawer-backdrop" type="button" aria-label="Close administration navigation" x-cloak x-show="sidebarOpen" x-on:click="sidebarOpen = false"></button>
<div class="admin-main"><header class="admin-header"><button class="admin-menu-toggle" type="button" aria-controls="admin-sidebar" x-on:click="sidebarOpen = ! sidebarOpen" x-bind:aria-expanded="sidebarOpen.toString()">☰ <span class="sr-only">Toggle navigation</span></button><div class="admin-header-context"><span class="eyebrow">WellSharp platform</span><strong>Administration console</strong></div><div class="admin-user"><span class="admin-avatar">{{ str(auth()->user()->display_name)->substr(0, 1)->upper() }}</span><span>{{ auth()->user()->display_name }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form></div></header><main class="admin-content">@if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif @if($errors->any())<div class="alert error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif @yield('admin-content')</main></div>
</div>
<script>
    window.studentGroupPicker = function (initialGroups, endpoint) {
        return {
            query: '', results: [], selected: initialGroups || [], loading: false, error: '', timer: null,
            searchGroups() {
                clearTimeout(this.timer); const term = this.query.trim(); this.error = '';
                if (term.length < 2) { this.results = []; this.loading = false; return; }
                this.timer = setTimeout(async () => {
                    this.loading = true;
                    try { const response = await fetch(endpoint + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' } }); if (!response.ok) throw new Error('Search failed'); this.results = await response.json(); }
                    catch (error) { this.results = []; this.error = 'Groups could not be loaded. Try again.'; }
                    finally { this.loading = false; }
                }, 250);
            },
            isSelected(id) { return this.selected.some(group => Number(group.id) === Number(id)); },
            select(group) { if (!this.isSelected(group.id)) this.selected.push(group); },
            remove(id) { this.selected = this.selected.filter(group => Number(group.id) !== Number(id)); }
        };
    };
</script>
<script src="{{ asset('js/auto-dismiss-alerts.js') }}?v={{ filemtime(public_path('js/auto-dismiss-alerts.js')) }}"></script>
@if(request()->routeIs('admin.providers.create', 'admin.providers.edit'))<script src="{{ asset('js/provider-location-picker.js') }}"></script>@endif
@if(request()->routeIs('admin.providers.show'))<script src="{{ asset('js/provider-location-map.js') }}"></script>@endif
@if(request()->routeIs('admin.questions.create', 'admin.courses.questions.create', 'admin.courses.questions.edit'))<script src="{{ asset('js/admin-question-wizard.js') }}"></script>@endif
<script src="{{ asset('js/file-upload-inputs.js') }}"></script>
@stack('scripts')
</body></html>
