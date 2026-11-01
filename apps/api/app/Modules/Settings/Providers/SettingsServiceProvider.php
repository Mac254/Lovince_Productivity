<?php

namespace App\Modules\Settings\Providers;

use App\Modules\Settings\Services\SettingsRegistry;
use App\Shared\Support\ModuleProvider;

class SettingsServiceProvider extends ModuleProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(SettingsRegistry::class);
    }
}
