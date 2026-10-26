<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

/**
 * Creates the account configured in .env (FOCUS_OWNER_*). An existing account is never
 * modified, so re-seeding cannot reset a password that was changed later.
 */
class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        ['name' => $name, 'email' => $email, 'password' => $password] = config('focus.owner');

        if (blank($email) || blank($password)) {
            $this->command?->info('No FOCUS_OWNER_EMAIL / FOCUS_OWNER_PASSWORD set — skipping account. Use `php artisan focus:create-user`.');

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("Account {$email} already exists — left unchanged.");

            return;
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['email', 'max:255'], 'password' => [Password::defaults()]],
        );

        if ($validator->errors()->has('email')) {
            throw new RuntimeException('FOCUS_OWNER_EMAIL is not a valid email address.');
        }

        if ($validator->errors()->has('password')) {
            if (app()->isProduction()) {
                throw new RuntimeException('FOCUS_OWNER_PASSWORD is too weak for production: '.$validator->errors()->first('password'));
            }

            $this->command?->warn('FOCUS_OWNER_PASSWORD does not meet the password policy ('.$validator->errors()->first('password').') Allowed locally only — change it before deploying.');
        }

        User::create([
            'name' => filled($name) ? $name : strtok($email, '@'),
            'email' => $email,
            'password' => $password,
        ]);

        $this->command?->info("Created account {$email}.");
    }
}
