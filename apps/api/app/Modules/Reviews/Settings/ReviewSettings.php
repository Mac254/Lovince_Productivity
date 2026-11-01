<?php

namespace App\Modules\Reviews\Settings;

use App\Shared\Support\Settings\Setting;
use App\Shared\Support\Settings\SettingsModule;

class ReviewSettings extends SettingsModule
{
    public function key(): string
    {
        return 'review';
    }

    public function label(): string
    {
        return 'Weekly review';
    }

    public function order(): int
    {
        return 45;
    }

    public function settings(): array
    {
        return [
            Setting::choice('day', 'Review day', [
                'off' => 'Don’t remind me',
                'friday' => 'Friday',
                'saturday' => 'Saturday',
                'sunday' => 'Sunday',
            ], 'friday', 'The dashboard reminds you to review the week from this day until it’s done.'),
        ];
    }
}
