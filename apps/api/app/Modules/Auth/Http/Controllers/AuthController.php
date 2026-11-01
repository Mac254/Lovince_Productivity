<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Services\AuthSession;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Users\Http\Resources\UserResource;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        // Session sign-in only exists for the first-party web app.
        abort_unless($request->hasSession(), 403, 'Sign in through the Focus web app.');

        $request->authenticate();
        $request->session()->regenerate();

        if ($request->boolean('remember')) {
            AuthSession::markRemembered($request);
        }

        return $this->me($request);
    }

    /**
     * The signed-in user, the state of this device's sign-in and their settings, so the app
     * applies them (theme, start page) before the first page renders.
     */
    public function me(Request $request): JsonResponse
    {
        return UserResource::make($request->user())
            ->additional(['meta' => [
                'session' => AuthSession::describe($request),
                'settings' => app(SettingsRegistry::class)->resolve($request->user()->settings),
            ]])
            ->response();
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
