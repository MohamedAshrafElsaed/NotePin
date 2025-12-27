<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recording extends Model
{
    protected $fillable = [
        'user_id',
        'anonymous_id',
        'status',
        'audio_path',
        'duration_seconds',
        'transcript',
        'ai_title',
        'ai_summary',
        'ai_action_items',
        'ai_meta',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'ai_action_items' => 'array',
        'ai_meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
