<?php

namespace App\Http\Middleware;

use App\Support\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sliding "keep me signed in": when a request is signed in from the remember cookie (the session
 * had expired), reissue that cookie for a full period and record the new expiry in the session.
 * The cookie stays HttpOnly and encrypted; logout still clears it and rotates the remember token.
 */
class RenewRememberedLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $guard = Auth::guard('web');
        if ($guard->check() && $guard->viaRemember()) {
            $name = $guard->getRecallerName();
            $value = $request->cookies->get($name);

            if (is_string($value) && $value !== '') {
                Cookie::queue(Cookie::make($name, $value, AuthSession::rememberMinutes()));
                AuthSession::markRemembered($request);
            }
        }

        return $response;
    }
}
