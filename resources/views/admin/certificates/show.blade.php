@extends('layouts.admin')

@section('admin-content')
<div class="page-head hero"><div><span class="admin-kicker">Certificate Details</span><h1>{{ $certificate->certificate_number }}</h1><p>Issued {{ $certificate->issued_at?->format('F j, Y H:i') }}</p></div><a class="btn secondary" href="{{ route('admin.certificates.index') }}">Back to certificates</a></div>
<div class="admin-bento">
<div class="admin-bento-card admin-bento-card--wide"><div class="admin-card-head"><span class="admin-card-icon">🎖️</span><h3>Certificate status</h3><span class="badge {{ $certificate->status->value }}" style="margin-left:auto">{{ $certificate->status->label() }}</span></div><p class="admin-card-note">This certificate is linked to one submitted exam attempt and contains {{ $certificate->documents->count() }} documents.</p><div class="admin-meta-grid"><div class="admin-meta-item"><span class="muted">Certificate number</span><strong>{{ $certificate->certificate_number }}</strong></div><div class="admin-meta-item"><span class="muted">Score</span><strong>{{ number_format((float) $certificate->score, 2) }}%</strong> <span class="muted">/ passing {{ $certificate->passing_score }}%</span></div><div class="admin-meta-item"><span class="muted">Exam attempt</span><strong>{{ $certificate->attempt?->public_id ?: '—' }}</strong></div><div class="admin-meta-item"><span class="muted">Issued date</span><strong>{{ $certificate->issued_at?->format('Y-m-d H:i') }}</strong></div><div class="admin-meta-item"><span class="muted">Valid through</span><strong>{{ $certificate->expires_at?->format('Y-m-d') ?: 'Not configured' }}</strong></div><div class="admin-meta-item"><span class="muted">Documents</span><strong>{{ $certificate->documents->count() }}</strong></div></div></div>

<div class="admin-bento-card"><div class="admin-card-head"><span class="admin-card-icon cool">🎓</span><h3>Student</h3><a style="margin-left:auto" href="{{ $certificate->student ? route('admin.users.show', $certificate->student) : '#' }}">Open user</a></div><div class="admin-meta-grid"><div class="admin-meta-item"><span class="muted">Name</span><strong>{{ $certificate->student_name }}</strong></div><div class="admin-meta-item"><span class="muted">WellSharp ID</span><strong>{{ $certificate->student_wellsharp_id }}</strong></div><div class="admin-meta-item"><span class="muted">Email</span><strong>{{ $certificate->student_email ?: '—' }}</strong></div></div></div>

<div class="admin-bento-card"><div class="admin-card-head"><span class="admin-card-icon cool">📚</span><h3>Training and assessment</h3></div><div class="admin-meta-grid"><div class="admin-meta-item"><span class="muted">Subject</span><strong>{{ $certificate->subject_name }}</strong></div><div class="admin-meta-item"><span class="muted">Exam</span><strong>{{ $certificate->exam_name }}{{ $certificate->exam_code ? ' ('.$certificate->exam_code.')' : '' }}</strong></div><div class="admin-meta-item"><span class="muted">Class</span><strong>{{ $certificate->class_number ?: '—' }}</strong></div><div class="admin-meta-item"><span class="muted">Group</span><strong>{{ $certificate->group_name ?: '—' }}</strong></div><div class="admin-meta-item"><span class="muted">Training provider</span><strong>{{ $certificate->provider_name ?: '—' }}</strong></div><div class="admin-meta-item"><span class="muted">Instructor</span><strong>{{ $certificate->instructor_name ?: '—' }}</strong></div></div></div>

