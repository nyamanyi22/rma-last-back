<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;
use Carbon\Carbon;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class FileUploadService
{
    const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'application/pdf',
    ];

    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    const MAX_FILE_SIZE = 5; // MB
    const TEMP_FILE_RETENTION_DAYS = 7;

    // Compression settings
    const IMAGE_QUALITY = 'auto:good'; // auto:good, auto:best, auto:eco
    const IMAGE_FORMAT = 'auto'; // auto converts to WebP/AVIF when supported
    const MAX_IMAGE_WIDTH = 1920; // Resize large images to this max width
    const MAX_IMAGE_HEIGHT = 1080; // Resize large images to this max height

    // =========================
    // CLOUDINARY UPLOAD METHODS WITH COMPRESSION
    // =========================

    /**
     * Upload a single file to Cloudinary with automatic compression
     */
    public function uploadToCloudinary(UploadedFile $file, int $userId, ?int $rmaId = null): array
    {
        $this->validateFile($file);

        try {
            // Determine folder structure
            $folder = $rmaId
                ? "rma-attachments/user-{$userId}/rma-{$rmaId}"
                : "rma-attachments/user-{$userId}/temp";

            // Generate unique public ID
            $timestamp = now()->format('Ymd_His');
            $random = Str::random(8);
            $extension = strtolower($file->getClientOriginalExtension());
            $publicId = "user_{$userId}_{$timestamp}_{$random}";

            // Determine resource type based on mime
            $mime = $file->getMimeType();
            $isImage = str_starts_with($mime, 'image/');
            $isPdf = $mime === 'application/pdf';

            // For PDFs, use image type to enable transformations
            $resourceType = $isImage || $isPdf ? 'image' : 'raw';

            // Build upload options with automatic compression
            $uploadOptions = [
                'folder' => $folder,
                'public_id' => $publicId,
                'resource_type' => $resourceType,
                'access_mode' => 'public',
                'tags' => ['rma', "user-{$userId}", $rmaId ? "rma-{$rmaId}" : 'temp'],
            ];

            // Add compression options for images and PDFs
            if ($isImage) {
                // Image compression options
                $uploadOptions = array_merge($uploadOptions, [
                    'quality' => self::IMAGE_QUALITY,
                    'fetch_format' => self::IMAGE_FORMAT,
                    'flags' => 'progressive', // Progressive loading

                    // Auto resize large images to save space
                    'transformation' => [
                        [
                            'width' => self::MAX_IMAGE_WIDTH,
                            'height' => self::MAX_IMAGE_HEIGHT,
                            'crop' => 'limit', // Maintain aspect ratio, don't exceed
                            'quality' => self::IMAGE_QUALITY,
                        ]
                    ],

                    // Remove metadata to save space
                    'invalidate' => true,
                ]);

                // If it's a JPEG, apply additional optimizations
                if ($file->getMimeType() === 'image/jpeg' || $file->getMimeType() === 'image/jpg') {
                    $uploadOptions['transformation'][0]['flags'] = 'strip_profile'; // Remove EXIF data
                }

            } elseif ($isPdf) {
                // PDF optimization
                $uploadOptions = array_merge($uploadOptions, [
                    'quality' => self::IMAGE_QUALITY,
                    'flags' => 'rasterize', // Rasterize for security
                ]);
            }

            // Upload to Cloudinary with compression
            $uploaded = Cloudinary::upload($file->getRealPath(), $uploadOptions);

            // Get the uploaded file info
            $uploadedData = $uploaded->getResponse();

            // Calculate compression savings (if original size is available)
            $originalSize = $file->getSize();
            $cloudinarySize = $uploadedData['bytes'] ?? $originalSize;
            $savings = $originalSize - $cloudinarySize;
            $savingsPercent = $originalSize > 0 ? round(($savings / $originalSize) * 100, 2) : 0;

            return [
                'uploaded_by' => $userId,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $originalSize,
                'cloudinary_public_id' => $uploaded->getPublicId(),
                'cloudinary_url' => $uploaded->getSecurePath(),
                'cloudinary_metadata' => [
                    'width' => $uploadedData['width'] ?? null,
                    'height' => $uploadedData['height'] ?? null,
                    'format' => $uploadedData['format'] ?? null,
                    'version' => $uploadedData['version'] ?? null,
                    'resource_type' => $uploadedData['resource_type'] ?? $resourceType,
                    'created_at' => $uploadedData['created_at'] ?? now()->toIso8601String(),
                    'bytes' => $cloudinarySize,
                    'original_bytes' => $originalSize,
                    'compression_savings' => $savings,
                    'compression_percent' => $savingsPercent,
                    'quality_setting' => self::IMAGE_QUALITY,
                ],
                'storage_type' => 'cloudinary',
            ];

        } catch (Exception $e) {
            throw new Exception("Cloudinary upload failed: " . $e->getMessage());
        }
    }

    /**
     * Upload multiple files to Cloudinary with compression
     */
    public function uploadMultipleToCloudinary(array $files, int $userId, ?int $rmaId = null): array
    {
        $uploadedFiles = [];
        $errors = [];

        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $index => $file) {
            if (!$file instanceof UploadedFile) {
                $errors[] = "File at index {$index} is not a valid upload.";
                continue;
            }

            try {
                $uploadedFiles[] = $this->uploadToCloudinary($file, $userId, $rmaId);
            } catch (Exception $e) {
                $errors[] = "File '{$file->getClientOriginalName()}': " . $e->getMessage();
            }
        }

        return [
            'success' => $uploadedFiles,
            'errors' => $errors,
        ];
    }

    /**
     * Upload Base64 string to Cloudinary with compression
     */
    public function uploadBase64ToCloudinary(string $base64Data, string $fileName, int $userId, ?int $rmaId = null): array
    {
        try {
            // Extract actual base64 if it's data URL
            if (preg_match('/^data:(\w+\/\w+);base64,/', $base64Data, $type)) {
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                $mimeType = $type[1];
            } else {
                $mimeType = 'application/octet-stream';
            }

            // Determine folder
            $folder = $rmaId
                ? "rma-attachments/user-{$userId}/rma-{$rmaId}"
                : "rma-attachments/user-{$userId}/temp";

            // Determine resource type
            $isImage = str_starts_with($mimeType, 'image/');
            $isPdf = $mimeType === 'application/pdf';
            $resourceType = $isImage || $isPdf ? 'image' : 'raw';

            // Build upload options with compression
            $uploadOptions = [
                'folder' => $folder,
                'public_id' => 'base64_' . uniqid() . '_' . pathinfo($fileName, PATHINFO_FILENAME),
                'resource_type' => $resourceType,
                'access_mode' => 'public',
            ];

            // Add compression for images
            if ($isImage) {
                $uploadOptions = array_merge($uploadOptions, [
                    'quality' => self::IMAGE_QUALITY,
                    'fetch_format' => self::IMAGE_FORMAT,
                    'flags' => 'progressive',
                    'transformation' => [
                        [
                            'width' => self::MAX_IMAGE_WIDTH,
                            'height' => self::MAX_IMAGE_HEIGHT,
                            'crop' => 'limit',
                        ]
                    ],
                ]);
            } elseif ($isPdf) {
                $uploadOptions = array_merge($uploadOptions, [
                    'quality' => self::IMAGE_QUALITY,
                    'flags' => 'rasterize',
                ]);
            }

            // Upload to Cloudinary
            $uploaded = Cloudinary::upload("data:{$mimeType};base64,{$base64Data}", $uploadOptions);

            $uploadedData = $uploaded->getResponse();

            return [
                'uploaded_by' => $userId,
                'original_name' => $fileName,
                'mime_type' => $mimeType,
                'file_size' => $uploadedData['bytes'] ?? 0,
                'cloudinary_public_id' => $uploaded->getPublicId(),
                'cloudinary_url' => $uploaded->getSecurePath(),
                'cloudinary_metadata' => json_encode($uploadedData),
                'storage_type' => 'cloudinary',
            ];

        } catch (Exception $e) {
            throw new Exception("Base64 upload failed: " . $e->getMessage());
        }
    }

    /**
     * Get optimized delivery URL for an existing image
     */
    public function getOptimizedUrl(string $publicId, int $width = null, int $height = null): string
    {
        $cloudName = config('cloudinary.cloud.cloud_name');
        $baseUrl = "https://res.cloudinary.com/{$cloudName}/image/upload/";

        $transformations = [];

        // Add automatic quality and format
        $transformations[] = 'q_auto';
        $transformations[] = 'f_auto';

        // Add progressive loading
        $transformations[] = 'fl_progressive';

        // Add resize if dimensions provided
        if ($width && $height) {
            $transformations[] = "c_limit,w_{$width},h_{$height}";
        } elseif ($width) {
            $transformations[] = "c_limit,w_{$width}";
        } elseif ($height) {
            $transformations[] = "c_limit,h_{$height}";
        }

        $transformationString = implode(',', $transformations) . '/';

        return $baseUrl . $transformationString . $publicId;
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrl(string $publicId, int $width = 200, int $height = 200): string
    {
        $cloudName = config('cloudinary.cloud.cloud_name');

        return "https://res.cloudinary.com/{$cloudName}/image/upload/" .
            "c_fill,w_{$width},h_{$height},q_auto,f_auto/" .
            $publicId;
    }

    // =========================
    // DELETE METHODS
    // =========================

    /**
     * Delete file from Cloudinary by public ID
     */
    public function deleteFromCloudinary(string $publicId, string $resourceType = 'image'): bool
    {
        try {
            $result = Cloudinary::destroy($publicId, [
                'resource_type' => $resourceType
            ]);

            return $result === 'ok';
        } catch (Exception $e) {
            \Log::error("Cloudinary deletion failed: " . $e->getMessage());
            return false;
        }
    }

    // =========================
    // VALIDATION & HELPERS
    // =========================

    /**
     * Validate file
     */
    private function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new Exception("Invalid file upload.");
        }

        if ($file->getSize() > self::MAX_FILE_SIZE * 1024 * 1024) {
            throw new Exception("File '{$file->getClientOriginalName()}' exceeds maximum size of " . self::MAX_FILE_SIZE . "MB.");
        }

        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME_TYPES)) {
            throw new Exception("File '{$file->getClientOriginalName()}' type '{$mime}' is not allowed.");
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new Exception("File '{$file->getClientOriginalName()}' extension '{$extension}' is not allowed.");
        }
    }

    /**
     * Format file size
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
     * Get compression statistics for an attachment
     */
    public function getCompressionStats(array $attachment): array
    {
        if (!isset($attachment['cloudinary_metadata'])) {
            return [
                'original_size' => $attachment['file_size'] ?? 0,
                'compressed_size' => $attachment['file_size'] ?? 0,
                'savings' => 0,
                'savings_percent' => 0,
            ];
        }

        $metadata = json_decode($attachment['cloudinary_metadata'], true);
        $original = $metadata['original_bytes'] ?? $attachment['file_size'] ?? 0;
        $compressed = $metadata['bytes'] ?? $original;
        $savings = $original - $compressed;
        $percent = $original > 0 ? round(($savings / $original) * 100, 2) : 0;

        return [
            'original_size' => $original,
            'original_size_formatted' => $this->formatSize($original),
            'compressed_size' => $compressed,
            'compressed_size_formatted' => $this->formatSize($compressed),
            'savings' => $savings,
            'savings_formatted' => $this->formatSize($savings),
            'savings_percent' => $percent,
        ];
    }

    // =========================
    // LEGACY METHODS (Keep for backward compatibility)
    // =========================

    public function uploadToLocal(UploadedFile $file, int $userId, ?int $rmaId = null): array
    {
        // Your existing local upload code...
        $this->validateFile($file);

        $filename = $this->generateFilename($file, $userId);
        $path = $rmaId
            ? "rma-attachments/{$userId}/rma-{$rmaId}"
            : "rma-attachments/{$userId}/temp";

        $storedPath = $file->storeAs($path, $filename, 'public');

        if (!$storedPath) {
            throw new Exception("Failed to store file '{$file->getClientOriginalName()}'");
        }

        return [
            'uploaded_by' => $userId,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'path' => $storedPath,
            'url' => Storage::url($storedPath),
            'storage_type' => 'local',
        ];
    }

    private function generateFilename(UploadedFile $file, int $userId): string
    {
        $timestamp = now()->format('Ymd_His');
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
}