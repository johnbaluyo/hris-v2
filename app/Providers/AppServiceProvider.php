<?php

namespace App\Providers;

use App\Models\RawPunch;
use App\Models\ServiceClient;
use App\Models\User;
use App\Policies\EmployeePolicy;
use App\Policies\RawPunchPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::policy(User::class, EmployeePolicy::class);
        Gate::policy(RawPunch::class, RawPunchPolicy::class);
        RateLimiter::for('attendance-ingest', function (Request $request) {
            $client = $request->attributes->get('service_client');

            return Limit::perMinute(60)->by($client instanceof ServiceClient ? 'collector:'.$client->id : 'collector-ip:'.$request->ip());
        });
        foreach (array_unique(array_merge(...array_values(config('hris.roles')))) as $ability) {
            Gate::define($ability, fn (User $user) => $user->hasAbility($ability));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
