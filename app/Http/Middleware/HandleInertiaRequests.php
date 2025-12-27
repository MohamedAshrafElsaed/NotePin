<?php

namespace App\Http\Middleware;

use App\Models\Recording;
use App\Services\AnonymousUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $anonymousId = $request->cookie(AnonymousUserResolver::getCookieName());

        $recordingCount = 0;
        if ($user) {
            $recordingCount = Recording::where('user_id', $user->id)->count();
        } elseif ($anonymousId) {
            $recordingCount = Recording::where('anonymous_id', $anonymousId)->count();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                ] : null,
                'isAuthenticated' => (bool) $user,
                'recordingCount' => $recordingCount,
            ],
            'locale' => app()->getLocale(),
            'locales' => ['ar', 'en'],
            'translations' => $this->getTranslations(),
            'flash' => [
                'auth_success' => $request->session()->get('auth_success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    protected function getTranslations(): array
    {
        $locale = app()->getLocale();
        $path = lang_path("{$locale}.json");

        if (File::exists($path)) {
            return json_decode(File::get($path), true) ?? [];
        }

        // Fallback to English
        $fallbackPath = lang_path('en.json');
        if (File::exists($fallbackPath)) {
            return json_decode(File::get($fallbackPath), true) ?? [];
        }

        return [];
    }
}
