<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\DailyReportGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Stores one user's report for one day. Queued so the nightly run never blocks the app,
 * retried with backoff if the database is busy, and unique per user and day.
 */
class GenerateDailyReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $uniqueFor = 3600;

    public function __construct(public User $user, public string $date) {}

    public function uniqueId(): string
    {
        return "{$this->user->id}:{$this->date}";
    }

    public function handle(DailyReportGenerator $generator): void
    {
        $generator->generate($this->user, Carbon::createFromFormat('!Y-m-d', $this->date));
    }
}
