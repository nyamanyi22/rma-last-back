<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RMAComment extends Model
{
    use HasFactory;

    protected $table = 'rma_comments';

    protected $fillable = [
        'rma_id',
        'user_id',
        'type',
        'comment',
        'notify_customer',
    ];

    protected $casts = [
        'notify_customer' => 'boolean',
    ];

    public function rmaRequest(): BelongsTo
    {
        return $this->belongsTo(RMARequest::class , 'rma_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
