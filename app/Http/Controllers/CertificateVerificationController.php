<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\View\View;

class CertificateVerificationController extends Controller
{
    public function show(Certificate $certificate): View
    {
        return view('certificates.verify', compact('certificate'));
    }
}
