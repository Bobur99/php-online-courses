<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class CertificateService
{
    /** @return Collection<int, Certificate> */
    public function forStudent(User $student): Collection
    {
        return Certificate::query()
            ->where('student_id', $student->id)
            ->with('course:id,title')
            ->latest()
            ->get();
    }

    public function filePath(Certificate $certificate): string
    {
        abort_unless($certificate->file_path && Storage::disk('local')->exists($certificate->file_path), 404);

        return Storage::disk('local')->path($certificate->file_path);
    }
}
