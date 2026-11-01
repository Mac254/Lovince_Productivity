<?php

namespace App\Providers;

/**
 * Every permission the modules declare in `Permissions/permissions.php`, named `module.action`.
 * A module may only declare permissions under its own prefix, and each name exists once, so the
 * full list can be seeded and synced to a permission store when roles arrive.
 */
final class PermissionRegistry
{
    /** @var array<string, list<string>>|null Module name => its permissions. */
    private ?array $permissions = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * @return array<string, list<string>>
     */
    public function byModule(): array
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }

        $this->permissions = [];
        $seen = [];
        foreach ($this->modules->files('Permissions/permissions.php') as $module => $file) {
            $prefix = self::prefix($module);
            $declared = require $file;

            foreach ($declared as $permission) {
                if (! is_string($permission) || ! preg_match('/^[a-z_]+\.[a-z_]+$/', $permission)) {
                    throw new \LogicException("The {$module} module declares an invalid permission name.");
                }
                if (! str_starts_with($permission, "{$prefix}.")) {
                    throw new \LogicException("The {$module} module may only declare {$prefix}.* permissions, not [{$permission}].");
                }
                if (isset($seen[$permission])) {
                    throw new \LogicException("The permission [{$permission}] is declared twice.");
                }
                $seen[$permission] = true;
            }

            $this->permissions[$module] = array_values($declared);
        }

        return $this->permissions;
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_merge(...array_values($this->byModule()));
    }

    /** `TaskTypes` → `task_types`. */
    public static function prefix(string $module): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $module));
    }
}
