<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateLookupController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $lookup = strtoupper(trim((string) $request->query('lookup')));
        $certificateId = $lookup;
        $instructorNumber = $lookup;
        $certificate = null;
        $instructor = null;
        $students = collect();
        $error = null;

        if ($lookup !== '') {
            $certificate = Certificate::query()->where('certificate_number', $certificateId)->first();
            if ($certificate) {
                return redirect()->route('certificates.verify', $certificate->certificate_number);
            }

            $instructor = User::query()
                ->where('wellsharp_id', $instructorNumber)
                ->whereHas('currentRole', fn ($query) => $query->where('key', Role::INSTRUCTOR))
                ->first();

            if (! $instructor) {
                $error = 'No certificate or instructor was found for that number.';
            }
        }

        if ($instructor) {
            $students = Certificate::query()
                ->where('instructor_user_id', $instructor->getKey())
                ->orderBy('student_name')
                ->orderByDesc('issued_at')
                ->get();

            if ($students->isEmpty()) {
                $error = 'No student certificates were found for that Instructor Number.';
            }
        }

        return view('iadc.certification', compact('lookup', 'certificateId', 'instructorNumber', 'certificate', 'instructor', 'students', 'error'));
    }
}
