<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use App\Models\Share;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShareController extends Controller
{
    public function store(Recording $recording): JsonResponse
    {
        $share = Share::firstOrCreate(
            ['recording_id' => $recording->id],
            ['token' => Str::random(32)]
        );

        return response()->json([
            'url' => url("/share/{$share->token}"),
            'token' => $share->token,
        ]);
    }

    public function show(string $token): Response
    {
        $share = Share::where('token', $token)->firstOrFail();
        $recording = $share->recording;

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
