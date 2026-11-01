<?php

namespace App\Providers;

use Illuminate\Support\Str;
use ReflectionClass;

/**
 * The business modules in app/Modules. A folder with `Providers/<Module>ServiceProvider.php` is an
 * active module; removing the folder (or its provider) takes the module out of the app.
 * Also finds what modules declare by convention, such as their settings and permissions.
 */
final class ModuleRegistry
{
    /** @var array<string, string>|null Module name => directory. */
    private ?array $modules = null;

    public function __construct(
        private readonly string $path,
        private readonly string $namespace = 'App\\Modules\\',
    ) {}

    /**
     * @return array<string, string> Module name => directory, by name.
     */
    public function modules(): array
    {
        if ($this->modules === null) {
            $this->modules = [];
            foreach (glob($this->path.'/*/Providers/*ServiceProvider.php') ?: [] as $file) {
                $directory = dirname($file, 2);
                $name = basename($directory);
                if (basename($file) === "{$name}ServiceProvider.php") {
                    $this->modules[$name] = $directory;
                }
            }
            ksort($this->modules);
        }

        return $this->modules;
    }

    /**
     * @return list<class-string>
     */
    public function providers(): array
    {
        return array_map(fn (string $name) => "{$this->namespace}{$name}\\Providers\\{$name}ServiceProvider", array_keys($this->modules()));
    }

    /**
     * Concrete subclasses of `$parent` in one folder of every module, such as each module's `Settings`.
     *
     * @template T of object
     *
     * @param  class-string<T>  $parent
     * @return list<class-string<T>>
     */
    public function classesIn(string $folder, string $parent): array
    {
        $classes = [];
        foreach ($this->modules() as $name => $directory) {
            foreach (glob("{$directory}/{$folder}/*.php") ?: [] as $file) {
                $class = $this->namespace.$name.'\\'.str_replace('/', '\\', $folder).'\\'.Str::beforeLast(basename($file), '.php');
                if (class_exists($class) && is_subclass_of($class, $parent) && ! (new ReflectionClass($class))->isAbstract()) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    /**
     * One file by its path inside each module, for the modules that have it.
     *
     * @return array<string, string> Module name => file.
     */
    public function files(string $relative): array
    {
        $files = [];
        foreach ($this->modules() as $name => $directory) {
            if (is_file("{$directory}/{$relative}")) {
                $files[$name] = "{$directory}/{$relative}";
            }
        }

        return $files;
    }
}
