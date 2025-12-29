<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRecordingWithAI;
use App\Models\Recording;
use App\Services\AnonymousUserResolver;
use App\Services\EventTracker;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Log;

class RecordingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Log::info('Recording upload started', [
            'has_file' => $request->hasFile('audio'),
            'file_valid' => $request->hasFile('audio') ? $request->file('audio')->isValid() : false,
            'content_type' => $request->header('Content-Type'),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            $request->validate([
                'audio' => 'required|file|mimetypes:audio/*,video/webm,video/mp4|max:102400',
                'duration' => 'nullable|integer|min:0|max:3600',
                'anonymous_id' => 'nullable|string|max:64',
            ]);

            Log::info('Validation passed');

            $file = $request->file('audio');

            Log::info('File info', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            $path = $file->store('recordings', 'public');

            Log::info('File stored', ['path' => $path]);

            $userId = auth()->id();
            $anonymousId = null;

            if (!$userId) {
                $anonymousId = $request->input('anonymous_id') ?: AnonymousUserResolver::resolve($request);
            }

            // Create with processing status - auto-start AI processing
            $recording = Recording::create([
                'user_id' => $userId,
                'anonymous_id' => $anonymousId,
                'status' => 'processing',
                'audio_path' => $path,
                'duration_seconds' => $request->input('duration'),
            ]);

            Log::info('Recording created', ['id' => $recording->id]);

            EventTracker::track('recording_created', [
                'recording_id' => $recording->id,
                'user_id' => $userId,
                'metadata' => [
                    'duration_seconds' => $recording->duration_seconds,
                    'anonymous' => !$userId,
                ],
            ]);

            // Auto-dispatch AI processing job
            EventTracker::track('ai_processing_started', [
                'recording_id' => $recording->id,
                'user_id' => $userId,
                'metadata' => [
                    'auto_started' => true,
                ],
            ]);

            ProcessRecordingWithAI::dispatch($recording->id);

            Log::info('AI processing job dispatched', ['recording_id' => $recording->id]);

            $response = response()->json([
                'id' => $recording->id,
                'status' => $recording->status,
            ]);

            if ($anonymousId) {
                $response->withCookie(AnonymousUserResolver::makeCookie($anonymousId));
            }

            return $response;

        } catch (ValidationException $e) {
            Log::error('Validation failed', [
                'errors' => $e->errors(),
            ]);
            throw $e;
        } catch (Exception $e) {
            Log::error('Recording upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Upload failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if the current user/anonymous user owns the recording
     */
    private function authorizeRecording(Recording $recording, Request $request): bool
    {
        $userId = auth()->id();
        $anonymousId = $request->cookie(AnonymousUserResolver::getCookieName());

        // Authenticated user owns the recording
        if ($userId && $recording->user_id === $userId) {
            return true;
        }

        // Anonymous user owns the recording
        if (!$userId && $anonymousId && $recording->anonymous_id === $anonymousId) {
            return true;
        }

        return false;
    }

    public function json(Request $request, Recording $recording): JsonResponse
    {
        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'recording' => $this->formatRecording($recording),
        ]);
    }

    private function formatRecording(Recording $recording): array
    {
        return [
            'id' => $recording->id,
            'status' => $recording->status,
            'audio_path' => $recording->audio_path,
            'duration_seconds' => $recording->duration_seconds,
            'transcript' => $recording->transcript,
            'ai_title' => $recording->ai_title,
            'ai_summary' => $recording->ai_summary,
            'ai_action_items' => $recording->ai_action_items,
            'ai_meta' => $recording->ai_meta,
            'created_at' => $recording->created_at->toISOString(),
        ];
    }

    public function show(Request $request, int $id): Response
    {
        $recording = Recording::findOrFail($id);

        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Notes/Show', [
            'recording' => $this->formatRecording($recording),
        ]);
    }
}
