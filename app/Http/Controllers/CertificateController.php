<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
    public function __construct(private CertificateService $certificateService) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(PermissionName::ViewOwnCertificates->value), 403);

        $certificates = $this->certificateService->forStudent($request->user())
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
        if (! $request->user()->can(PermissionName::DownloadOwnCertificates->value)
            || $certificate->student_id !== $request->user()->id) {
            throw new AuthorizationException(
                'You cannot download this certificate.'
            );
        }

        return response()->file(
            $this->certificateService->filePath($certificate),
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }
}
