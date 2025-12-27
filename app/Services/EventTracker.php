<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class EventTracker
{
    public static function track(string $name, array $data = []): void
    {
        try {
            $metadata = $data['metadata'] ?? [];

            if (Request::ip()) {
                $metadata['ip'] = Request::ip();
            }

            if (Request::userAgent()) {
                $metadata['user_agent'] = Request::userAgent();
            }

            Event::create([
                'name' => $name,
                'recording_id' => $data['recording_id'] ?? null,
                'user_id' => $data['user_id'] ?? Auth::id(),
                'metadata' => !empty($metadata) ? $metadata : null,
            ]);
        } catch (\Throwable $e) {
            // Silent fail - tracking should never break the app
            report($e);
        }
    }
}
