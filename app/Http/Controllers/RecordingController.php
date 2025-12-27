<?php

namespace App\Http\Controllers;

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
                'audio' => 'required|file|mimes:webm,mp3,wav,ogg,m4a|max:51200',
                'duration' => 'nullable|integer|min:0',
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

            $recording = Recording::create([
                'user_id' => $userId,
                'anonymous_id' => $anonymousId,
                'status' => 'uploaded',
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

    public function json(Recording $recording): JsonResponse
    {
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

    public function show(int $id): Response
    {
        $recording = Recording::findOrFail($id);

        return Inertia::render('Notes/Show', [
            'recording' => $this->formatRecording($recording),
        ]);
    }
}
