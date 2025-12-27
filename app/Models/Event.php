<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'recording_id',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
