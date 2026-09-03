<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificate Search</title>
  <style>
    :root{color-scheme:light}*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:52px 24px;background:#f1f1f1;color:#222;font:18px Arial,Helvetica,sans-serif}.search-card{width:min(856px,100%);min-height:500px;margin:0 auto;padding:74px 75px 94px;background:#fff;border:1px solid #e7e7e7;border-radius:4px;box-shadow:0 2px 4px rgba(0,0,0,.28)}.logo{display:block;width:230px;height:70px;margin:0 auto 56px;object-fit:contain;object-position:center}.prompt{display:block;margin-bottom:3px;color:#ed1b2f;font-size:27px;line-height:1.25}.lookup-input{display:block;width:100%;height:63px;padding:0 22px;border:1px solid #c9c9c9;border-radius:6px;box-shadow:inset 0 1px 2px rgba(0,0,0,.08);color:#222;font:27px Arial,Helvetica,sans-serif}.lookup-input::placeholder{color:#8c8c8c;opacity:1}.lookup-input:focus{outline:2px solid rgba(153,0,37,.22);border-color:#990025}.search-button{display:block;width:100%;height:68px;margin-top:37px;border:0;border-radius:5px;background:#990025;color:#fff;cursor:pointer;font:700 27px Arial,Helvetica,sans-serif}.search-button:hover{background:#78001d}.message{width:min(856px,100%);margin:20px auto 0;padding:14px 18px;border:1px solid #e2b5bc;background:#fff1f3;color:#8b1c2f;font-size:15px}.students{width:min(856px,100%);margin:20px auto 0;padding:26px 34px;background:#fff;border:1px solid #e7e7e7;border-radius:4px;box-shadow:0 2px 4px rgba(0,0,0,.16)}.students h2{margin:0;color:#990025;font-size:22px}.students p{margin:7px 0 16px;color:#666;font-size:15px}.student-row{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:13px 0;border-top:1px solid #ddd}.student-row strong,.student-row small{display:block}.student-row strong{font-size:17px}.student-row small{margin-top:4px;color:#777}.student-row a{color:#990025;font-size:14px;font-weight:700;white-space:nowrap}@media(max-width:620px){body{padding:24px 12px}.search-card{min-height:0;padding:46px 20px 58px}.logo{margin-bottom:48px}.prompt{font-size:22px}.lookup-input{font-size:21px}.search-button{font-size:23px}.students{padding:22px 20px}.student-row{align-items:flex-start;flex-direction:column;gap:8px}}
  </style>
</head>
<body>
  <main class="search-card">
    <img class="logo" src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp">
    <form method="get" action="{{ route('iadc.certification') }}">
      <label class="prompt" for="lookup">Enter Student Certificate ID or Instructor Number</label>
      <input class="lookup-input" id="lookup" name="lookup" value="{{ $lookup }}" placeholder="Certificate ID" autocomplete="off" autofocus>
      <button class="search-button" type="submit">Search</button>
    </form>
  </main>
  @if($error)<div class="message" role="alert">{{ $error }}</div>@endif
  @if($instructor && $students->isNotEmpty())
    <section class="students" aria-labelledby="students-title"><h2 id="students-title">Students for {{ $instructor->display_name }}</h2><p>Instructor Number: {{ $instructor->wellsharp_id }}</p>
      @foreach($students as $student)<div class="student-row"><div><strong>{{ $student->student_name }}</strong><small>{{ $student->certificate_number }} - {{ $student->subject_name ?: $student->exam_name }}</small></div><a href="{{ route('certificates.verify', $student->certificate_number) }}">Select student -&gt;</a></div>@endforeach
    </section>
  @endif
</body>
</html>
