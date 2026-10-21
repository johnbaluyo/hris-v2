<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantHrisAbility extends Command
{
    protected $signature = 'hris:grant {email} {ability} {--revoke}';

    protected $description = 'Explicitly grant or revoke a documented ability for an employee (audited operator action)';

    public function handle(): int
    {
        $ability = $this->argument('ability');
        if (! is_string($ability)) {
            return self::FAILURE;
        }
        $allowed = array_unique(array_merge(...array_values(config('hris.roles'))));
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || ! in_array($ability, $allowed, true)) {
            $this->error('Unknown account or ability.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user, $ability) {
            if ($this->option('revoke')) {
                DB::table('ability_grants')->where('user_id', $user->id)->where('ability', $ability)->delete();
            } else {
                DB::table('ability_grants')->insertOrIgnore(['user_id' => $user->id, 'ability' => $ability]);
            }
            Audit::record('ability.'.($this->option('revoke') ? 'revoked.' : 'granted.').$ability, $user, null);
        });
        $this->info('Ability updated. Role defaults are independent of explicit grants.');

        return self::SUCCESS;
    }
}
