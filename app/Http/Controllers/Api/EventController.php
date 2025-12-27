<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'metadata' => 'nullable|array',
        ]);

        $allowedEvents = [
            'auth_prompt_shown',
        ];

        $name = $request->input('name');

        if (!in_array($name, $allowedEvents)) {
            return response()->json(['error' => 'Invalid event'], 400);
        }

        EventTracker::track($name, [
            'metadata' => $request->input('metadata', []),
        ]);

        return response()->json(['success' => true]);
    }
}
