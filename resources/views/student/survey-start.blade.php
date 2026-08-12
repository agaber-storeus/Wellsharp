@extends('layouts.student')

@section('student-content')
<section class="workspace">
  <section class="wide-panel survey-intro">
    <div class="panel-title">Course Feedback</div>
    <div class="survey-copy">
      <h1>Trainee Course Feedback</h1>
      <p>The following survey allows IADC and accredited training providers to continually improve the WellSharp training and assessment process.</p>
      <p>Your responses to the survey are completely anonymous and in no way traceable back to you. Survey results are returned to IADC only and collected for review and analysis of exam questions, your instructor's course performance, and your training provider's management of the WellSharp training delivery.</p>
      <p>Please complete each survey question with the choice that you think best answers the question. Some survey questions allow for comments to be made. Though comments are desirable, they are not required.</p>
    </div>
  </section>
  <section class="wide-panel survey-box">
    <div class="panel-title">Survey Questions</div>
    <div class="survey-empty"><a class="green-btn arrow" href="{{ route('student.survey.form', $schedule) }}">Proceed</a></div>
  </section>
</section>
@endsection
