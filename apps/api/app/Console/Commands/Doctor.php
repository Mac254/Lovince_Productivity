<?php

namespace App\Console\Commands;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('focus:doctor')]
#[Description('Check that PHP, the database and the app are ready to run Focus')]
class Doctor extends Command
{
    private const DRIVER_EXTENSIONS = [
        'sqlite' => ['pdo_sqlite', 'php8.4-sqlite3'],
        'mysql' => ['pdo_mysql', 'php8.4-mysql'],
        'mariadb' => ['pdo_mysql', 'php8.4-mysql'],
        'pgsql' => ['pdo_pgsql', 'php8.4-pgsql'],
    ];

    private int $failures = 0;

    public function handle(): int
    {
        $this->check('PHP '.PHP_VERSION, version_compare(PHP_VERSION, '8.3.0', '>='), 'Focus needs PHP 8.3 or newer.');
        $this->check('Application key', filled(config('app.key')), 'Run `php artisan key:generate`.');

        $connection = config('database.default');
        [$extension, $package] = self::DRIVER_EXTENSIONS[$connection] ?? [null, null];

        if ($extension && ! $this->check(
            "PHP {$extension} extension for the {$connection} database",
            extension_loaded($extension),
            "Install it, e.g. `sudo apt install {$package}`, then restart `php artisan serve`.",
        )) {
            return $this->finish();
        }

        if ($connection === 'sqlite') {
            $path = config('database.connections.sqlite.database');
            if ($path !== ':memory:' && ! $this->check("SQLite file {$path}", is_file($path), 'Run `touch database/database.sqlite`.')) {
                return $this->finish();
            }
        }

        try {
            DB::connection()->getPdo();
            $this->check('Database connection', true);
        } catch (Throwable $e) {
            $this->check('Database connection', false, $e->getMessage());

            return $this->finish();
        }

        $migrated = collect(['users', 'sessions', 'goals', 'tasks', 'quotes'])->every(fn (string $table) => Schema::hasTable($table));
        if (! $this->check('Database migrated', $migrated, 'Run `php artisan migrate --seed`.')) {
            return $this->finish();
        }

        $this->advise('Quotes seeded', Quote::query()->exists(), 'Run `php artisan db:seed` to add the quote collection.');
        $this->advise('At least one account', User::query()->exists(), 'Run `php artisan focus:create-user`.');
        $this->advise(
            'Built web app (for `php artisan serve` without Vite)',
            is_file(public_path('app/index.html')),
            'Run `pnpm build` from the repository root, or use `pnpm dev` and open http://localhost:5173.',
        );

        if (app()->isProduction()) {
            $this->check('Debug mode off in production', ! config('app.debug'), 'Set APP_DEBUG=false.');
            // Loopback HTTP never leaves the machine, so a local install may run without HTTPS.
            $loopback = in_array(parse_url((string) config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
            $this->check(
                'Secure session cookie in production',
                (bool) config('session.secure') || $loopback,
                'Set SESSION_SECURE_COOKIE=true and serve over HTTPS.',
            );
        }

        return $this->finish();
    }

    private function check(string $label, bool $ok, string $fix = ''): bool
    {
        if ($ok) {
            $this->components->twoColumnDetail($label, '<fg=green;options=bold>OK</>');
        } else {
            $this->failures++;
            $this->components->twoColumnDetail($label, '<fg=red;options=bold>FAIL</>');
            $this->line("  <fg=gray>→</> {$fix}");
        }

        return $ok;
    }

    private function advise(string $label, bool $ok, string $fix): void
    {
        $this->components->twoColumnDetail($label, $ok ? '<fg=green;options=bold>OK</>' : '<fg=yellow;options=bold>WARN</>');
        if (! $ok) {
            $this->line("  <fg=gray>→</> {$fix}");
        }
    }

    private function finish(): int
    {
        $this->newLine();

        if ($this->failures > 0) {
            $this->components->error('Focus is not ready. Fix the items marked FAIL above.');

            return self::FAILURE;
        }

        $this->components->info('Focus is ready.');

        return self::SUCCESS;
    }
}
