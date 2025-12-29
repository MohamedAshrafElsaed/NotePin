<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRecordingWithAI;
use App\Models\Recording;
use App\Services\AnonymousUserResolver;
use App\Services\EventTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordingProcessController extends Controller
{
    /**
     * Retry processing for failed recordings.
     * Note: New recordings are auto-processed on creation.
     * This endpoint is only for retrying failed ones.
     */
    public function store(Request $request, Recording $recording): JsonResponse
    {
        // Authorization check
        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
        }

        // Only allow retry for failed recordings
        // Processing and ready recordings should not be reprocessed
        if ($recording->status === 'ready') {
            return response()->json([
                'error' => 'Recording is already processed.',
            ], 422);
        }

        if ($recording->status === 'processing') {
            return response()->json([
                'id' => $recording->id,
                'status' => $recording->status,
                'message' => 'Processing already in progress.',
            ]);
        }

        if (!in_array($recording->status, ['uploaded', 'failed'])) {
            return response()->json([
                'error' => 'Recording cannot be processed in current state.',
            ], 422);
        }

        $recording->update(['status' => 'processing']);

        EventTracker::track('ai_processing_started', [
            'recording_id' => $recording->id,
            'user_id' => $recording->user_id,
            'metadata' => [
                'retry' => true,
            ],
        ]);

        ProcessRecordingWithAI::dispatch($recording->id);

        return response()->json([
            'id' => $recording->id,
            'status' => $recording->status,
        ]);
    }

    private function authorizeRecording(Recording $recording, Request $request): bool
    {
        $userId = auth()->id();
        $anonymousId = $request->cookie(AnonymousUserResolver::getCookieName());

        if ($userId && $recording->user_id === $userId) {
            return true;
        }

        if (!$userId && $anonymousId && $recording->anonymous_id === $anonymousId) {
            return true;
        }

        return false;
    }
}
