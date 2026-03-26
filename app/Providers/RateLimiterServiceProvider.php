<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimiterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureApiLimiter($this->app);
        $this->configureLoginLimiter($this->app);
        $this->configureGlobalLimiter($this->app);
    }

    protected function configureApiLimiter(Application $app): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $key = $user ? 'api-user:' . $user->id : 'api-ip:' . $request->ip();

            return [
                Limit::perMinute(60)->by($key),
            ];
        });
    }

    protected function configureLoginLimiter(Application $app): void
    {
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by('login-ip:' . $request->ip()),
                Limit::perMinute(10)->by('login-email:' . $request->input('email')),
            ];
        });
    }

    protected function configureGlobalLimiter(Application $app): void
    {
        RateLimiter::for('global', function (Request $request) {
            return [
                Limit::perMinute(120)->by('global-ip:' . $request->ip()),
            ];
        });
    }
}

