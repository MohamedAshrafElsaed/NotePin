<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $recordings = $request->user()
            ->recordings()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'status' => $r->status,
                'duration_seconds' => $r->duration_seconds,
                'ai_title' => $r->ai_title,
                'ai_summary' => $r->ai_summary,
                'ai_action_items' => $r->ai_action_items,
                'created_at' => $r->created_at->toISOString(),
            ]);

        return Inertia::render('Dashboard', [
            'recordings' => $recordings,
        ]);
    }
}
