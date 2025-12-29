<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActionStateRequest;
use App\Http\Requests\StoreNoteOverrideRequest;
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
                $anonymousId = $request->input('anonymous_id')
                    ?: AnonymousUserResolver::resolve($request);
            }

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

    public function json(Request $request, Recording $recording): JsonResponse
    {
        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'recording' => $this->formatRecording($recording),
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

    /**
     * @return array<string, mixed>
     */
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
            'actions' => $recording->actions->map(fn($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'source_items' => $a->source_items,
                'payload' => $a->payload,
                'status' => $a->status,
                'created_at' => $a->created_at->toISOString(),
            ])->toArray(),
        ];
    }

    public function show(Request $request, int $id): Response
    {
        $recording = Recording::with('actions')->findOrFail($id);

        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Notes/Show', [
            'recording' => $this->formatRecording($recording),
        ]);
    }

    public function updateOverride(StoreNoteOverrideRequest $request, int $id): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
        }

        $meta = $recording->ai_meta ?? [];
        $meta['user_overrides'] = [
            'title' => $request->input('title'),
            'summary' => $request->input('summary'),
            'action_items' => $request->input('action_items'),
        ];
        $meta['edited_at'] = now()->toISOString();

        $recording->update(['ai_meta' => $meta]);

        return response()->json([
            'success' => true,
            'ai_meta' => $recording->ai_meta,
        ]);
    }

    public function updateActionState(StoreActionStateRequest $request, int $id): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
        }

        $meta = $recording->ai_meta ?? [];
        $meta['action_state'] = $request->input('state');

        $recording->update(['ai_meta' => $meta]);

        return response()->json([
            'success' => true,
            'ai_meta' => $recording->ai_meta,
        ]);
    }
}
