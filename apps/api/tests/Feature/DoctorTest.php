<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_passes_on_a_migrated_database(): void
    {
        $this->artisan('focus:doctor')
            ->expectsOutputToContain('Database migrated')
            ->assertSuccessful();
    }

    public function test_doctor_fails_and_explains_when_the_database_is_unreachable(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => storage_path('framework/testing/missing.sqlite'),
        ]);

        $this->artisan('focus:doctor')
            ->expectsOutputToContain('touch database/database.sqlite')
            ->assertFailed();
    }
}
