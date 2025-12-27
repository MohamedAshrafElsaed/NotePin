<?php

namespace App\Http\Middleware;

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

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'locale' => fn () => app()->getLocale(),
            'locales' => ['ar', 'en'],
            'translations' => fn () => $this->getTranslations(),
        ];
    }

    protected function getTranslations(): array
    {
        $locale = app()->getLocale();
        $path = lang_path("{$locale}.json");

        if (File::exists($path)) {
            return json_decode(File::get($path), true) ?? [];
        }

        // Fallback: load from PHP files
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
