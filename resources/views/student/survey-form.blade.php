@extends('layouts.student')

@section('student-content')
  <section class="workspace">
    <section class="wide-panel survey-intro">
      <div class="panel-title">Course Feedback</div>
      <div class="survey-copy">
        <h1>Trainee Course Feedback</h1>
        <p>The following survey allows IADC and accredited training providers to continually improve the WellSharp
          training and assessment process.</p>
        <p>Your responses to the survey are completely anonymous and in no way traceable back to you.</p>
      </div>
    </section>
    <section class="wide-panel survey-form-panel">
      <div class="panel-title">Survey Questions</div>
      <form class="survey-form" method="POST" action="{{ route('student.survey.store', $schedule) }}">
        @csrf
        @foreach($questions as $question)
          <div
            class="question-row {{ $loop->even ? 'shade' : '' }} {{ $question['type'] === 'textarea' ? 'comment-row' : '' }}">
            @if($question['type'] !== 'textarea')
              <label for="survey-{{ $question['key'] }}">{{ $question['label'] }}@if($question['required']) @endif</label>
            @endif
            @if($question['type'] === 'select')
              <select id="survey-{{ $question['key'] }}" name="answers[{{ $question['key'] }}]" {{ $question['required'] ? 'required' : '' }}>
                <option value="">Select an Option</option>
                @foreach($question['options'] as $option)
                  <option value="{{ $option }}" @selected(old('answers.' . $question['key']) === $option)>{{ $option }}</option>
                @endforeach
              </select>
            @elseif($question['type'] === 'textarea')
              <textarea class="h-[50px]" id="survey-{{ $question['key'] }}" name="answers[{{ $question['key'] }}]"
                placeholder="{{ $question['label'] }}:" aria-label="{{ $question['label'] }}"
                rows="4">{{ old('answers.' . $question['key']) }}</textarea>
            @else
              <input id="survey-{{ $question['key'] }}" name="answers[{{ $question['key'] }}]"
                value="{{ old('answers.' . $question['key']) }}" {{ $question['required'] ? 'required' : '' }} />
            @endif
          </div>
        @endforeach
        <div class="center-actions"><button class="green-btn arrow" type="submit">Proceed</button></div>
      </form>
    </section>
  </section>
@endsection
