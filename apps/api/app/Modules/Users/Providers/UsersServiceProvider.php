<?php

namespace App\Modules\Users\Providers;

use App\Modules\Users\Console\Commands\CreateUser;
use App\Shared\Support\ModuleProvider;
use Illuminate\Validation\Rules\Password;

class UsersServiceProvider extends ModuleProvider
{
    public function boot(): void
    {
        parent::boot();

        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers());

        $this->commands([CreateUser::class]);
    }
}
