<?php

namespace App\Settings;

/**
 * A group of settings owned by one part of the app. Subclasses in `App\Settings\Modules`
 * are found automatically and joined into the settings API; adding a module is the whole job.
 */
abstract class SettingsModule
{
    /** The module's key in the API and in stored settings, such as `appearance`. */
    abstract public function key(): string;

    abstract public function label(): string;

    public function description(): ?string
    {
        return null;
    }

    /** Where the module sits on the Settings page; lower comes first. */
    public function order(): int
    {
        return 100;
    }

    /**
     * @return list<Setting>
     */
    abstract public function settings(): array;
}
