<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateCertificate implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $student,
        public Course $course,
    ) {
    }

    public function handle(): void
    {
        $certificate = Certificate::firstOrCreate(
            [
                'student_id' => $this->student->id,
                'course_id' => $this->course->id,
            ],
            [
                'file_path' => null,
            ]
        );

        $filePath = "certificates/{$certificate->id}.pdf";

        $pdf = Pdf::loadView('certificates.certificate', [
            'student' => $this->student,
            'course' => $this->course,
        ]);

        Storage::disk('local')->put(
            $filePath,
            $pdf->output()
        );

        $certificate->update([
            'file_path' => $filePath,
        ]);
    }
}