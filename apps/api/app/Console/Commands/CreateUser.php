<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Focus has no public sign-up page; accounts are created from the server.
 */
#[Signature('focus:create-user')]
#[Description('Create a Focus user account')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $name = text('Name', required: true, validate: ['name' => 'max:255']);
        $email = text('Email', required: true, validate: ['email' => 'email|max:255|unique:users,email']);
        $password = password('Password', required: true, hint: 'At least 12 characters with upper and lower case letters and a number.');
        $confirmation = password('Confirm password', required: true);

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::create(['name' => $name, 'email' => $email, 'password' => $password]);

        $this->components->info("Account created for {$email}.");

        return self::SUCCESS;
    }
}
