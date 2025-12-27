<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Share extends Model
{
    protected $fillable = [
        'recording_id',
        'token',
    ];

    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }
}
