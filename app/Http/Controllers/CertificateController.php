<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'student', 403);

        $certificates = Certificate::query()
            ->where('student_id', $request->user()->id)
            ->with('course:id,title')
            ->latest()
            ->get()
            ->map(fn (Certificate $certificate): array => [
                'id' => $certificate->id,
                'course' => $certificate->course,
                'created_at' => $certificate->created_at,
                'download_url' => "/api/certificates/{$certificate->id}/download",
            ]);

        return response()->json(['data' => $certificates]);
    }

    public function download(
        Request $request,
        Certificate $certificate
    ): BinaryFileResponse {
        if ($certificate->student_id !== $request->user()->id) {
            throw new AuthorizationException(
                'You cannot download this certificate.'
            );
        }

        abort_unless(
            $certificate->file_path
                && Storage::disk('local')->exists($certificate->file_path),
            404
        );

        return response()->file(
            Storage::disk('local')->path($certificate->file_path),
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }
}