@if($knowledgeResult)
<section class="admin-bento-card admin-bento-card--wide knowledge-score-control" aria-labelledby="knowledge-control-title">
  <div class="admin-card-head"><span class="admin-card-icon cool" aria-hidden="true">%</span><div><h2 id="knowledge-control-title">Knowledge Score Control</h2><p>Canonical result used by certificates, reports, dashboards, and operational views.</p></div></div>
  <div class="knowledge-score-summary">
    <div><span>Original calculated</span><strong>{{ number_format((float) $knowledgeResult['calculated_score'], 2) }}%</strong></div>
    <div><span>Question adjusted</span><strong>{{ number_format((float) $knowledgeResult['question_adjusted_score'], 2) }}%</strong></div>
    <div><span>Manual adjustments</span><strong>{{ $knowledgeResult['adjustment_total'] >= 0 ? '+' : '' }}{{ number_format($knowledgeResult['adjustment_total'], 2) }}</strong></div>
    <div><span>Final score override</span><strong>{{ $knowledgeResult['final_score_override'] === null ? 'None' : number_format($knowledgeResult['final_score_override'], 2).'%' }}</strong></div>
    <div class="primary"><span>Final Knowledge score</span><strong>{{ number_format((float) $knowledgeResult['final_knowledge_score'], 2) }}%</strong></div>
    <div><span>Passing score</span><strong>{{ $knowledgeResult['passing_score'] }}%</strong></div>
    <div><span>Final result</span><strong class="knowledge-result {{ $knowledgeResult['final_passed'] ? 'passed' : 'failed' }}">{{ $knowledgeResult['final_passed'] ? 'Passed' : 'Failed' }}</strong></div>
  </div>
  @if($knowledgeResult['active_overrides'])
    <div class="knowledge-active-controls">@foreach($knowledgeResult['active_overrides'] as $control)<span>{{ $control['type_label'] }}</span>@endforeach</div>
  @endif
  <div class="knowledge-control-actions">
    <button class="btn secondary small" type="button" onclick="document.getElementById('score-adjustment-dialog').showModal()">Add Score Adjustment</button>
    <button class="btn secondary small" type="button" onclick="document.getElementById('final-score-dialog').showModal()">Final Score Override</button>
    <button class="btn secondary small" type="button" onclick="document.getElementById('pass-fail-dialog').showModal()">Pass / Fail Override</button>
    @if($knowledgeResult['active_overrides'])<button class="btn secondary small" type="button" onclick="document.getElementById('restore-score-dialog').showModal()">Restore Calculated Result</button>@endif
    @if($knowledgeResult['certificate_eligible'] && $certificate->status === \App\Enums\CertificateStatus::Issued)<form method="POST" action="{{ route('admin.certificates.issue', $certificate) }}">@csrf<button class="btn small" type="submit">Confirm / Issue Certificate</button></form>@endif
  </div>

  @if(!$knowledgeResult['final_passed'] && $certificate->status === \App\Enums\CertificateStatus::Issued)
    <div class="knowledge-certificate-warning"><strong>Certificate action required</strong><span>The canonical result is now Failed, but the issued certificate snapshot has not been changed.</span><button class="btn small" type="button" onclick="document.getElementById('revoke-certificate-dialog').showModal()">Revoke Certificate</button></div>
  @endif

  <div class="knowledge-history"><h3>Override history</h3>
    @forelse($knowledgeResult['override_history'] as $control)
      <div class="knowledge-history-row"><div><strong>{{ $control['type_label'] }}@if($control['question_code']) · {{ $control['question_code'] }}@endif</strong><span>{{ $control['reason'] }} · {{ $control['created_by'] ?: 'Admin' }} · {{ $control['created_at'] }}</span>@if(!$control['active'])<small>Reverted {{ $control['reverted_at'] }} by {{ $control['reverted_by'] ?: 'Admin' }}: {{ $control['revert_reason'] }}</small>@endif</div>@if($control['active'])<button class="btn secondary small" type="button" onclick="document.getElementById('revert-{{ $control['id'] }}').showModal()">Revert</button>@else<span class="badge">Reverted</span>@endif</div>
      @if($control['active'])<dialog id="revert-{{ $control['id'] }}" class="knowledge-dialog"><form method="POST" action="{{ route('admin.certificates.knowledge-controls.revert', [$certificate, $control['id']]) }}">@csrf @method('PATCH')<h3>Revert {{ $control['type_label'] }}</h3><label>Reason<textarea name="reason" required minlength="3"></textarea></label><div class="actions"><button class="btn">Revert</button><button class="btn secondary" type="button" onclick="this.closest('dialog').close()">Cancel</button></div></form></dialog>@endif
    @empty<p class="muted">No score controls have been recorded.</p>@endforelse
  </div>
