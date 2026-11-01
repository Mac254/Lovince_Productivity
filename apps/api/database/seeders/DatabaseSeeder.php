<?php

namespace Database\Seeders;

use App\Modules\Quotes\Database\Seeders\QuoteSeeder;
use App\Modules\Tasks\Database\Seeders\DemoSeeder;
use App\Modules\Users\Database\Seeders\OwnerSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Runs the module seeders. Quotes always; your account from FOCUS_OWNER_* in .env; sample data locally when FOCUS_SEED_DEMO=true.
     * Every seeder is safe to run again.
     */
    public function run(): void
    {
        $this->call(QuoteSeeder::class);
        $this->call(OwnerSeeder::class);

        if (config('focus.seed_demo')) {
            $this->call(DemoSeeder::class);
        }
    }
}
