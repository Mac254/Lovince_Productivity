<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * What the client needs to know about the current sign-in: whether this device is remembered
 * and when the sign-in lapses if Focus isn't used. Credentials themselves never leave the cookie.
 */
final class AuthSession
{
    public const REMEMBER_UNTIL = 'auth.remember_until';

    public static function rememberMinutes(): int
    {
        return (int) config('auth.guards.web.remember', 30 * 1440);
    }

    public static function markRemembered(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::REMEMBER_UNTIL, now()->addMinutes(self::rememberMinutes())->toIso8601String());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(Request $request): array
    {
        // Signed in from the remember cookie on this request: it is being renewed for a full period.
        if (Auth::guard('web')->viaRemember()) {
            self::markRemembered($request);
        }

        $rememberUntil = $request->hasSession() ? $request->session()->get(self::REMEMBER_UNTIL) : null;
        $idleMinutes = (int) config('session.lifetime');

        return [
            'remembered' => $rememberUntil !== null,
            // Remembered: until the remember cookie lapses. Otherwise: after this much inactivity.
            'expires_at' => $rememberUntil ?? now()->addMinutes($idleMinutes)->toIso8601String(),
            'idle_timeout_minutes' => $idleMinutes,
            'remember_days' => intdiv(self::rememberMinutes(), 1440),
        ];
    }

    public static function expiresAt(Request $request): Carbon
    {
        return Carbon::parse(self::describe($request)['expires_at']);
    }
}
