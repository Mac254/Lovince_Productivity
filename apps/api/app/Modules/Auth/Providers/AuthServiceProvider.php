<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Listeners\LogAuthEvents;
use App\Shared\Support\ModuleProvider;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

class AuthServiceProvider extends ModuleProvider
{
    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        Event::listen(Login::class, [LogAuthEvents::class, 'handleLogin']);
        Event::listen(Failed::class, [LogAuthEvents::class, 'handleFailed']);
        Event::listen(Lockout::class, [LogAuthEvents::class, 'handleLockout']);
        Event::listen(Logout::class, [LogAuthEvents::class, 'handleLogout']);
    }
}
