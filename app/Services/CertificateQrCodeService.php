<?php

namespace App\Services;

use App\Models\Certificate;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class CertificateQrCodeService
{
    public function payload(Certificate $certificate): string
    {
        return route('certificates.verify', ['certificate' => $certificate->certificate_number]);
    }

    public function png(Certificate $certificate, int $size = 220): string
    {
        $qrCode = new QrCode(data: $this->payload($certificate), size: $size, margin: 0);

        return (new PngWriter())->write($qrCode)->getString();
    }

    public function dataUri(Certificate $certificate, int $size = 220): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($certificate, $size));
    }
}
