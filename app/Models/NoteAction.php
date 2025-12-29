<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteAction extends Model
{
    protected $fillable = [
        'recording_id',
        'type',
        'source_items',
        'payload',
        'status',
    ];

    protected $casts = [
        'source_items' => 'array',
        'payload' => 'array',
    ];

    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }
}
