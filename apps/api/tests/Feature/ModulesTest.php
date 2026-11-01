<?php

namespace Tests\Feature;

use App\Providers\ModuleRegistry;
use App\Providers\PermissionRegistry;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    public function test_every_module_is_registered_once_and_documented(): void
    {
        $modules = $this->app->make(ModuleRegistry::class);

        $this->assertSame(
            ['Auth', 'Blockers', 'Clock', 'Dashboard', 'Goals', 'Journal', 'Metrics', 'Quotes', 'Reports', 'Reviews', 'Settings', 'Tasks', 'Users'],
            array_keys($modules->modules()),
        );

        foreach ($modules->providers() as $provider) {
            $this->assertCount(1, $this->app->getProviders($provider), $provider);
        }

        foreach ($modules->modules() as $name => $directory) {
            $this->assertFileExists("{$directory}/README.md", "{$name} has no README.");
        }
    }

    public function test_permissions_are_aggregated_once_under_their_own_module(): void
    {
        $permissions = $this->app->make(PermissionRegistry::class);

        $all = $permissions->all();
        $this->assertSame(array_unique($all), $all);
        $this->assertContains('tasks.view', $all);

        foreach ($permissions->byModule() as $module => $names) {
            foreach ($names as $name) {
                $this->assertStringStartsWith(PermissionRegistry::prefix($module).'.', $name);
            }
        }

        $this->artisan('focus:permissions', ['--json' => true])->expectsOutput(json_encode($all))->assertSuccessful();
    }

    public function test_a_module_cannot_declare_another_modules_permissions(): void
    {
        $path = sys_get_temp_dir().'/focus-modules-'.uniqid();
        mkdir("{$path}/Notes/Providers", 0755, true);
        mkdir("{$path}/Notes/Permissions");
        touch("{$path}/Notes/Providers/NotesServiceProvider.php");
        file_put_contents("{$path}/Notes/Permissions/permissions.php", "<?php return ['tasks.delete'];");

        try {
            $this->expectExceptionMessage('may only declare notes.* permissions');
            (new PermissionRegistry(new ModuleRegistry($path)))->all();
        } finally {
            unlink("{$path}/Notes/Permissions/permissions.php");
            unlink("{$path}/Notes/Providers/NotesServiceProvider.php");
            rmdir("{$path}/Notes/Permissions");
            rmdir("{$path}/Notes/Providers");
            rmdir("{$path}/Notes");
            rmdir($path);
        }
    }
}
