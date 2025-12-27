<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRecordingWithAI;
use App\Models\Recording;
use Illuminate\Http\JsonResponse;

class RecordingProcessController extends Controller
{
    public function store(Recording $recording): JsonResponse
    {
        if (!in_array($recording->status, ['uploaded', 'failed'])) {
            return response()->json([
                'error' => 'Recording cannot be processed in current state.',
            ], 422);
        }

        $recording->update(['status' => 'processing']);

        ProcessRecordingWithAI::dispatch($recording->id);

        return response()->json([
            'id' => $recording->id,
            'status' => $recording->status,
        ]);
    }
}
