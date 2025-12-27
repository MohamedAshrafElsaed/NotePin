<?php

namespace App\Services;

use App\Models\Recording;

class AnonymousDataLinker
{
    public function link(string $anonymousId, int $userId): int
    {
        $recordings = Recording::where('anonymous_id', $anonymousId)
            ->whereNull('user_id')
            ->get();

        $count = $recordings->count();

        if ($count > 0) {
            Recording::where('anonymous_id', $anonymousId)
                ->whereNull('user_id')
                ->update([
                    'user_id' => $userId,
                    'anonymous_id' => null,
                ]);

            EventTracker::track('anonymous_linked', [
                'user_id' => $userId,
                'metadata' => [
                    'recordings_linked' => $count,
                    'anonymous_id' => $anonymousId,
                ],
            ]);
        }

        return $count;
    }
}
