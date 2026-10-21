<?php

namespace App\Console\Commands;

use App\Jobs\SendAccountInvitation;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateHrisAdministrator extends Command
{
    protected $signature = 'hris:create-administrator {email} {--name=}';

    protected $description = 'Create the initial system administrator and send a password setup email; never sets a shared password';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (! is_string($email)) {
            return self::FAILURE;
        }
        $data = ['email' => strtolower($email), 'name' => $this->option('name') ?: $this->ask('Administrator name')];
        $validator = Validator::make($data, ['email' => 'required|email|unique:users,email', 'name' => 'required|string|max:255']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = DB::transaction(function () use ($data) {
            $user = new User;
            $user->forceFill($data + ['password' => Str::random(64), 'role' => 'system_admin'])->save();
            Audit::record('account.bootstrap', $user, null);

            return $user;
        });
        SendAccountInvitation::dispatch($user->id);
        $this->info('Administrator created. Process the queue and use the password setup email. Grant confidential HR abilities separately.');

        return self::SUCCESS;
    }
}
