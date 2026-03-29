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
        'file_path',
        'cloudinary_public_id',
        'cloudinary_url',
        'cloudinary_metadata',
        'storage_type',
    ];

    protected $casts = [
        'cloudinary_metadata' => 'array',
        'file_size' => 'integer',
    ];

    protected $appends = [
        'url',
        'thumbnail',
        'optimized',
    ];

    // ===================================
    // ACCESSORS
    // ===================================

    public function getUrlAttribute()
    {
        return $this->getUrl();
    }

    public function getThumbnailAttribute()
    {
        return $this->getThumbnailUrl();
    }

    public function getOptimizedAttribute()
    {
        return $this->getOptimizedUrl();
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function rmaRequest(): BelongsTo
    {
        return $this->belongsTo(RMARequest::class, 'rma_request_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // =========================================================================
    // API / HELPER METHODS
    // =========================================================================

    /**
     * Get the public URL for the attachment.
     */
    public function getUrl(): string
    {
        if ($this->storage_type === 'cloudinary' && $this->cloudinary_url) {
            return $this->cloudinary_url;
        }

        if ($this->file_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->file_path);
        }

        return asset('images/icons/file-icon.png');
    }

    /**
     * Get a thumbnail URL.
     */
    public function getThumbnailUrl(int $width = 150, int $height = 150): string
    {
        if (!$this->isImage()) {
            return asset('images/icons/file-icon.png');
        }

        if ($this->storage_type === 'cloudinary' && $this->cloudinary_public_id) {
            return Cloudinary::image($this->cloudinary_public_id)
                ->resize('fill', $width, $height)
                ->toUrl();
        }

        // For local storage, just return the full URL as a simple fallback
        return $this->getUrl();
    }

    /**
     * Returns an optimized version of the image.
     */
    public function getOptimizedUrl(): string
    {
        if ($this->storage_type === 'cloudinary' && $this->cloudinary_public_id) {
            return Cloudinary::getUrl($this->cloudinary_public_id);
        }

        return $this->getUrl();
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
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
         * Automatically clean up storage when database record is deleted.
         */
        static::deleting(function ($attachment) {
            if ($attachment->storage_type === 'cloudinary' && $attachment->cloudinary_public_id) {
                try {
                    Cloudinary::destroy($attachment->cloudinary_public_id);
                    Log::info('Cloudinary file deleted: ' . $attachment->cloudinary_public_id);
                } catch (\Exception $e) {
                    Log::error('Cloudinary deletion failed: ' . $e->getMessage());
                }
            } elseif ($attachment->storage_type === 'local' && $attachment->file_path) {
                try {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
                    Log::info('Local file deleted: ' . $attachment->file_path);
                } catch (\Exception $e) {
                    Log::error('Local file deletion failed: ' . $e->getMessage());
                }
            }
        });
    }
}