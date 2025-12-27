<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecordingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|mimes:webm,mp3,wav,ogg,m4a|max:51200',
            'duration' => 'nullable|integer|min:0',
        ]);

        $file = $request->file('audio');
        $path = $file->store('recordings', 'public');

        $recording = Recording::create([
            'user_id' => auth()->id(),
            'status' => 'uploaded',
            'audio_path' => $path,
            'duration_seconds' => $request->input('duration'),
        ]);

        return response()->json([
            'id' => $recording->id,
            'status' => $recording->status,
        ]);
    }

    public function show(int $id): Response
    {
        $recording = Recording::findOrFail($id);

        return Inertia::render('Notes/Show', [
            'recording' => $this->formatRecording($recording),
        ]);
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
}
