<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Applicant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

trait StreamsApplicantDocuments
{
    private array $applicantDocumentTypes = ['photo_2x2', 'psa_birth_cert'];

    protected function downloadApplicantDocument(Applicant $applicant, string $documentType)
    {
        return $this->streamApplicantDocument($applicant, $documentType, true);
    }

    protected function previewApplicantDocument(Applicant $applicant, string $documentType)
    {
        return $this->streamApplicantDocument($applicant, $documentType, false);
    }

    private function streamApplicantDocument(Applicant $applicant, string $documentType, bool $download)
    {
        if (! in_array($documentType, $this->applicantDocumentTypes, true)) {
            abort(404, 'Document not found.');
        }

        $filePath = $applicant->{$documentType};
        $disk = Storage::disk('private');

        if (! $filePath || ! $disk->exists($filePath)) {
            abort(404, 'File not found on server.');
        }

        $absolutePath = $disk->path($filePath);
        $filename = basename($filePath);
        $mimeType = $disk->mimeType($filePath) ?: $this->fallbackMimeType($filename);

        $response = response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);

        $response->setContentDisposition(
            $download ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $filename
        );

        return $response;
    }

    private function fallbackMimeType(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
