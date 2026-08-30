@extends('layouts.admin')
@section('admin-content')
<style>
.badge.info{background:var(--admin-accent-cool-soft);color:var(--admin-accent-cool)}
.badge.warning{background:var(--admin-warning-soft);color:var(--admin-warning)}
.system-log-json{background:var(--admin-surface-soft);border:1px solid var(--admin-line);border-radius:var(--admin-radius-sm);padding:12px 14px;font-family:'JetBrains Mono',monospace;font-size:12.5px;white-space:pre-wrap;overflow-wrap:anywhere;max-height:420px;overflow-y:auto}
.system-log-mono{font-family:'JetBrains Mono',monospace;font-size:12.5px;overflow-wrap:anywhere}
</style>
<div class="page-head hero"><div><span class="admin-kicker">{{ $entry['category_label'] }}</span><h1>{{ $entry['label'] }}</h1><p>{{ $entry['occurred_at']->format('F j, Y g:i A') }}</p></div><a class="btn secondary" href="{{ route('admin.system-logs.index') }}">Back to System Logs</a></div>
<div class="admin-bento">
    <div class="admin-bento-card admin-bento-card--wide">
        <div class="admin-card-head"><span class="admin-card-icon">🛡️</span><h3>Event summary</h3><span class="badge {{ $entry['severity'] }}" style="margin-left:auto">{{ $entry['result'] ? ucfirst($entry['result']) : '—' }}</span></div>
        <div class="admin-meta-grid">
            <div class="admin-meta-item"><span class="muted">Event</span><strong>{{ $entry['label'] }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Category</span><strong>{{ $entry['category_label'] }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Timestamp</span><strong>{{ $entry['occurred_at']->format('Y-m-d H:i:s') }} UTC</strong></div>
            <div class="admin-meta-item"><span class="muted">Actor</span><strong>{{ $entry['actor'] }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Actor role</span><strong>{{ $entry['actor_role'] ?: '—' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Subject</span><strong>{{ $entry['subject_detail'] ?? $entry['subject'] ?? '—' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Reason</span><strong>{{ $entry['reason'] ?: '—' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">Correlation ID</span><strong class="system-log-mono">{{ $entry['correlation_id'] ?: '—' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">IP address</span><strong>{{ $entry['ip_address'] ?: '—' }}</strong></div>
            <div class="admin-meta-item"><span class="muted">User agent</span><strong class="system-log-mono">{{ $entry['user_agent'] ?: '—' }}</strong></div>
        </div>
    </div>

    @if(in_array($entry['action'], ['class.proctor_verification.succeeded', 'class.proctor_verification.failed', 'class.control_attempt.failed'], true))
        @php
            $context = $entry['after_state'] ?? [];
            $verifiedProctor = isset($context['verified_proctor_user_id']) ? \App\Models\User::find($context['verified_proctor_user_id']) : null;
            $failureReason = isset($context['failure_reason'])
                ? (\App\Enums\ProctorVerificationFailureReason::tryFrom($context['failure_reason']) ?? \App\Enums\ClassControlFailureReason::tryFrom($context['failure_reason']))
                : null;
            $isSuccess = $entry['action'] === 'class.proctor_verification.succeeded';
        @endphp
        <div class="admin-bento-card admin-bento-card--wide">
            <div class="admin-card-head"><span class="admin-card-icon">🪪</span><h3>Class control attempt</h3><span class="badge {{ $entry['severity'] }}" style="margin-left:auto">{{ $isSuccess ? 'Succeeded' : 'Failed' }}</span></div>
            <div class="admin-meta-grid">
                <div class="admin-meta-item"><span class="muted">Requested operation</span><strong>{{ isset($context['operation']) ? ucfirst($context['operation']).' Class' : '—' }}</strong></div>
                @if($isSuccess)
                    <div class="admin-meta-item"><span class="muted">Verified Proctor</span><strong>{{ $verifiedProctor?->display_name ?: 'Unavailable' }}</strong></div>
                    <div class="admin-meta-item"><span class="muted">Proctor WellSharp ID</span><strong>{{ $context['verified_proctor_wellsharp_id'] ?? '—' }}</strong></div>
                @else
                    <div class="admin-meta-item"><span class="muted">Failure stage</span><strong>{{ isset($context['failure_stage']) ? \Illuminate\Support\Str::headline($context['failure_stage']) : '—' }}</strong></div>
                    <div class="admin-meta-item"><span class="muted">Failure reason</span><strong>{{ $failureReason?->label() ?: ($context['failure_reason'] ?? '—') }}</strong></div>
                @endif
            </div>
            @if(in_array($context['failure_stage'] ?? null, ['validation', 'verification'], true))
                <p class="admin-card-note">The Proctor's ID entered by the Instructor is never stored here - only whether it matched an active, eligible Proctor.</p>
            @endif
        </div>
    @endif

    @if(!is_null($entry['before_state']))
    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon cool">⬅️</span><h3>Before state</h3></div>
        <div class="system-log-json">{{ json_encode($entry['before_state'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
    </div>
    @endif

    @if(!is_null($entry['after_state']))
    <div class="admin-bento-card">
        <div class="admin-card-head"><span class="admin-card-icon cool">➡️</span><h3>After state</h3></div>
        <div class="system-log-json">{{ json_encode($entry['after_state'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
    </div>
    @endif
</div>
@endsection
