<?php

namespace App\Modules\Users\Providers;

use App\Modules\Users\Console\Commands\CreateUser;
use App\Modules\Users\Models\User;
use App\Modules\Users\Services\AccountTimezone;
use App\Shared\Support\ModuleProvider;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rules\Password;

class UsersServiceProvider extends ModuleProvider
{
    public function boot(): void
    {
        parent::boot();

        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers());

        $this->commands([CreateUser::class]);

        Event::listen(Authenticated::class, function (Authenticated $event) {
            if ($event->user instanceof User) {
                AccountTimezone::apply($event->user);
            }
        });
    }
}
