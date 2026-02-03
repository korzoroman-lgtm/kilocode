<?php

declare(strict_types=1);

/**
 * Web Routes
 * SSR pages for landing, gallery, and authenticated areas
 */

use App\Core\App;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\GalleryController;
use App\Controllers\DashboardController;
use App\Controllers\ProfileController;
use App\Controllers\AdminController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

/** @var App $app */

$app->get('/', [HomeController::class, 'index'], ['track_visit']);
$app->get('/features', [HomeController::class, 'features']);
$app->get('/pricing', [HomeController::class, 'pricing']);
$app->get('/about', [HomeController::class, 'about']);

// Gallery routes
$app->get('/gallery', [GalleryController::class, 'index']);
$app->get('/gallery/{id:\d+}', [GalleryController::class, 'view']);
$app->get('/gallery/{id:\d+}/embed', [GalleryController::class, 'embed']);

// Auth routes
$app->get('/login', [AuthController::class, 'showLogin']);
$app->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
$app->get('/register', [AuthController::class, 'showRegister']);
$app->post('/register', [AuthController::class, 'register'], [CsrfMiddleware::class]);
$app->get('/logout', [AuthController::class, 'logout']);
$app->get('/verify-email/{token}', [AuthController::class, 'verifyEmail']);
$app->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$app->post('/forgot-password', [AuthController::class, 'forgotPassword'], [CsrfMiddleware::class]);
$app->get('/reset-password/{token}', [AuthController::class, 'showResetPassword']);
$app->post('/reset-password/{token}', [AuthController::class, 'resetPassword'], [CsrfMiddleware::class]);

// Protected dashboard routes
$app->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);
$app->get('/dashboard/projects', [DashboardController::class, 'projects'], [AuthMiddleware::class]);
$app->get('/dashboard/projects/new', [DashboardController::class, 'newProject'], [AuthMiddleware::class]);
$app->get('/dashboard/projects/{id:\d+}', [DashboardController::class, 'viewProject'], [AuthMiddleware::class]);
$app->get('/dashboard/projects/{id:\d+}/edit', [DashboardController::class, 'editProject'], [AuthMiddleware::class]);
$app->get('/dashboard/generations', [DashboardController::class, 'generations'], [AuthMiddleware::class]);
$app->get('/dashboard/credits', [DashboardController::class, 'credits'], [AuthMiddleware::class]);
$app->get('/dashboard/settings', [DashboardController::class, 'settings'], [AuthMiddleware::class]);

// Profile routes
$app->get('/profile/{username}', [ProfileController::class, 'view']);
$app->get('/profile/{username}/videos', [ProfileController::class, 'videos']);

// Admin routes
$app->get('/admin', [AdminController::class, 'index'], [AuthMiddleware::class, 'admin_only']);
$app->get('/admin/users', [AdminController::class, 'users'], [AuthMiddleware::class, 'admin_only']);
$app->get('/admin/users/{id:\d+}', [AdminController::class, 'viewUser'], [AuthMiddleware::class, 'admin_only']);
$app->get('/admin/videos', [AdminController::class, 'videos'], [AuthMiddleware::class, 'admin_only']);
$app->get('/admin/videos/{id:\d+}', [AdminController::class, 'viewVideo'], [AuthMiddleware::class, 'admin_only']);
$app->get('/admin/reports', [AdminController::class, 'reports'], [AuthMiddleware::class, 'admin_only']);
$app->get('/admin/payments', [AdminController::class, 'payments'], [AuthMiddleware::class, 'admin_only']);
$app->get('/admin/settings', [AdminController::class, 'settings'], [AuthMiddleware::class, 'admin_only']);
