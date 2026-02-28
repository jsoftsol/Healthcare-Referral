<?php
use App\Http\Controllers\Api\V1\AdminReferralController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HospitalReferralController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReportingController;
use App\Http\Controllers\Api\V1\StaffReferralController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('hospital.auth')
    ->prefix('hospital')
    ->group(function () {
        Route::post('referrals', [HospitalReferralController::class, 'store']);
    });

Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        // Referrals
        Route::get('referrals', [AdminReferralController::class, 'index']);
        Route::get('referrals/{referral}', [AdminReferralController::class, 'show']);
        Route::patch('referrals/{referral}/assign', [AdminReferralController::class, 'assign']);
        Route::patch('referrals/{referral}/cancel', [AdminReferralController::class, 'cancel']);

        // Reports
        Route::get('reports', [ReportingController::class, 'index']);
    });

Route::middleware(['auth:sanctum', 'role:admin,doctor,coordinator'])
    ->prefix('staff')
    ->group(function () {
        Route::get('referrals', [StaffReferralController::class, 'index']);
        Route::patch('notifications/{notification}/acknowledge', [NotificationController::class, 'acknowledge']);
    });
