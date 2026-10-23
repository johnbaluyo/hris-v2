<?php

namespace App\Console\Commands;

use App\Models\SystemNotification;
use Illuminate\Console\Command;

class ExpireNotifications extends Command
{
    protected $signature = 'notifications:expire';

    protected $description = 'Deactivate expired notification content without deleting audience or recipient history';

    public function handle(): int
    {
        $count = SystemNotification::where('is_active', true)->where('expires_at', '<=', now())->update(['is_active' => false]);
        $this->info("Deactivated {$count} expired notifications. Receipt history was retained.");

        return self::SUCCESS;
    }
}
