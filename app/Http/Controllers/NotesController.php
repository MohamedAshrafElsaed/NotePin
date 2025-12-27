<?php

namespace App\Http\Controllers;

use App\Models\Recording;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotesController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $recordings = Recording::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['ready', 'processing', 'uploaded', 'failed'])
            ->orderByDesc('created_at')
            ->get();

        $notes = $recordings->map(function ($recording) {
            $actionItems = $recording->ai_action_items ?? [];

            return [
                'id' => $recording->id,
                'title' => $recording->ai_title ?? '',
                'summary' => $recording->ai_summary ?? '',
                'actionItemsCount' => count($actionItems),
                'completedCount' => 0,
                'duration' => $this->formatDuration($recording->duration_seconds),
                'createdAt' => $recording->created_at->toISOString(),
                'status' => $recording->status,
            ];
        })->toArray();

        return Inertia::render('Notes/Index', [
            'notes' => $notes,
        ]);
    }

    private function formatDuration(?int $seconds): string
    {
        if (!$seconds) {
            return '00:00';
        }

        $mins = floor($seconds / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d', $mins, $secs);
    }
}
