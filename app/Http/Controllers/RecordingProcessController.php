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
    public function store(Request $request, Recording $recording): JsonResponse
    {
        // Authorization check
        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
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
