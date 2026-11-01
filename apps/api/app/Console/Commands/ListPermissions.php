<?php

namespace App\Console\Commands;

use App\Providers\PermissionRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('focus:permissions {--json : Print the names as a JSON list, for seeding a permission store}')]
#[Description('List the permissions every module declares in its Permissions/permissions.php')]
class ListPermissions extends Command
{
    public function handle(PermissionRegistry $permissions): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($permissions->all(), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        foreach ($permissions->byModule() as $module => $names) {
            $this->components->twoColumnDetail("<options=bold>{$module}</>", implode(', ', $names));
        }

        return self::SUCCESS;
    }
}
