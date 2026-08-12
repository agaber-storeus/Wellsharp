<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(): View
    {
        return view('student.certificates.index', [
            'certificates' => Certificate::query()
                ->with('documents')
                ->where('student_user_id', auth()->id())
                ->where('status', 'issued')
                ->latest('issued_at')
                ->get(),
        ]);
    }
}
