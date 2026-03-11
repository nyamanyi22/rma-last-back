<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;

class RmaAttachment extends Model
{
    use HasFactory;

    protected $table = 'rma_attachments';

    protected $fillable = [
        'rma_request_id',
        'uploaded_by',
        'original_name',
        'mime_type',
        'file_size',
        'cloudinary_public_id',
        'cloudinary_url',
        'cloudinary_metadata',
        'storage_type',
    ];

    protected $casts = [
        'cloudinary_metadata' => 'array',
        'file_size' => 'integer',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function rmaRequest(): BelongsTo
    {
        return $this->belongsTo(RMARequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // =========================================================================
    // API / HELPER METHODS
    // =========================================================================

    /**
     * Get a thumbnail URL. 
     * Note: Changed from Accessor to Method to allow dynamic width/height.
     */
    public function getThumbnailUrl(int $width = 150, int $height = 150): string
    {
        if (!$this->isImage()) {
            return asset('images/icons/file-icon.png'); // Fallback for non-images
        }

        return Cloudinary::image($this->cloudinary_public_id)
            ->resize('fill', $width, $height)
            ->toUrl();
    }

    /**
     * Returns an optimized version of the image.
     */
    public function getOptimizedUrl(): string
    {
        return Cloudinary::image($this->cloudinary_public_id)
            ->autoFormat()
            ->autoQuality()
            ->toUrl();
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    // =========================================================================
    // ACCESSORS & SCOPES
    // =========================================================================

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function scopeForRma($query, $rmaId)
    {
        return $query->where('rma_request_id', $rmaId);
    }

    // =========================================================================
    // MODEL EVENTS
    // =========================================================================

    protected static function booted()
    {
        /**
         * Automatically clean up Cloudinary storage when database record is deleted.
         */
        static::deleting(function ($attachment) {
            if ($attachment->cloudinary_public_id) {
                try {
                    Cloudinary::destroy($attachment->cloudinary_public_id);
                    Log::info('Cloudinary file deleted: ' . $attachment->cloudinary_public_id);
                } catch (\Exception $e) {
                    Log::error('Cloudinary deletion failed: ' . $e->getMessage());
                }
            }
        });
    }
}