<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ForeignKeyIndexTest extends TestCase
{
    use RefreshDatabase;

    /** SQLite doesn't index foreign keys itself: lookups and cascades on an unindexed one scan the table. */
    public function test_every_foreign_key_leads_an_index(): void
    {
        $missing = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            $leading = collect(Schema::getIndexes($table))->map(fn (array $index) => $index['columns'][0])->all();
            foreach (Schema::getForeignKeys($table) as $key) {
                $column = $key['columns'][0];
                if (! in_array($column, $leading, true)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        $this->assertSame([], $missing);
    }
}
