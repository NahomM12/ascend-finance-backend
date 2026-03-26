<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsSuperAdmin;
use App\Http\Middleware\IsInvestor;

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
        //
        Route::aliasMiddleware('admin', IsAdmin::class);
        Route::aliasMiddleware('superadmin', IsSuperAdmin::class);
        Route::aliasMiddleware('isinvestor', IsInvestor::class);
       
        config([
            'cors' => [
                'paths' => ['api/*', 'sanctum/csrf-cookie'],
                'allowed_methods' => ['*'],
                'allowed_origins' => ['*'],// should need to avoid in production
                //'http://primeproperty.test',
                //'http://192.168.1.6:8081',
                //'exp://192.168.1.6:8081',
                //'http://localhost:8081',
              // In production, specify your app's URL
                'allowed_origins_patterns' => [],
                'allowed_headers' => ['*'],
                'exposed_headers' => [],
                'max_age' => 0,
                'supports_credentials' => false,
            ],
        ]);
    }
}
