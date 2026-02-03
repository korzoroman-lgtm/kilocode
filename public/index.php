<?php

declare(strict_types=1);

/**
 * Photo2Video - Front Controller
 * Pure PHP 8.2+ Web Application
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Config;
use App\Core\Router;

// Initialize application
$app = new App();
$app->run();
