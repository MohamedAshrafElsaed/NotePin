<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use App\Models\Share;
use App\Services\EventTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShareController extends Controller
{
    public function store(Request $request, Recording $recording): JsonResponse
    {
        // Ensure the authenticated user owns this recording
        if ($recording->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $share = Share::firstOrCreate(
            ['recording_id' => $recording->id],
            ['token' => Str::random(32)]
        );

        if ($share->wasRecentlyCreated) {
            EventTracker::track('share_created', [
                'recording_id' => $recording->id,
                'user_id' => auth()->id(),
                'metadata' => [
                    'share_token' => $share->token,
                ],
            ]);
        }

        return response()->json([
            'url' => url("/share/{$share->token}"),
            'token' => $share->token,
        ]);
    }

    public function show(string $token): Response
    {
        $share = Share::where('token', $token)->firstOrFail();
        $recording = $share->recording;

        EventTracker::track('share_opened', [
            'recording_id' => $recording->id,
            'metadata' => [
                'share_token' => $token,
            ],
        ]);

        return Inertia::render('Share/Show', [
            'recording' => [
                'ai_title' => $recording->ai_title,
                'ai_summary' => $recording->ai_summary,
                'ai_action_items' => $recording->ai_action_items ?? [],
                'duration_seconds' => $recording->duration_seconds,
                'created_at' => $recording->created_at->toISOString(),
            ],
        ]);
    }
}
