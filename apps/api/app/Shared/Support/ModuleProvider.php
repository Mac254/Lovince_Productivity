<?php

namespace App\Shared\Support;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base for a module's service provider. Loads what the module has by convention: `Config/*.php`
 * (merged under the file's name), `Database/Migrations`, `Routes/api.php` (under `/api/v1` with the
 * `api` middleware group) and the policies listed in `$policies`.
 */
abstract class ModuleProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> Model => policy. */
    protected array $policies = [];

    public function register(): void
    {
        foreach (glob($this->modulePath('Config/*.php')) ?: [] as $file) {
            $this->mergeConfigFrom($file, basename($file, '.php'));
        }
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        if (is_dir($this->modulePath('Database/Migrations'))) {
            $this->loadMigrationsFrom($this->modulePath('Database/Migrations'));
        }

        if (is_file($this->modulePath('Routes/api.php')) && ! $this->app->routesAreCached()) {
            Route::middleware('api')->prefix('api/v1')->group($this->modulePath('Routes/api.php'));
        }
    }

    protected function modulePath(string $path = ''): string
    {
        return dirname((string) (new ReflectionClass(static::class))->getFileName(), 2).($path === '' ? '' : "/{$path}");
    }
}
