<?php

namespace App\Modules\Suggestions\Console\Commands;

use App\Modules\Suggestions\Services\IdeaPool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('suggestions:fetch-ideas')]
#[Description('Add a few fresh ideas from the internet feed to the shared suggestions pool')]
class FetchIdeas extends Command
{
    public function handle(IdeaPool $pool): int
    {
        $added = $pool->fill();
        $this->components->info("Added {$added} ".str('idea')->plural($added).' to the pool.');

        return self::SUCCESS;
    }
}
