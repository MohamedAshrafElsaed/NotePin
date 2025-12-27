<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AnonymousDataLinker;
use App\Services\EventTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put('auth_redirect', $request->input('redirect', '/dashboard'));
        $request->session()->put('auth_action', $request->input('action'));
        $request->session()->put('auth_anonymous_id', $request->input('anonymous_id'));

        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Authentication failed.');
        }

        $user = User::where('provider', 'google')
            ->where('provider_id', $googleUser->getId())
            ->first();

        if (!$user) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'avatar' => $googleUser->getAvatar(),
                    'provider' => 'google',
                    'provider_id' => $googleUser->getId(),
                    'first_seen_at' => now(),
                    'last_active_at' => now(),
                ]);
            }
        }

        $user->update(['last_active_at' => now()]);

        Auth::login($user, true);

        $anonymousId = $request->session()->pull('auth_anonymous_id');
        if ($anonymousId) {
            app(AnonymousDataLinker::class)->link($anonymousId, $user->id);
        }

        EventTracker::track('auth_completed', [
            'user_id' => $user->id,
            'metadata' => [
                'provider' => 'google',
                'action' => $request->session()->pull('auth_action'),
            ],
        ]);

        $redirect = $request->session()->pull('auth_redirect', '/dashboard');

        return redirect($redirect)->with('auth_success', true);
    }
}
