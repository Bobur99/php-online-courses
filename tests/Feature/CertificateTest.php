<?php

namespace Tests\Feature;

use App\Events\CourseCompleted;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use App\Jobs\GenerateCertificate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    private function createCompletedCourse(): array
    {
        $student = User::factory()->create();
        $student->forceFill(['role' => 'student'])->save();

        $teacher = User::factory()->create();
        $teacher->forceFill(['role' => 'teacher'])->save();

        $category = Category::create([
            'name' => 'Backend',
        ]);

        $course = Course::create([
            'title' => 'Laravel Course',
            'description' => 'Course description',
            'price' => 100000,
            'category_id' => $category->id,
            'author_id' => $teacher->id,
            'status' => 'published',
        ]);

        return [$student->refresh(), $course];
    }

    public function test_course_completed_event_dispatches_certificate_job(): void
    {
        Queue::fake();

        [$student, $course] = $this->createCompletedCourse();

        event(new CourseCompleted($student, $course));

        Queue::assertPushed(GenerateCertificate::class);
    }

    public function test_student_can_download_own_certificate(): void
    {
        Storage::fake('local');

        [$student, $course] = $this->createCompletedCourse();

        Storage::disk('local')->put(
            'certificates/certificate.pdf',
            '%PDF-test-content'
        );

        $certificate = Certificate::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'file_path' => 'certificates/certificate.pdf',
        ]);

        $token = $student->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->get("/api/certificates/{$certificate->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_student_cannot_download_another_student_certificate(): void
    {
        Storage::fake('local');

        [$owner, $course] = $this->createCompletedCourse();

        $anotherStudent = User::factory()->create();
        $anotherStudent->forceFill(['role' => 'student'])->save();

        Storage::disk('local')->put(
            'certificates/certificate.pdf',
            '%PDF-test-content'
        );

        $certificate = Certificate::create([
            'student_id' => $owner->id,
            'course_id' => $course->id,
            'file_path' => 'certificates/certificate.pdf',
        ]);

        $token = $anotherStudent->createToken('api-token');

        $this
            ->withToken($token->plainTextToken)
            ->get("/api/certificates/{$certificate->id}/download")
            ->assertForbidden();
    }
    
    public function test_generate_certificate_job_creates_pdf_file(): void
    {
        Storage::fake('local');

        [$student, $course] = $this->createCompletedCourse();

        $job = new GenerateCertificate($student, $course);

        $job->handle();

        $certificate = \App\Models\Certificate::first();

        $this->assertNotNull($certificate);
        $this->assertNotNull($certificate->file_path);

        Storage::disk('local')->assertExists($certificate->file_path);

        $this->assertStringStartsWith(
            '%PDF',
            Storage::disk('local')->get($certificate->file_path)
        );
    }
}
