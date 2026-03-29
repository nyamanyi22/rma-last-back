<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class FileUploadService
{
    const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'application/pdf',

    ];

    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    /**
     * Validate file
     */
    private function validateFile(UploadedFile $file): void
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            throw new Exception("Invalid file type. Only JPG, PNG allowed.");
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new Exception("File too large. Maximum 5MB allowed.");
        }
    }

    /**
     * Upload file to Cloudinary
     */
    public function uploadToCloudinary(UploadedFile $file, int $userId, ?int $rmaId = null): array
    {
        // Wrapper that now uses local storage as requested
        return $this->uploadToLocal($file, $userId, $rmaId);
    }

    /**
     * Local upload
     */
    public function uploadToLocal(UploadedFile $file, int $userId, ?int $rmaId = null): array
    {
        $this->validateFile($file);

        $filename = $this->generateFilename($file, $userId);

        // Store in public disk under rma-attachments/user_id/rma_id/
        $path = $rmaId
            ? "rma-attachments/{$userId}/rma-{$rmaId}"
            : "rma-attachments/{$userId}/temp";

        $storedPath = $file->storeAs($path, $filename, 'public');

        if (!$storedPath) {
            throw new Exception("Failed to store file locally.");
        }

        return [
            'uploaded_by' => $userId,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_path' => $storedPath,
            'url' => Storage::disk('public')->url($storedPath),
            'storage_type' => 'local',
        ];
    }

    private function generateFilename(UploadedFile $file, int $userId): string
    {
        $timestamp = Carbon::now()->format('Ymd_His');
        $random = Str::random(8);
        $extension = strtolower($file->getClientOriginalExtension());

        return "user_{$userId}_{$timestamp}_{$random}.{$extension}";
    }

    public function deleteFromLocal(string $path): bool
    {
        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->delete($path)
            : false;
    }

    /**
     * Helper to format file size
     */
    public function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    /**
     * Get compression stats for an attachment
     */
    public function getCompressionStats(array $data): array
    {
        // For local storage, we don't have compression stats in the same way as Cloudinary
        if (($data['storage_type'] ?? 'local') === 'local') {
            return [
                'original_size' => $data['file_size'] ?? 0,
                'compressed_size' => $data['file_size'] ?? 0,
                'savings_percent' => 0,
            ];
        }

        // For Cloudinary, try to extract from metadata
        $metadata = $data['cloudinary_metadata'] ?? [];
        return [
            'original_size' => $metadata['original_bytes'] ?? ($data['file_size'] ?? 0),
            'compressed_size' => $metadata['bytes'] ?? ($data['file_size'] ?? 0),
            'savings_percent' => $metadata['compression_percent'] ?? 0,
        ];
    }
}
