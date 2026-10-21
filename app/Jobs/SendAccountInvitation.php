<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Password;

class SendAccountInvitation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        Password::sendResetLink(['email' => $user->email]);
    }
}
