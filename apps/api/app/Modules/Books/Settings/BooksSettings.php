<?php

namespace App\Modules\Books\Settings;

use App\Shared\Support\Settings\Setting;
use App\Shared\Support\Settings\SettingsModule;

class BooksSettings extends SettingsModule
{
    public function key(): string
    {
        return 'books';
    }

    public function label(): string
    {
        return 'Books';
    }

    public function order(): int
    {
        return 60;
    }

    public function settings(): array
    {
        return [
            Setting::toggle('show_in_sidebar', 'Show in the sidebar', true, 'Hide Books from the sidebar when you’re not using it. The page stays reachable from search.'),
            Setting::choice('start_tab', 'Open Books on', [
                'reading' => 'Reading',
                'to_read' => 'To read',
                'to_find' => 'To find',
                'finished' => 'Finished',
            ], 'reading', 'The tab you see first on the Books page.'),
            Setting::toggle('show_in_insights', 'Reading in reports and metrics', true, 'Show the reading card on daily reports and the metrics page.'),
        ];
    }
}
