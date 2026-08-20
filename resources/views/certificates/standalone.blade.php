<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $document->title }}</title>
    <link rel="stylesheet" href="{{ asset('css/proctor.css') }}?v={{ filemtime(public_path('css/proctor.css')) }}" />
  </head>
  <body class="certificate-page">
    @if($document->type === \App\Enums\CertificateDocumentType::CompletionCardFront)
      @include('certificates.documents._front-card')
    @else
      @include('certificates.documents._back-card')
    @endif
  </body>
</html>
