<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AnonymousDataLinker;
use App\Services\EventTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailAuthController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'redirect' => 'nullable|string|max:255',
            'action' => 'nullable|string|max:50',
            'anonymous_id' => 'nullable|string|max:64',
        ]);

        $email = $request->input('email');
        $redirect = $request->input('redirect', '/dashboard');
        $action = $request->input('action');
        $anonymousId = $request->input('anonymous_id');

        $signedUrl = URL::temporarySignedRoute(
            'auth.email.callback',
            now()->addMinutes(15),
            [
                'email' => $email,
                'redirect' => $redirect,
                'action' => $action,
                'anonymous_id' => $anonymousId,
            ]
        );

        Mail::raw(
            "Click this link to sign in to NotePin:\n\n{$signedUrl}\n\nThis link expires in 15 minutes.",
            function ($message) use ($email) {
                $message->to($email)
                    ->subject('Sign in to NotePin');
            }
        );

        return response()->json(['success' => true]);
    }

    public function callback(Request $request): RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            return redirect('/')->with('error', 'Invalid or expired link.');
        }

        $email = $request->query('email');
        $redirect = $request->query('redirect', '/dashboard');
        $action = $request->query('action');
        $anonymousId = $request->query('anonymous_id');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'email' => $email,
                'provider' => 'email',
                'first_seen_at' => now(),
                'last_active_at' => now(),
            ]);
        } else {
            if (!$user->provider) {
                $user->update(['provider' => 'email']);
            }
            $user->update(['last_active_at' => now()]);
        }

        Auth::login($user, true);

        if ($anonymousId) {
            app(AnonymousDataLinker::class)->link($anonymousId, $user->id);
        }

        EventTracker::track('auth_completed', [
            'user_id' => $user->id,
            'metadata' => [
                'provider' => 'email',
                'action' => $action,
            ],
        ]);

        return redirect($redirect)->with('auth_success', true);
    }
}
