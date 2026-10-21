<?php

namespace App\Jobs;

use App\Models\IntegrationRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;

class SyncPartnerUsers implements ShouldQueue
{
    use Queueable;

    // Remote mutation is not retried automatically without a partner idempotency contract.
    public int $tries = 3;

    public function __construct(public int $runId) {}

    /** @return array<int, WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('ssi-sync'))->releaseAfter(30)->expireAfter(90)];
    }

    public function handle(): void
    {
        $run = IntegrationRun::findOrFail($this->runId);
        if ($run->status !== 'queued') {
            return;
        }
        $partner = config('hris.partners.'.$run->partner);
        if (! is_array($partner) || empty($partner['url']) || empty($partner['token']) || parse_url($partner['url'], PHP_URL_SCHEME) !== 'https') {
            $run->update(['status' => 'failed', 'message' => 'The partner URL and credentials must be configured.']);

            return;
        }
        $run->update(['status' => 'running']);
        try {
            $client = Http::acceptJson()->withToken($partner['token'])->timeout(60)->connectTimeout(5)->withoutRedirecting()->withHeaders(['Idempotency-Key' => 'hris-sync-'.$run->id]);
            $url = rtrim($partner['url'], '/').'/api/sync-users';
            $response = $client->post($url);
            if ($response->status() === 405 && ($partner['allow_get_fallback'] ?? false)) {
                $response = $client->get($url);
            }
            $run->update(['status' => $response->successful() ? 'succeeded' : 'failed',
                'message' => $response->successful() ? 'Partner synchronization completed.' : 'The partner rejected the synchronization request.']);
        } catch (ConnectionException) {
            $run->update(['status' => 'failed', 'message' => 'The partner could not be reached. Check its status before retrying.']);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        IntegrationRun::whereKey($this->runId)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'failed', 'message' => 'Synchronization did not complete. Check the partner state before retrying.']);
    }
}
