<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Enums\RMAStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RMAStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'rma_status_histories';

    protected $fillable = [
        'rma_id',
        'old_status',
        'new_status',
        'changed_by',
        'notes',
    ];

    protected $casts = [
        'old_status' => RMAStatus::class ,
        'new_status' => RMAStatus::class ,
    ];

    public function rmaRequest(): BelongsTo
    {
        return $this->belongsTo(RMARequest::class , 'rma_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class , 'changed_by');
    }
}
