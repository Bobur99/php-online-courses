<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
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
