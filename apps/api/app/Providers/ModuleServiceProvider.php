<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registers each active module's service provider, once. Each module provider then loads its own
 * routes, config, migrations, policies and listeners.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn () => new ModuleRegistry(app_path('Modules')));
        $this->app->singleton(PermissionRegistry::class);

        foreach ($this->app->make(ModuleRegistry::class)->providers() as $provider) {
            $this->app->register($provider);
        }
    }
}
