@extends('layouts.student')

@section('student-content')
@php
  $answerEndpoint = route('student.attempts.answers.update', [$attempt, 'QUESTION']);
  $totalQuestions = $attempt->attemptQuestions->count();
@endphp
<div class="overlay exam-overlay"></div>
<section class="exam-modal" x-data="studentExam(@js($initialAnswers), @js($answerEndpoint), @js(route('student.attempts.expire', $attempt)), {{ $totalQuestions }}, @js($attempt->expires_at?->toIso8601String()))">
  <header class="exam-modal-head">
    <img src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" />
    <div class="exam-status">
      <strong>{{ auth()->user()->display_name }}</strong><br />
      <span x-text="timerLabel"></span><br />
      <span x-text="answeredCount + '/' + totalQuestions + ' questions answered'"></span><br />
      <span class="exam-expired-message" x-show="errors._timer" x-text="errors._timer" x-cloak></span>
      <form method="POST" action="{{ route('student.attempts.submit', $attempt) }}">
        @csrf
        <button class="green-btn" type="submit" x-show="!expired" x-bind:disabled="expired" x-cloak>Submit Exam</button>
      </form>
      <span class="exam-expired-message" x-show="expired" x-cloak>Time expired. Your attempt is closed.</span>
      <a class="green-btn small" href="{{ route('student.dashboard') }}" x-show="expired" x-cloak>Return to Dashboard</a>
    </div>
  </header>
  <div class="exam-content">
    @forelse($attempt->attemptQuestions as $attemptQuestion)
      @php($question = $attemptQuestion->question)
      @php($questionKey = (string) $attemptQuestion->getKey())
      <section class="exam-question" data-question-id="{{ $questionKey }}">
        <div class="question-line">
          <span class="question-number">{{ $attemptQuestion->display_order }}.</span>
          <div>
            <h2>{{ $question->question_text }}</h2>
            @if($question->question_image_path)
              <img class="exam-question-image" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($question->question_image_path) }}" alt="Question image" />
            @endif
            <small>{{ $question->code }}</small>
          </div>
        </div>
        @if($question->type === \App\Enums\QuestionType::Mcq)
          <div class="answer-list compact">
            @foreach($attemptQuestion->orderedOptions() as $option)
              <label x-bind:class="expired ? 'is-disabled' : ''">
                <input type="radio" name="question-{{ $questionKey }}" value="{{ $option->public_id }}" x-model="answers['{{ $questionKey }}']" x-on:change="save('{{ $questionKey }}', $event.target.value)" x-bind:disabled="expired" />
                <span>{{ $option->option_text }}</span>
                @if($option->image_path)
                  <img class="exam-answer-image" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($option->image_path) }}" alt="Answer option image" />
                @endif
              </label>
            @endforeach
          </div>
        @elseif($question->type === \App\Enums\QuestionType::TrueFalse)
          <div class="answer-list compact">
              <label><input type="radio" name="question-{{ $questionKey }}" value="true" x-model="answers['{{ $questionKey }}']" x-on:change="save('{{ $questionKey }}', $event.target.value)" x-bind:disabled="expired" /> True</label>
            <label><input type="radio" name="question-{{ $questionKey }}" value="false" x-model="answers['{{ $questionKey }}']" x-on:change="save('{{ $questionKey }}', $event.target.value)" x-bind:disabled="expired" /> False</label>
          </div>
        @else
          <div class="answer-list compact">
            <input type="text" class="exam-text-answer" value="{{ $attemptQuestion->answer }}" x-model="answers['{{ $questionKey }}']" x-on:input.debounce.400ms="save('{{ $questionKey }}', $event.target.value)" x-bind:disabled="expired" aria-label="Answer for question {{ $attemptQuestion->display_order }}" />
          </div>
        @endif
        <small class="answer-save-state" x-cloak x-show="errors['{{ $questionKey }}']" x-text="errors['{{ $questionKey }}']"></small>
      </section>
    @empty
      <section class="exam-question"><h2>No questions are available for this attempt.</h2></section>
    @endforelse
  </div>
</section>
@endsection

@push('scripts')
<script>
window.studentExam = (initialAnswers, answerEndpoint, expireEndpoint, totalQuestions, expiresAt) => ({
    answers: initialAnswers || {},
    saving: {},
    saved: {},
    errors: {},
    controllers: {},
    totalQuestions,
    expireEndpoint,
    secondsLeft: expiresAt ? Math.max(0, Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000)) : null,
    expired: false,
    expiring: false,
    timer: null,
    init() {
      if (this.secondsLeft !== null) {
        if (this.secondsLeft === 0) {
          this.expire();
          return;
        }
        this.timer = window.setInterval(() => {
          this.secondsLeft = Math.max(0, this.secondsLeft - 1);
          if (this.secondsLeft === 0) {
            window.clearInterval(this.timer);
            this.expire();
          }
        }, 1000);
      }
    },
    async expire() {
      if (this.expiring || this.expired) return;
      this.expiring = true;
      try {
        const response = await fetch(this.expireEndpoint, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
        });
        if (!response.ok) throw new Error('The attempt could not be closed.');
        this.expired = true;
      } catch (error) {
        this.errors._timer = error.message;
      } finally {
        this.expiring = false;
      }
    },
    get answeredCount() {
      return Object.values(this.answers).filter((answer) => answer !== null && answer !== '').length;
    },
    get timerLabel() {
      if (this.secondsLeft === null) return 'No time limit';
      const hours = Math.floor(this.secondsLeft / 3600).toString().padStart(2, '0');
      const minutes = Math.floor((this.secondsLeft % 3600) / 60).toString().padStart(2, '0');
      const seconds = (this.secondsLeft % 60).toString().padStart(2, '0');
      return `${hours}:${minutes}:${seconds}`;
    },
    async save(questionId, answer) {
      if (this.expired) return;
      if (this.controllers[questionId]) this.controllers[questionId].abort();
      const controller = new AbortController();
      this.controllers[questionId] = controller;
      this.saving[questionId] = true;
      this.saved[questionId] = false;
      this.errors[questionId] = '';
      try {
        const response = await fetch(answerEndpoint.replace('QUESTION', questionId), {
          method: 'PATCH',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          signal: controller.signal,
          body: JSON.stringify({ answer: answer ?? '' }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Answer could not be saved.');
        this.saved[questionId] = true;
      } catch (error) {
        if (error.name !== 'AbortError') this.errors[questionId] = error.message;
      } finally {
        if (this.controllers[questionId] === controller) {
          this.saving[questionId] = false;
          delete this.controllers[questionId];
        }
      }
    },
  });
</script>
@endpush
