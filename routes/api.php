<?php

use App\Http\Controllers\Api\UserExcelController;
use App\Http\Controllers\Api\V1\AdminOtoxpertBookingController;
use App\Http\Controllers\Api\V1\AdminToyotaServiceBookingController;
use App\Http\Controllers\Api\V1\AppController;
use App\Http\Controllers\Api\V1\AppraisalController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OtoxpertController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\ToyotaServiceController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\VehicleMakeController;
use App\Http\Controllers\Webhook\GithubDeployWebhookController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::post('deploy/github', GithubDeployWebhookController::class)
    ->middleware('throttle:30,1')
    ->name('webhooks.github.deploy');

Route::prefix('v1')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Excel Import & Export Routes (Tanpa Middleware Auth)
    |--------------------------------------------------------------------------
    | Jika Anda ingin memproteksi endpoint ini dengan middleware auth Passport di kemudian hari,
    | Anda bisa menambahkan middleware: ->middleware('auth:api')
    */
    Route::get('users/export', [UserExcelController::class, 'export']);
    Route::post('users/import', [UserExcelController::class, 'import']);

    Route::get('health', HealthController::class);

    // Unauthenticated app info endpoints (no maintenance check — needed to show maintenance message)
    Route::prefix('app')->group(function (): void {
        Route::get('version', [AppController::class, 'version'])->middleware('throttle:60,1');
        Route::get('config', [AppController::class, 'config'])->middleware('throttle:60,1');
    });

    // OTP endpoints (unauthenticated, heavily throttled)
    Route::prefix('auth/otp')->middleware(['throttle:10,1', 'check.maintenance'])->group(function (): void {
        Route::post('send', [OtpController::class, 'send']);
        Route::post('verify', [OtpController::class, 'verify']);
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware(['throttle:6,1', 'check.maintenance']);
        Route::post('login', [AuthController::class, 'login'])->middleware(['throttle:6,1', 'check.maintenance']);
        Route::post('google', [AuthController::class, 'google'])->middleware(['throttle:6,1', 'check.maintenance']);
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware(['throttle:6,1', 'check.maintenance']);
        Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])
            ->middleware(['throttle:6,1', 'check.maintenance']);
        Route::post('reset-password', [PasswordResetController::class, 'reset'])
            ->middleware(['throttle:6,1', 'check.maintenance']);
        Route::get('password/reset/{token}', function (string $token) {
            return ApiResponse::success(['token' => $token], 'Password reset token received.');
        })->name('password.reset')->middleware(['check.maintenance']);

        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->name('verification.verify')
            ->middleware(['check.maintenance']);

        Route::middleware(['auth:api', 'check.maintenance'])->group(function (): void {
            Route::post('email/send-verification', [EmailVerificationController::class, 'sendVerification'])
                ->middleware('throttle:6,1');
            Route::post('email/verify', [EmailVerificationController::class, 'verify']);

            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'updateProfile']);
            Route::post('avatar', [AuthController::class, 'uploadAvatar']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('device', [AuthController::class, 'updateDevice'])
                ->middleware('throttle:30,1');
            Route::post('phone', [OtpController::class, 'updatePhone']);
            Route::post('phone/verify', [OtpController::class, 'verifyPhone']);
        });
    });

    Route::apiResource('quotes', QuoteController::class);

    Route::middleware(['auth:api', 'check.maintenance'])->group(function (): void {
        Route::get('regions/provinces', [RegionController::class, 'provinces'])
            ->middleware('throttle:60,1');
        Route::get('vehicle-makes', [VehicleMakeController::class, 'index'])
            ->middleware('throttle:60,1');
        Route::get('vehicle-makes/{vehicleMake}/models', [VehicleMakeController::class, 'models'])
            ->middleware('throttle:60,1');

        Route::post('assets/upload', [AssetController::class, 'upload'])->middleware('throttle:30,1');

        Route::apiResource('vehicles', VehicleController::class)
            ->only(['index', 'store', 'show', 'update']);

        Route::get('appraisals', [AppraisalController::class, 'index']);
        Route::post('appraisals', [AppraisalController::class, 'store']);
        Route::get('appraisals/{appraisal}', [AppraisalController::class, 'show']);
        Route::put('appraisals/{appraisal}/vehicle-condition', [AppraisalController::class, 'updateCondition']);
        Route::post('appraisals/{appraisal}/photos', [AppraisalController::class, 'attachPhotos']);
        Route::post('appraisals/{appraisal}/submit', [AppraisalController::class, 'submit'])
            ->middleware('throttle:10,1');
        Route::post('appraisals/{appraisal}/resubmit', [AppraisalController::class, 'resubmit'])
            ->middleware('throttle:10,1');
        Route::post('appraisals/{appraisal}/decision', [AppraisalController::class, 'decision']);
        Route::post('appraisals/{appraisal}/schedule-inspection', [AppraisalController::class, 'scheduleInspection']);

        Route::prefix('toyota-service')->group(function (): void {
            Route::get('options', [ToyotaServiceController::class, 'options'])
                ->middleware('throttle:60,1');
            Route::get('availability', [ToyotaServiceController::class, 'availability'])
                ->middleware('throttle:60,1');
            Route::get('bookings', [ToyotaServiceController::class, 'index']);
            Route::post('bookings', [ToyotaServiceController::class, 'store'])
                ->middleware('throttle:10,1');
            Route::get('bookings/{booking}', [ToyotaServiceController::class, 'show']);
            Route::post(
                'bookings/{booking}/accept-alternative',
                [ToyotaServiceController::class, 'acceptAlternative'],
            );
            Route::post(
                'bookings/{booking}/reject-alternative',
                [ToyotaServiceController::class, 'rejectAlternative'],
            );
            Route::post(
                'bookings/{booking}/reschedule',
                [ToyotaServiceController::class, 'reschedule'],
            );
            Route::post(
                'bookings/{booking}/cancel',
                [ToyotaServiceController::class, 'cancel'],
            );
        });

        Route::prefix('otoxpert')->group(function (): void {
            Route::get('options', [OtoxpertController::class, 'options'])
                ->middleware('throttle:60,1');
            Route::get('workshops', [OtoxpertController::class, 'workshops'])
                ->middleware('throttle:60,1');
            Route::get(
                'workshops/{workshop}/services',
                [OtoxpertController::class, 'services'],
            )->middleware('throttle:60,1');
            Route::get(
                'availability',
                [OtoxpertController::class, 'availability'],
            )->middleware('throttle:60,1');
            Route::get('bookings', [OtoxpertController::class, 'index']);
            Route::post('bookings', [OtoxpertController::class, 'store'])
                ->middleware('throttle:10,1');
            Route::get(
                'bookings/{booking}',
                [OtoxpertController::class, 'show'],
            );
            Route::post(
                'bookings/{booking}/accept-alternative',
                [OtoxpertController::class, 'acceptAlternative'],
            );
            Route::post(
                'bookings/{booking}/reject-alternative',
                [OtoxpertController::class, 'rejectAlternative'],
            );
            Route::post(
                'bookings/{booking}/reschedule',
                [OtoxpertController::class, 'reschedule'],
            );
            Route::post(
                'bookings/{booking}/cancel',
                [OtoxpertController::class, 'cancel'],
            );
        });

        Route::prefix('admin/toyota-service')->group(function (): void {
            Route::get('options', [AdminToyotaServiceBookingController::class, 'options']);
            Route::prefix('bookings')->group(function (): void {
                Route::get('/', [AdminToyotaServiceBookingController::class, 'index']);
                Route::get('{booking}', [AdminToyotaServiceBookingController::class, 'show']);
                Route::post(
                    '{booking}/actions',
                    [AdminToyotaServiceBookingController::class, 'action'],
                )->middleware('throttle:30,1');
            });
        });

        Route::prefix('admin/otoxpert')->group(function (): void {
            Route::get(
                'options',
                [AdminOtoxpertBookingController::class, 'options'],
            );
            Route::get(
                'bookings/export',
                [AdminOtoxpertBookingController::class, 'export'],
            )->middleware('throttle:10,1');
            Route::prefix('bookings')->group(function (): void {
                Route::get(
                    '/',
                    [AdminOtoxpertBookingController::class, 'index'],
                );
                Route::get(
                    '{booking}',
                    [AdminOtoxpertBookingController::class, 'show'],
                );
                Route::post(
                    '{booking}/actions',
                    [AdminOtoxpertBookingController::class, 'action'],
                )->middleware('throttle:30,1');
            });
        });

        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('articles', ArticleController::class);
        Route::apiResource('tags', TagController::class);

        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('read-all', [NotificationController::class, 'markAllRead']);
            Route::post('{notification}/read', [NotificationController::class, 'markRead']);
        });
    });
});
