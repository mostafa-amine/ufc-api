<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Switches off every API key of one account, e.g. one used for gambling (see /terms).
 * The account stays, marked blocked: it cannot log in, make new keys, or sign up again with that email.
 */
class RevokeKeyCommand extends Command
{
    protected $signature = 'ufc:revoke-key {email : Email the key was registered with}';

    protected $description = 'Switch off every API key of an account and stop it from signing up again';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }

        $count = $user->tokens()->delete();
        $user->forceFill(['blocked_at' => now()])->save();
        $this->info("Switched off {$count} key(s) for {$user->email} and blocked the account: it can't log in, make keys or sign up again.");

        return self::SUCCESS;
    }
}
