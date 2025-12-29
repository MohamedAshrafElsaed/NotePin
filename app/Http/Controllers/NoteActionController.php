<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoteActionRequest;
use App\Models\NoteAction;
use App\Models\Recording;
use App\Services\AnonymousUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteActionController extends Controller
{
    public function store(StoreNoteActionRequest $request, int $id): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
        }

        $payload = $this->buildPayload($request);

        $action = NoteAction::create([
            'recording_id' => $recording->id,
            'type' => $request->input('type'),
            'source_items' => $request->input('selected_items'),
            'payload' => $payload,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'action' => $this->formatAction($action),
        ]);
    }

    public function update(Request $request, int $id, int $actionId): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
        }

        $action = NoteAction::where('recording_id', $recording->id)
            ->where('id', $actionId)
            ->firstOrFail();

        $request->validate([
            'status' => 'required|in:open,done,cancelled',
        ]);

        $action->update([
            'status' => $request->input('status'),
        ]);

        return response()->json([
            'success' => true,
            'action' => $this->formatAction($action),
        ]);
    }

    public function destroy(Request $request, int $id, int $actionId): JsonResponse
    {
        $recording = Recording::findOrFail($id);

        if (!$this->authorizeRecording($recording, $request)) {
            abort(403, 'Unauthorized');
        }

        $action = NoteAction::where('recording_id', $recording->id)
            ->where('id', $actionId)
            ->firstOrFail();

        $action->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(StoreNoteActionRequest $request): array
    {
        $type = $request->input('type');
        $payload = ['title' => $request->input('title')];

        if ($type === 'task') {
            $payload['due_date'] = $request->input('due_date');
            $payload['priority'] = $request->input('priority', 'medium');
        }

        if ($type === 'meeting') {
            $payload['date'] = $request->input('date');
            $payload['time'] = $request->input('time');
            $payload['duration_minutes'] = $request->input('duration_minutes', 30);
            $payload['attendees'] = $request->input('attendees');
        }

        if ($type === 'reminder') {
            $payload['remind_at'] = $request->input('remind_at');
            $payload['reminder_note'] = $request->input('reminder_note');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAction(NoteAction $action): array
    {
        return [
            'id' => $action->id,
            'type' => $action->type,
            'source_items' => $action->source_items,
            'payload' => $action->payload,
            'status' => $action->status,
            'created_at' => $action->created_at->toISOString(),
        ];
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
