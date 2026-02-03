<?php

declare(strict_types=1);

/**
 * API Routes
 * JSON API endpoints for frontend and Telegram Mini App
 */

use App\Core\App;
use App\Controllers\Api\AuthApiController;
use App\Controllers\Api\ProjectApiController;
use App\Controllers\Api\VideoApiController;
use App\Controllers\Api\PaymentApiController;
use App\Controllers\Api\GalleryApiController;
use App\Controllers\Api\UserApiController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\CsrfMiddleware;

// API version prefix
$app->get('/api/v1/status', [UserApiController::class, 'status']);

// Health check (no auth)
$app->get('/api/health', function () {
    return \App\Core\Response::json([
        'status' => 'ok',
        'timestamp' => date('c'),
        'version' => '1.0.0',
    ]);
});

// Auth endpoints
$app->post('/api/v1/auth/register', [AuthApiController::class, 'register'], [RateLimitMiddleware::class, 'auth']);
$app->post('/api/v1/auth/login', [AuthApiController::class, 'login'], [RateLimitMiddleware::class, 'auth']);
$app->post('/api/v1/auth/logout', [AuthApiController::class, 'logout'], [AuthMiddleware::class]);
$app->post('/api/v1/auth/telegram', [AuthApiController::class, 'telegramLogin'], [RateLimitMiddleware::class, 'auth']);
$app->post('/api/v1/auth/refresh', [AuthApiController::class, 'refresh']);

// Password reset
$app->post('/api/v1/auth/forgot-password', [AuthApiController::class, 'forgotPassword'], [RateLimitMiddleware::class, 'auth']);
$app->post('/api/v1/auth/reset-password', [AuthApiController::class, 'resetPassword']);

// User endpoints
$app->get('/api/v1/user/me', [UserApiController::class, 'me'], [AuthMiddleware::class]);
$app->put('/api/v1/user/me', [UserApiController::class, 'updateMe'], [AuthMiddleware::class, CsrfMiddleware::class]);
$app->get('/api/v1/user/credits', [UserApiController::class, 'credits'], [AuthMiddleware::class]);
$app->get('/api/v1/user/{id:\d+}', [UserApiController::class, 'view']);
$app->get('/api/v1/user/{id:\d+}/videos', [UserApiController::class, 'userVideos']);

// Projects endpoints
$app->get('/api/v1/projects', [ProjectApiController::class, 'list'], [AuthMiddleware::class]);
$app->post('/api/v1/projects', [ProjectApiController::class, 'create'], [AuthMiddleware::class, CsrfMiddleware::class]);
$app->get('/api/v1/projects/{id:\d+}', [ProjectApiController::class, 'view'], [AuthMiddleware::class]);
$app->put('/api/v1/projects/{id:\d+}', [ProjectApiController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
$app->delete('/api/v1/projects/{id:\d+}', [ProjectApiController::class, 'delete'], [AuthMiddleware::class]);

// Video generation endpoints
$app->get('/api/v1/videos', [VideoApiController::class, 'list'], [AuthMiddleware::class]);
$app->post('/api/v1/videos', [VideoApiController::class, 'create'], [AuthMiddleware::class, CsrfMiddleware::class]);
$app->get('/api/v1/videos/{id:\d+}', [VideoApiController::class, 'view'], [AuthMiddleware::class]);
$app->post('/api/v1/videos/{id:\d+}/generate', [VideoApiController::class, 'generate'], [AuthMiddleware::class]);
$app->get('/api/v1/videos/{id:\d+}/status', [VideoApiController::class, 'status'], [AuthMiddleware::class]);
$app->delete('/api/v1/videos/{id:\d+}', [VideoApiController::class, 'delete'], [AuthMiddleware::class]);
$app->post('/api/v1/videos/{id:\d+}/regenerate', [VideoApiController::class, 'regenerate'], [AuthMiddleware::class]);

// Video interaction endpoints
$app->post('/api/v1/videos/{id:\d+}/like', [VideoApiController::class, 'like'], [AuthMiddleware::class]);
$app->delete('/api/v1/videos/{id:\d+}/like', [VideoApiController::class, 'unlike'], [AuthMiddleware::class]);
$app->post('/api/v1/videos/{id:\d+}/view', [VideoApiController::class, 'viewVideo']);
$app->post('/api/v1/videos/{id:\d+}/report', [VideoApiController::class, 'report'], [AuthMiddleware::class]);
$app->put('/api/v1/videos/{id:\d+}/visibility', [VideoApiController::class, 'updateVisibility'], [AuthMiddleware::class]);

// Gallery endpoints (public)
$app->get('/api/v1/gallery', [GalleryApiController::class, 'list']);
$app->get('/api/v1/gallery/featured', [GalleryApiController::class, 'featured']);
$app->get('/api/v1/gallery/{id:\d+}', [GalleryApiController::class, 'view']);

// Payment endpoints
$app->get('/api/v1/payments', [PaymentApiController::class, 'list'], [AuthMiddleware::class]);
$app->post('/api/v1/payments/create', [PaymentApiController::class, 'create'], [AuthMiddleware::class]);
$app->get('/api/v1/payments/{id:\d+}', [PaymentApiController::class, 'view'], [AuthMiddleware::class]);
$app->post('/api/v1/payments/webhook', [PaymentApiController::class, 'webhook']);

// Credit balance (public for quick check)
$app->get('/api/v1/credits/balance', [UserApiController::class, 'publicCredits']);

// Upload endpoint
$app->post('/api/v1/upload/image', [ProjectApiController::class, 'uploadImage'], [AuthMiddleware::class, CsrfMiddleware::class]);
