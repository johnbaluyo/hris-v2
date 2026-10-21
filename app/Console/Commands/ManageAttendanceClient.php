<?php

namespace App\Console\Commands;

use App\Models\ServiceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ManageAttendanceClient extends Command
{
    protected $signature = 'attendance:client {name} {--device=*} {--revoke}';

    protected $description = 'Create/rotate a scoped collector credential, or revoke it; plaintext is displayed once';

    public function handle(): int
    {
        $name = $this->argument('name');
        if (! is_string($name) || $name === '') {
            return self::FAILURE;
        }
        $client = ServiceClient::firstOrNew(['name' => $name]);
        if ($this->option('revoke')) {
            if ($client->exists) {
                $client->update(['revoked_at' => now()]);
            }
            $this->info('Credential revoked.');

            return self::SUCCESS;
        }
        $devices = $this->option('device');
        if (! is_array($devices) || $devices === [] || strlen($name) > 255 || collect($devices)->contains(fn ($device) => ! is_string($device) || strlen($device) > 100 || $device === '')) {
            $this->error('Supply at least one --device=NAME (at most 100 characters).');

            return self::FAILURE;
        }
        $token = Str::random(64);
        $client->fill(['token_hash' => hash('sha256', $token), 'scopes' => ['attendance.ingest'], 'devices' => array_values(array_unique($devices)), 'revoked_at' => null])->save();
        $this->warn('Store this credential securely; it is displayed once and replaces the previous credential.');
        $this->line($token);

        return self::SUCCESS;
    }
}
