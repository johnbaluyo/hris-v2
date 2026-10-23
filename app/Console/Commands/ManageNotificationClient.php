<?php

namespace App\Console\Commands;

use App\Models\ServiceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ManageNotificationClient extends Command
{
    protected $signature = 'notifications:client {name} {--broadcast : Permit broadcast and role audiences} {--revoke}';

    protected $description = 'Create/rotate a notification-only producer credential, or revoke it; plaintext is displayed once';

    public function handle(): int
    {
        $name = $this->argument('name');
        if (! is_string($name) || trim($name) === '' || strlen($name) > 100) {
            $this->error('Supply a producer name of 1–100 characters.');

            return self::FAILURE;
        }
        $client = ServiceClient::firstOrNew(['name' => $name]);
        if ($client->exists && array_diff($client->scopes, ['notifications.publish', 'notifications.broadcast']) !== []) {
            $this->error('This name belongs to another service. Use a separate notification producer name.');

            return self::FAILURE;
        }
        if ($this->option('revoke')) {
            if ($client->exists) {
                $client->update(['revoked_at' => now()]);
            }
            $this->info('Notification credential revoked.');

            return self::SUCCESS;
        }
        $token = Str::random(64);
        $client->fill(['token_hash' => hash('sha256', $token), 'scopes' => $this->option('broadcast')
            ? ['notifications.publish', 'notifications.broadcast'] : ['notifications.publish'], 'devices' => [], 'revoked_at' => null])->save();
        $this->warn('Store this credential securely; it is displayed once and replaces the previous notification credential.');
        $this->line($token);

        return self::SUCCESS;
    }
}
