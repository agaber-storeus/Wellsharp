<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Certificate verification - {{ $certificate->certificate_number }}</title>
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f1f1f1; color: #172333; font: 18px Arial, sans-serif; }
    main { width: min(484px, 100%); padding: 42px 55px 54px; background: #fff; border: 1px solid #e7e7e7; border-radius: 3px; box-shadow: 0 2px 4px rgba(0, 0, 0, .22); }
    .logo { display: block; width: 184px; height: auto; margin: 0 auto 30px; object-fit: contain; }
    dl { margin: 0; }
    .row { display: grid; grid-template-columns: 134px 1fr; gap: 0; padding: 13px 12px; border-top: 1px solid #dedede; line-height: 1.35; }
    dt, dd { margin: 0; }
    dt { white-space: pre-line; }
    dd { overflow-wrap: anywhere; }
    .status-issued { color: #18794e; font-weight: 600; }
    .status-revoked { color: #a40022; font-weight: 600; }
    .back { display: block; margin-top: 26px; padding: 14px 16px; border-radius: 3px; background: #990025; color: #fff; font-weight: 700; text-align: center; text-decoration: none; }
    .back:hover { background: #78001d; }
    @media (max-width: 520px) { main { padding: 30px 20px 34px; } .row { grid-template-columns: 120px 1fr; padding-inline: 6px; } }
  </style>
</head>
<body>
  <main>
    <img class="logo" src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" />
    <dl>
      <div class="row"><dt>Certificate ID<br>:</dt><dd>{{ $certificate->certificate_number }}</dd></div>
      <div class="row"><dt>Name :</dt><dd>{{ $certificate->student_name }}</dd></div>
      <div class="row"><dt>Completed<br>on :</dt><dd>{{ $certificate->issued_at?->format('d F Y') }}</dd></div>
      <div class="row"><dt>Expires On:</dt><dd>{{ $certificate->expires_at?->format('d F Y') ?: '—' }}</dd></div>
      <div class="row"><dt>Program :</dt><dd>{{ $certificate->subject_name ?: $certificate->exam_name }}</dd></div>
      <div class="row"><dt>Supplement:</dt><dd>{{ $certificate->exam_name ?: '—' }}</dd></div>
      <div class="row"><dt>Provider :</dt><dd>{{ $certificate->provider_name ?: '—' }}</dd></div>
      <div class="row"><dt>Status :</dt><dd class="{{ $certificate->status?->value === 'revoked' ? 'status-revoked' : 'status-issued' }}">{{ $certificate->status?->label() ?? 'Issued' }}</dd></div>
    </dl>
    <a class="back" href="{{ route('iadc.certification') }}">Back</a>
  </main>
</body>
</html>
