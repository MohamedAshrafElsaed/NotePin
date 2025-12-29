<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRecordingWithAI;
use App\Models\Recording;
use App\Services\AnonymousUserResolver;
use App\Services\EventTracker;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TextNoteController extends Controller
{
    /**
     * Store a text note and auto-start AI processing.
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('Text note creation started', [
            'content_length' => strlen($request->input('text', '')),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            $request->validate([
                'text' => 'required|string|min:10|max:50000',
                'anonymous_id' => 'nullable|string|max:64',
            ]);

            Log::info('Text note validation passed');

            $userId = auth()->id();
            $anonymousId = null;

            if (!$userId) {
                $anonymousId = $request->input('anonymous_id')
                    ?: AnonymousUserResolver::resolve($request);
            }

            // Create with processing status - auto-start AI processing
            $recording = Recording::create([
                'user_id' => $userId,
                'anonymous_id' => $anonymousId,
                'status' => 'processing',
                'transcript' => $request->input('text'),
                // No audio_path - this is a text note
            ]);

            Log::info('Text note created', ['id' => $recording->id]);

            EventTracker::track('recording_created', [
                'recording_id' => $recording->id,
                'user_id' => $userId,
                'metadata' => [
                    'input_type' => 'text',
                    'text_length' => strlen($request->input('text')),
                    'anonymous' => !$userId,
                ],
            ]);

            // Auto-dispatch AI processing job
            EventTracker::track('ai_processing_started', [
                'recording_id' => $recording->id,
                'user_id' => $userId,
                'metadata' => [
                    'auto_started' => true,
                    'input_type' => 'text',
                ],
            ]);

            ProcessRecordingWithAI::dispatch($recording->id);

            Log::info('AI processing job dispatched for text note', ['recording_id' => $recording->id]);

            $response = response()->json([
                'id' => $recording->id,
                'status' => $recording->status,
            ]);

            if ($anonymousId) {
                $response->withCookie(AnonymousUserResolver::makeCookie($anonymousId));
            }

            return $response;

        } catch (ValidationException $e) {
            Log::error('Text note validation failed', [
                'errors' => $e->errors(),
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Text note creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Failed to create note',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
