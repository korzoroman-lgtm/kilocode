<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrf_token ?? '' ?>">
    <title><?= htmlspecialchars($title ?? 'Photo2Video') ?></title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
</head>
<body>
    <div class="page-wrapper">
        <header class="header">
            <div class="container header-inner">
                <a href="/" class="logo">
                    <div class="logo-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                        </svg>
                    </div>
                    Photo2Video
                </a>
                
                <nav class="nav">
                    <a href="/" class="nav-link <?= $page ?? '' === 'home' ? 'active' : '' ?>">Home</a>
                    <a href="/gallery" class="nav-link <?= $page ?? '' === 'gallery' ? 'active' : '' ?>">Gallery</a>
                    <a href="/features" class="nav-link">Features</a>
                    <a href="/pricing" class="nav-link">Pricing</a>
                </nav>
                
                <div class="nav">
                    <?php if (isset($is_logged_in) && $is_logged_in): ?>
                        <a href="/dashboard" class="btn btn-secondary btn-sm">Dashboard</a>
                        <div class="dropdown">
                            <a href="#" class="btn btn-icon btn-secondary dropdown-toggle">
                                <div class="avatar avatar-sm">U</div>
                            </a>
                            <div class="dropdown-menu">
                                <a href="/dashboard/settings" class="dropdown-item">Settings</a>
                                <a href="/dashboard/credits" class="dropdown-item">Credits: <?= $user['credits'] ?? 0 ?></a>
                                <a href="/logout" class="dropdown-item">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/login" class="btn btn-secondary btn-sm">Login</a>
                        <a href="/register" class="btn btn-primary btn-sm">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <main class="main-content">
            <?php if (isset($flash_error)): ?>
                <div class="container mb-2">
                    <div class="alert alert-danger"><?= $flash_error ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($flash_success)): ?>
                <div class="container mb-2">
                    <div class="alert alert-success"><?= $flash_success ?></div>
                </div>
            <?php endif; ?>
