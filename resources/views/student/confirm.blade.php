@extends('layouts.student')

@section('student-content')
<section class="workspace">
  <section class="wide-panel welcome-panel">
    <div class="panel-title">Welcome to Your Account</div>
    <div class="welcome-body">
      <img src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" />
      <div>
        <h1>Welcome to your IADC WellSharp<br />Testing Portal</h1>
        <p>Please review your name and contact information for accuracy and report any incorrect information to your instructor. This information will help us regenerate your certificate if lost.</p>
      </div>
    </div>
  </section>

  <section class="wide-panel contact-panel">
    <div class="panel-title">Review Your Contact Information</div>
    <div class="contact-grid">
      <dl>
        <dt>Name:</dt><dd>{{ $user->display_name }}</dd>
        <dt>Birthday:</dt><dd>{{ $user->profile?->birthday?->format('F j, Y') ?: '-' }}</dd>
        <dt>Email:</dt><dd>{{ $user->email ?: '-' }}</dd>
        <dt>Phone Number:</dt><dd>{{ $user->profile?->phone ?: '-' }}</dd>
        <dt>Address:</dt><dd>{{ $user->profile?->address ?: '-' }}</dd>
        <dt>Country:</dt><dd>{{ $user->profile?->country ?: '-' }}</dd>
        <dt>City:</dt><dd>{{ $user->profile?->city ?: '-' }}</dd>
        <dt>Postal Code:</dt><dd>{{ $user->profile?->postal_code ?: '-' }}</dd>
      </dl>
      <dl>
        <dt>Company:</dt><dd>{{ $user->profile?->company ?: '-' }}</dd>
        <dt>Position:</dt><dd>{{ $user->profile?->position ?: '-' }}</dd>
        <dt>Company Contact:</dt><dd>{{ $user->profile?->company_contact ?: '-' }}</dd>
        <dt>Employee ID:</dt><dd>{{ $user->profile?->employee_id ?: $user->wellsharp_id }}</dd>
      </dl>
    </div>
  </section>

  <div class="center-actions">
    <form method="POST" action="{{ route('student.confirm.store', $schedule) }}">@csrf<button class="green-btn arrow" type="submit">Continue</button></form>
  </div>
</section>
@endsection
