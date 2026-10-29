<?php

namespace App\Settings;

use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Every settings module, found by scanning `app/Settings/Modules`, and the operations on the
 * whole set: defaults, validation rules, resolving stored values and describing it for the API.
 * Stored settings hold only what differs from the defaults, so changing a default reaches
 * everyone who never changed that setting.
 */
class SettingsRegistry
{
    /** @var array<string, SettingsModule> */
    private array $modules;

    /**
     * @param  list<class-string<SettingsModule>>|null  $modules  Defaults to every module in app/Settings/Modules.
     */
    public function __construct(?array $modules = null)
    {
        $found = array_map(fn (string $class) => new $class, $modules ?? self::discover());
        usort($found, fn (SettingsModule $a, SettingsModule $b) => [$a->order(), $a->key()] <=> [$b->order(), $b->key()]);

        $this->modules = [];
        foreach ($found as $module) {
            if (isset($this->modules[$module->key()])) {
                throw new \LogicException("Two settings modules use the key [{$module->key()}].");
            }
            $this->modules[$module->key()] = $module;
        }
    }

    /**
     * @return list<class-string<SettingsModule>>
     */
    public static function discover(): array
    {
        $classes = [];
        foreach (glob(app_path('Settings/Modules/*.php')) ?: [] as $file) {
            $class = 'App\\Settings\\Modules\\'.Str::beforeLast(basename($file), '.php');
            if (class_exists($class) && is_subclass_of($class, SettingsModule::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @return array<string, SettingsModule>
     */
    public function modules(): array
    {
        return $this->modules;
    }

    public function has(string $module, ?string $key = null): bool
    {
        return isset($this->modules[$module]) && ($key === null || $this->setting($module, $key) !== null);
    }

    public function setting(string $module, string $key): ?Setting
    {
        foreach (($this->modules[$module] ?? null)?->settings() ?? [] as $setting) {
            if ($setting->key === $key) {
                return $setting;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, bool|string>>
     */
    public function defaults(): array
    {
        return array_map(
            fn (SettingsModule $module) => collect($module->settings())->mapWithKeys(fn (Setting $s) => [$s->key => $s->default])->all(),
            $this->modules,
        );
    }

    /**
     * Validation rules for a partial update shaped `{ module: { key: value } }`.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->modules as $name => $module) {
            $rules[$name] = ['sometimes', 'array'];
            foreach ($module->settings() as $setting) {
                $rules["{$name}.{$setting->key}"] = $setting->rules();
            }
        }

        return $rules;
    }

    /**
     * Every setting's value: what is stored where it is still valid, the default elsewhere.
     *
     * @param  array<string, mixed>|null  $stored
     * @return array<string, array<string, bool|string>>
     */
    public function resolve(?array $stored): array
    {
        $values = $this->defaults();
        foreach ($values as $name => $settings) {
            foreach (array_keys($settings) as $key) {
                $value = $stored[$name][$key] ?? null;
                if ($value !== null && $this->setting($name, $key)?->accepts($value)) {
                    $values[$name][$key] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * Applies validated changes and keeps only what differs from the defaults, dropping settings
     * no module declares any more.
     *
     * @param  array<string, mixed>|null  $stored
     * @param  array<string, array<string, mixed>>  $changes
     * @return array<string, array<string, bool|string>>
     */
    public function merge(?array $stored, array $changes): array
    {
        $values = array_replace_recursive($this->resolve($stored), $changes);

        return $this->compact($values);
    }

    /**
     * Puts one module, or every module, back to its defaults.
     *
     * @param  array<string, mixed>|null  $stored
     * @return array<string, array<string, bool|string>>
     */
    public function reset(?array $stored, ?string $module = null): array
    {
        $values = $this->resolve($stored);
        $defaults = $this->defaults();

        return $this->compact($module === null ? $defaults : array_replace($values, [$module => $defaults[$module]]));
    }

    /**
     * Modules with their settings and current values, in page order.
     *
     * @param  array<string, mixed>|null  $stored
     * @return list<array<string, mixed>>
     */
    public function describe(?array $stored): array
    {
        $values = $this->resolve($stored);

        return array_values(array_map(fn (SettingsModule $module) => array_filter([
            'key' => $module->key(),
            'label' => $module->label(),
            'description' => $module->description(),
            'settings' => array_map(
                fn (Setting $setting) => $setting->describe($values[$module->key()][$setting->key]),
                $module->settings(),
            ),
        ], fn ($item) => $item !== null), $this->modules));
    }

    /**
     * @param  array<string, array<string, mixed>>  $values
     * @return array<string, array<string, bool|string>>
     */
    private function compact(array $values): array
    {
        $defaults = $this->defaults();
        $kept = [];
        foreach ($defaults as $name => $settings) {
            foreach ($settings as $key => $default) {
                $value = $values[$name][$key] ?? $default;
                if ($value !== $default) {
                    $kept[$name][$key] = $value;
                }
            }
        }

        return $kept;
    }
}
