<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnonymousUserResolver
{
    protected const COOKIE_NAME = 'notepin_anon_id';
    protected const COOKIE_LIFETIME = 60 * 24 * 365; // 1 year

    public static function resolve(Request $request): string
    {
        $anonymousId = $request->cookie(self::COOKIE_NAME);

        if (!$anonymousId) {
            $anonymousId = Str::uuid()->toString();
        }

        return $anonymousId;
    }

    public static function getCookieName(): string
    {
        return self::COOKIE_NAME;
    }

    public static function getCookieLifetime(): int
    {
        return self::COOKIE_LIFETIME;
    }

    public static function makeCookie(string $anonymousId): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            self::COOKIE_NAME,
            $anonymousId,
            self::COOKIE_LIFETIME,
            '/',
            null,
            true,
            true,
            false,
            'Lax'
        );
    }
}
