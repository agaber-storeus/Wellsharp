@extends('layouts.student')

@section('student-content')
<section class="workspace certificate-list-page">
  <section class="wide-panel welcome-panel">
    <div class="panel-title">My Certificates</div>
    <div class="welcome-body"><img src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" /><div><h1>Certificate Documents</h1><p>Each passed Class produces a Knowledge Assessment Report and the front and back of your Course Completion Card.</p></div></div>
  </section>
  @forelse($certificates as $certificate)
    <section class="wide-panel certificate-list-card">
      <div><strong>{{ $certificate->subject_name }}</strong><span>{{ $certificate->certificate_number }}</span><small>Issued {{ $certificate->issued_at?->format('F j, Y') }} · Valid through {{ $certificate->expires_at?->format('F j, Y') ?: $certificate->issued_at?->copy()->addYears(2)->format('F j, Y') }}</small></div>
      <a class="green-btn" href="{{ route('certificates.show', $certificate) }}">View {{ $certificate->documents->count() }} documents</a>
    </section>
  @empty
    <section class="wide-panel"><div class="panel-body">No certificates have been issued yet.</div></section>
  @endforelse
</section>
@endsection
