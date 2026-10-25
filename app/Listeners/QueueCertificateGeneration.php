<?php

namespace App\Listeners;

use App\Events\CourseCompleted;
use App\Jobs\GenerateCertificate;

class QueueCertificateGeneration
{
    public function handle(CourseCompleted $event): void
    {
        GenerateCertificate::dispatch(
            $event->student,
            $event->course
        );
    }
}