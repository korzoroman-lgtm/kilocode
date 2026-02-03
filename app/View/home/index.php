<?php $page = 'home'; ?>

<div class="container">
    <!-- Hero Section -->
    <section style="text-align: center; padding: 60px 0;">
        <h1 style="font-size: 48px; margin-bottom: 20px;">
            Turn Photos into <span class="text-accent">Amazing Videos</span>
        </h1>
        <p style="font-size: 20px; color: var(--text-muted); max-width: 600px; margin: 0 auto 40px;">
            Transform your static images into dynamic animated videos with our AI-powered platform.
            Perfect for social media, marketing, and creative projects.
        </p>
        <div style="display: flex; gap: 16px; justify-content: center;">
            <?php if ($is_logged_in): ?>
                <a href="/dashboard/projects/new" class="btn btn-primary btn-lg">Create Video</a>
                <a href="/dashboard" class="btn btn-secondary btn-lg">Dashboard</a>
            <?php else: ?>
                <a href="/register" class="btn btn-primary btn-lg">Get Started Free</a>
                <a href="/gallery" class="btn btn-secondary btn-lg">View Gallery</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Stats -->
    <?php if (!empty($stats)): ?>
    <section class="stats" style="margin-bottom: 60px;">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['total_videos'] ?? 0) ?></div>
            <div class="stat-label">Videos Created</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['total_users'] ?? 0) ?></div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['total_generations'] ?? 0) ?></div>
            <div class="stat-label">Generations</div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Features -->
    <section style="padding: 60px 0;">
        <h2 style="text-align: center; margin-bottom: 40px;">Why Choose Photo2Video?</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px;">
            <div class="card">
                <div class="card-body" style="text-align: center;">
                    <div style="font-size: 48px; margin-bottom: 16px;">🎨</div>
                    <h3 style="margin-bottom: 12px;">AI-Powered Animation</h3>
                    <p style="color: var(--text-muted);">
                        Our advanced AI transforms static images into smooth, natural animations.
                    </p>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="text-align: center;">
                    <div style="font-size: 48px; margin-bottom: 16px;">📱</div>
                    <h3 style="margin-bottom: 12px;">Multiple Formats</h3>
                    <p style="color: var(--text-muted);">
                        Create videos in 16:9, 9:16, or 1:1 aspect ratios for any platform.
                    </p>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="text-align: center;">
                    <div style="font-size: 48px; margin-bottom: 16px;">⚡</div>
                    <h3 style="margin-bottom: 12px;">Fast Processing</h3>
                    <p style="color: var(--text-muted);">
                        Get your animated videos in minutes, not hours.
                    </p>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="text-align: center;">
                    <div style="font-size: 48px; margin-bottom: 16px;">🎯</div>
                    <h3 style="margin-bottom: 12px;">Custom Presets</h3>
                    <p style="color: var(--text-muted);">
                        Choose from cinematic, smooth, fast, or slow motion presets.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Gallery -->
    <?php if (!empty($featured_videos)): ?>
    <section style="padding: 60px 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h2>Featured Creations</h2>
            <a href="/gallery" class="btn btn-secondary">View All</a>
        </div>
        <div class="video-grid">
            <?php foreach ($featured_videos as $video): ?>
            <a href="/gallery/<?= $video['id'] ?>" class="video-card">
                <div class="video-thumbnail">
                    <?php if (!empty($video['thumbnail'])): ?>
                        <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title'] ?? 'Video') ?>">
                    <?php else: ?>
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($video['duration'])): ?>
                        <div class="video-duration"><?= number_format($video['duration'], 0, '.', '') ?>s</div>
                    <?php endif; ?>
                </div>
                <div class="video-info">
                    <h3 class="video-title"><?= htmlspecialchars($video['title'] ?? 'Untitled') ?></h3>
                    <div class="video-meta">
                        <div class="video-author">
                            <div class="avatar avatar-sm"><?= strtoupper(substr($video['author_name'] ?? 'U', 0, 1)) ?></div>
                            <span><?= htmlspecialchars($video['author_name'] ?? 'Unknown') ?></span>
                        </div>
                        <span><?= number_format($video['view_count'] ?? 0) ?> views</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section style="text-align: center; padding: 60px 0; background: var(--bg-secondary); border-radius: 12px; margin-bottom: 40px;">
        <h2 style="margin-bottom: 16px;">Ready to Create?</h2>
        <p style="color: var(--text-muted); margin-bottom: 30px;">
            Start transforming your photos into videos today.
        </p>
        <a href="/register" class="btn btn-primary btn-lg">Start Creating Free</a>
    </section>
</div>