</section>

@foreach([['score-adjustment-dialog','score_adjustment','Add Score Adjustment','Adjustment points'],['final-score-dialog','final_score','Final Score Override','Final score']] as [$dialogId,$type,$title,$label])
<dialog id="{{ $dialogId }}" class="knowledge-dialog"><form method="POST" action="{{ route('admin.certificates.knowledge-controls.store', $certificate) }}">@csrf<input type="hidden" name="type" value="{{ $type }}"><h3>{{ $title }}</h3><label>{{ $label }}<input type="number" name="numeric_value" step="0.01" @if($type === 'final_score') min="0" max="100" @else min="-100" max="100" @endif required></label><label>Reason<textarea name="reason" required minlength="3"></textarea></label><div class="actions"><button class="btn">Save</button><button class="btn secondary" type="button" onclick="this.closest('dialog').close()">Cancel</button></div></form></dialog>
@endforeach
<dialog id="pass-fail-dialog" class="knowledge-dialog"><form method="POST" action="{{ route('admin.certificates.knowledge-controls.store', $certificate) }}">@csrf<input type="hidden" name="type" value="pass_fail"><h3>Pass / Fail Override</h3><label>Final decision<select name="boolean_value" required><option value="1">Pass</option><option value="0">Fail</option></select></label><label>Reason<textarea name="reason" required minlength="3"></textarea></label><div class="actions"><button class="btn">Save privileged override</button><button class="btn secondary" type="button" onclick="this.closest('dialog').close()">Cancel</button></div></form></dialog>
<dialog id="restore-score-dialog" class="knowledge-dialog"><form method="POST" action="{{ route('admin.certificates.knowledge-controls.restore', $certificate) }}">@csrf<h3>Restore Calculated Result</h3><label>Reason<textarea name="reason" required minlength="3"></textarea></label><div class="actions"><button class="btn">Restore all</button><button class="btn secondary" type="button" onclick="this.closest('dialog').close()">Cancel</button></div></form></dialog>
<dialog id="revoke-certificate-dialog" class="knowledge-dialog"><form method="POST" action="{{ route('admin.certificates.revoke', $certificate) }}">@csrf<h3>Revoke Certificate</h3><p>This is separate from the score change and will mark the issued certificate as revoked.</p><label>Reason<textarea name="reason" required minlength="3"></textarea></label><div class="actions"><button class="btn">Revoke Certificate</button><button class="btn secondary" type="button" onclick="this.closest('dialog').close()">Cancel</button></div></form></dialog>
@endif

