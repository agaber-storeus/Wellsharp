@extends('layouts.admin')
@section('admin-content')
<style>
.badge.info{background:var(--admin-accent-cool-soft);color:var(--admin-accent-cool)}
.badge.warning{background:var(--admin-warning-soft);color:var(--admin-warning)}
.badge.verified_control_failed{background:var(--admin-danger-soft);color:var(--admin-danger)}
.system-log-json{background:var(--admin-surface-soft);border:1px solid var(--admin-line);border-radius:var(--admin-radius-sm);padding:12px 14px;font-family:'JetBrains Mono',monospace;font-size:12.5px;white-space:pre-wrap;overflow-wrap:anywhere;max-height:420px;overflow-y:auto}
.system-log-mono{font-family:'JetBrains Mono',monospace;font-size:12.5px;overflow-wrap:anywhere}
.proctor-activity-badge{position:relative;display:inline-flex;align-items:center;gap:7px;width:max-content;max-width:100%;padding:6px 10px;border:1px solid rgba(31,131,185,.35);border-radius:999px;background:linear-gradient(115deg,rgba(236,248,255,.95),rgba(255,248,239,.95),rgba(232,247,238,.95));background-size:220% 220%;color:#123e61;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;box-shadow:0 0 0 1px rgba(255,255,255,.7) inset,0 6px 18px rgba(23,109,159,.12);overflow:hidden}
.proctor-activity-badge::before{content:"";position:absolute;inset:-60%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.58),transparent);transform:translateX(-35%) rotate(12deg);animation:proctorBadgeShine 5.5s ease-in-out infinite}
.proctor-activity-badge svg{position:relative;width:14px;height:14px;flex:0 0 auto}
.proctor-activity-badge span{position:relative}
.proctor-status{display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
.proctor-status::before{content:"";width:9px;height:9px;border-radius:50%;background:var(--admin-success);box-shadow:0 0 0 3px var(--admin-success-soft)}
.proctor-status.failed::before,.proctor-status.verified_control_failed::before{background:var(--admin-danger);box-shadow:0 0 0 3px var(--admin-danger-soft)}
@keyframes proctorBadgeShine{0%,65%,100%{transform:translateX(-55%) rotate(12deg)}82%{transform:translateX(55%) rotate(12deg)}}
@media(prefers-reduced-motion:reduce){.proctor-activity-badge::before{animation:none}}
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

    @if($entry['proctor_activity'])
        @php
            $activity = $entry['proctor_activity'];
            $failureReason = isset($activity['failure_reason'])
                ? (\App\Enums\ProctorVerificationFailureReason::tryFrom($activity['failure_reason']) ?? \App\Enums\ClassControlFailureReason::tryFrom($activity['failure_reason']))
                : null;
        @endphp
        <div class="admin-bento-card admin-bento-card--wide">
            <div class="admin-card-head">
                <span class="proctor-activity-badge"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2.8 16 5v4.6c0 3.7-2.4 6.4-6 7.6-3.6-1.2-6-3.9-6-7.6V5l6-2.2Z"/><path d="M8.4 10.4 10 12l3-3"/></svg><span>Proctor ID Activity</span></span>
                <span class="badge {{ $activity['status'] }}" style="margin-left:auto">{{ strtoupper($activity['status_label']) }}</span>
            </div>
            <div class="admin-meta-grid">
                <div class="admin-meta-item"><span class="muted">Entered Proctor ID</span><strong class="system-log-mono">{{ $activity['entered_proctor_id'] ?: '—' }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Operation</span><strong>{{ isset($activity['operation']) ? \Illuminate\Support\Str::headline($activity['operation']) : '—' }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Verification</span><strong>{{ \Illuminate\Support\Str::headline($entry['after_state']['verification_status'] ?? '—') }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Control</span><strong>{{ \Illuminate\Support\Str::headline($activity['control_status'] ?? '—') }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Class</span><strong>{{ $activity['class_number'] ?: ($entry['subject_detail'] ?? $entry['subject'] ?? '—') }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Class public ID</span><strong class="system-log-mono">{{ $activity['class_public_id'] ?: '—' }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Verified Proctor</span><strong>{{ $activity['verified_proctor_display_name'] ?: '—' }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Verified Proctor user ID</span><strong>{{ $activity['verified_proctor_user_id'] ?: '—' }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Verified WellSharp ID</span><strong>{{ $activity['verified_proctor_wellsharp_id'] ?: '—' }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Failure stage</span><strong>{{ isset($activity['failure_stage']) ? \Illuminate\Support\Str::headline($activity['failure_stage']) : '—' }}</strong></div>
                <div class="admin-meta-item"><span class="muted">Failure reason</span><strong>{{ $failureReason?->label() ?: ($activity['failure_reason'] ?? '—') }}</strong></div>
            </div>
        </div>
    @elseif(in_array($entry['action'], ['class.proctor_verification.succeeded', 'class.proctor_verification.failed', 'class.control_attempt.failed'], true))
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
