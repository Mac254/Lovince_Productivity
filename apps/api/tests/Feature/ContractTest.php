<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContractTest extends TestCase
{
    public function test_shared_typescript_contract_matches_the_laravel_enums(): void
    {
        $this->artisan('focus:types', ['--check' => true])->assertSuccessful();
    }
}
