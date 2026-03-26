<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\FounderController;
use App\Http\Controllers\PitchDeckController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\ThumbnailController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AvailabilityController;
use App\Models\AdminActivity;
use App\Models\PitchDeckDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

Route::middleware('throttle:api')->group(function () {

    Route::post('/login', [RegistrationController::class, 'login'])->middleware('throttle:login');
    Route::post('/logout', [RegistrationController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/investors/register', [RegistrationController::class, 'investorRegister'])->middleware('throttle:login');

    // OAuth Routes
    Route::get('/oauth/{provider}/redirect', [RegistrationController::class, 'redirectToProvider']);
    Route::get('/oauth/{provider}/callback', [RegistrationController::class, 'handleProviderCallback']);
    Route::post('/oauth/{provider}/login', [RegistrationController::class, 'oauthLogin']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');

    Route::post('/otp/send', [OtpController::class, 'send'])->middleware('throttle:login');

    Route::apiResource('users', UserController::class);
    Route::apiResource('founders', FounderController::class)->middleware('auth:sanctum');

    Route::post('/admin/register', [RegistrationController::class, 'adminRegister'])->middleware(['auth:sanctum', 'superadmin']);
    Route::post('/founder/create-profile', [RegistrationController::class, 'createFounderProfile']);

    // Pitch Deck Routes
    Route::get('/pitch-decks', [PitchDeckController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/pitch-decks/{id}', [PitchDeckController::class, 'show'])->middleware('auth:sanctum');
    // Public pitch deck browsing (published only)
    Route::get('/public/pitch-decks', [PitchDeckController::class, 'publicIndex']);
    Route::get('/public/pitch-decks/{id}', [PitchDeckController::class, 'publicShow']);
    Route::get('/pitch-decks/{id}/download', [PitchDeckController::class, 'download'])->middleware(['auth:sanctum']);
    Route::post('/pitch-decks', [PitchDeckController::class, 'store'])->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {

        Route::put('/pitch-decks/{id}', [PitchDeckController::class, 'update']);
        Route::delete('/pitch-decks/{id}', [PitchDeckController::class, 'destroy']);
        Route::post('/pitch-decks/{id}/file', [PitchDeckController::class, 'updateFile']);
    });

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::put('/pitch-decks/{id}/status', [PitchDeckController::class, 'changeStatusByAdmin']);
    });

    Route::post('/pitch-decks/test-auth', [PitchDeckController::class, 'testAuth'])->middleware('auth:sanctum');

    Route::get('/admin/activities', function () {
        return AdminActivity::with('adminUser')->latest()->limit(20)->get();
    })->middleware(['auth:sanctum', 'admin']);

    Route::get('/admin/downloads', function () {
        return PitchDeckDownload::with(['user', 'pitchDeck'])->orderByDesc('downloaded_at')->limit(20)->get();
    })->middleware(['auth:sanctum', 'admin']);

    // Appointments
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::put('/appointments/{id}', [AppointmentController::class, 'update']);
        Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);

        Route::get('/availability', [AvailabilityController::class, 'index']);
        Route::post('/availability', [AvailabilityController::class, 'store']);
        Route::put('/availability/{id}', [AvailabilityController::class, 'update']);
        Route::delete('/availability/{id}', [AvailabilityController::class, 'destroy']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/appointments/available', [AppointmentController::class, 'available']);
        Route::post('/appointments/book', [AppointmentController::class, 'book']);
        Route::get('/appointments/mine', [AppointmentController::class, 'myAppointments']);
    });

    // Thumbnail management routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/pitch-decks/{pitchDeck}/thumbnail', [ThumbnailController::class, 'show']);
        Route::post('/pitch-decks/{pitchDeck}/thumbnail', [ThumbnailController::class, 'upload']);
        Route::delete('/pitch-decks/{pitchDeck}/thumbnail', [ThumbnailController::class, 'delete']);
        Route::middleware('admin')->post('/thumbnails/bulk-convert', [ThumbnailController::class, 'bulkConvert']);
    });
    Route::get('/test-redis', function () {
    // Test basic cache
    Cache::put('test_key', 'Redis is working!', 60);
    $cachedValue = Cache::get('test_key');
    
    // Test Redis facade
    Redis::set('test_redis', 'Direct Redis access works!');
    $redisValue = Redis::get('test_redis');
    
    return response()->json([
        'cache_facade' => $cachedValue,
        'redis_facade' => $redisValue,
        'status' => 'Redis is configured correctly'
    ]);
});
});
