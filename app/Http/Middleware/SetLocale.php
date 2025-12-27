<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected array $supportedLocales = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        // Priority: URL param > Session > Cookie > Browser > Default
        $locale = config('app.locale');


        if (in_array($locale, $this->supportedLocales)) {
            App::setLocale($locale);
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    protected function getBrowserLocale(Request $request): ?string
    {
        $browserLocale = $request->getPreferredLanguage($this->supportedLocales);

        return $browserLocale ? substr($browserLocale, 0, 2) : null;
    }
}
