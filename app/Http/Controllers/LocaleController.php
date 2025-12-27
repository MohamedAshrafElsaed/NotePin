<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    protected array $supportedLocales = ['ar', 'en'];

    public function update(Request $request, string $locale): RedirectResponse
    {
        if (!in_array($locale, $this->supportedLocales)) {
            abort(400, 'Unsupported locale');
        }

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        // Set cookie for persistence (1 year)
        $cookie = cookie('locale', $locale, 60 * 24 * 365);

        return redirect()
            ->back()
            ->withCookie($cookie);
    }
}