<section class="admin-bento-card admin-bento-card--wide certificate-exam-review" aria-labelledby="exam-review-title">
  <div class="admin-card-head">
    <span class="admin-card-icon cool" aria-hidden="true">✓</span>
    <div><h2 id="exam-review-title">Exam Review</h2><p>{{ count($examReview) }} {{ \Illuminate\Support\Str::plural('question', count($examReview)) }} in attempt order</p></div>
  </div>
  <p class="certificate-review-history-note">Question content and correct answers reflect the current question bank and are not historical snapshots.</p>

  @if($examReview !== [])
    <div class="certificate-review-list">
      @foreach($examReview as $review)
        @php
          $state = ! $review['answered'] ? 'unanswered' : ($review['is_correct'] ? 'correct' : 'incorrect');
          $stateLabel = str($state)->headline();
        @endphp
        <article class="certificate-review-card is-{{ $state }}">
          <header class="certificate-review-card-head">
            <div class="certificate-review-identifiers">
              <span class="certificate-review-number">Question {{ $review['display_order'] }}</span>
              <strong>{{ $review['code'] ?: 'No question code' }}</strong>
            </div>
            <span class="certificate-review-state is-{{ $state }}">{{ $stateLabel }}</span>
          </header>

          <div class="certificate-review-tags">
            <span>{{ $review['type_label'] ?: str($review['type'])->headline() }}</span>
            <span>{{ $review['difficulty_label'] ?: 'Difficulty not set' }}</span>
          </div>

          <p class="certificate-review-question">{{ $review['question_text'] }}</p>
          @if($review['question_image_url'])
            <img class="certificate-review-image" src="{{ $review['question_image_url'] }}" alt="Image for question {{ $review['display_order'] }}">
          @endif

          <div class="certificate-review-answers">
            <div class="certificate-review-answer student-answer">
              <span>Student answer</span>
              <strong>{{ $review['answered'] ? ($review['answer'] ?: 'Answer unavailable') : 'Unanswered' }}</strong>
              @if($review['answer_image_url'])<img src="{{ $review['answer_image_url'] }}" alt="Student answer image">@endif
            </div>
            <div class="certificate-review-answer correct-answer">
              <span>Correct answer</span>
              <strong>{{ $review['correct_answer'] ?: 'Not configured' }}</strong>
              @if($review['correct_answer_image_url'])<img src="{{ $review['correct_answer_image_url'] }}" alt="Correct answer image">@endif
            </div>
          </div>

          @if(filled($review['solution_text']))
            <div class="certificate-review-solution"><span>Solution</span><p>{{ $review['solution_text'] }}</p></div>
          @endif

          <footer class="certificate-review-points">
            <span>Points earned</span>
            <strong>{{ number_format((float) $review['awarded_points'], 2) }} / {{ number_format((float) $review['points'], 2) }}</strong>
            <button class="btn secondary small" type="button" onclick="document.getElementById('question-control-{{ $review['id'] }}').showModal()">Override points</button>
          </footer>
          <dialog id="question-control-{{ $review['id'] }}" class="knowledge-dialog"><form method="POST" action="{{ route('admin.certificates.knowledge-controls.store', $certificate) }}">@csrf<input type="hidden" name="type" value="question_points"><input type="hidden" name="exam_attempt_question_id" value="{{ $review['id'] }}"><h3>Override {{ $review['code'] ?: 'Question '.$review['display_order'] }} points</h3><label>Awarded points<input type="number" name="numeric_value" min="0" max="{{ $review['points'] }}" step="0.01" value="{{ $review['awarded_points'] }}" required></label><label>Reason<textarea name="reason" required minlength="3"></textarea></label><div class="actions"><button class="btn">Save</button><button class="btn secondary" type="button" onclick="this.closest('dialog').close()">Cancel</button></div></form></dialog>
        </article>
      @endforeach
    </div>
  @else
    <div class="admin-empty-row"><strong>No attempt questions available</strong><span>This certificate has no question review data.</span></div>
  @endif
</section>

<div class="admin-bento-card admin-bento-card--wide certificate-documents-admin-card">
  <div class="admin-card-head"><span class="admin-card-icon">📄</span><h3>Certificate documents</h3><a class="btn secondary" style="margin-left:auto" href="{{ route('certificates.show', $certificate) }}">Open bundle</a></div>
  <p class="admin-card-note">Open a document to view it, or download an individual PDF.</p>
  <div class="certificate-documents-admin-list">
    @foreach($certificate->documents as $document)
      <div class="certificate-admin-document-row">
        <a class="certificate-admin-document-link" href="{{ route('certificates.documents.show', [$certificate, $document]) }}">
          <span class="certificate-admin-document-icon" aria-hidden="true">PDF</span>
          <span><strong>{{ $document->title }}</strong><small>Issued {{ $document->issued_at?->format('Y-m-d H:i') }}</small></span>
          <span class="certificate-admin-document-view">View <span aria-hidden="true">↗</span></span>
        </a>
        <a class="btn secondary small" href="{{ route('certificates.documents.download', [$certificate, $document]) }}">Download PDF</a>
      </div>
    @endforeach
  </div>
</div>
</div>
@endsection
