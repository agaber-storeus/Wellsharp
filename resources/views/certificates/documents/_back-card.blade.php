<main class="qr-card certificate-standalone exact-card-back">
  <img class="card-back-logo" src="{{ asset('images/iadcLoginLgo.png') }}" alt="IADC WellSharp" />
  <div class="card-back-copy">
    <p>This individual has successfully completed a well control course at an institution accredited by the International Association of Drilling Contractors.</p>
    <p>For scheduling training or replacement of a lost card, please call the training provider with the information provided on this completion card.</p>
    <p>To verify validity, please visit the IADC website:</p>
    <strong>www.iadc.org/wellsharp</strong>
  </div>
  <img class="exact-qr" src="{{ app(\App\Services\CertificateQrCodeService::class)->dataUri($certificate) }}" alt="Certificate verification QR code" />
</main>
