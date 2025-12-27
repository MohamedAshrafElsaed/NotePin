<?php

namespace App\Http\Middleware;

use App\Models\Recording;
use App\Services\AnonymousUserResolver;
use Illuminate\Foundation\Inspiring;
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
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

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
            'quote' => ['message' => trim($message), 'author' => trim($author)],
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
            'locale' => fn () => app()->getLocale(),
            'locales' => ['ar', 'en'],
            'translations' => fn () => $this->getTranslations(),
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

        $phpPath = lang_path("{$locale}");
        if (File::isDirectory($phpPath)) {
            $translations = [];
            foreach (File::files($phpPath) as $file) {
                $key = $file->getFilenameWithoutExtension();
                $translations[$key] = require $file->getPathname();
            }
            return $translations;
        }

        return [];
    }
}
