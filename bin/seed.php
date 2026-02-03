<?php

declare(strict_types=1);

/**
 * Database Seeder
 * Seeds the database with initial test data
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

class Seeder
{
    private Database $db;
    private Logger $logger;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
    }

    /**
     * Run all seeders
     */
    public function run(): void
    {
        echo "Running database seeders...\n\n";

        $this->seedUsers();
        $this->seedProjects();
        $this->seedVideos();
        $this->seedFeaturedVideos();

        echo "\n✓ Seeding complete!\n";
    }

    /**
     * Seed admin and test users
     */
    private function seedUsers(): void
    {
        echo "Seeding users...\n";

        // Check if admin exists
        $adminExists = $this->db->exists('users', ['email' => 'admin@photo2video.local']);

        if (!$adminExists) {
            $adminId = $this->db->insert('users', [
                'email' => 'admin@photo2video.local',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'name' => 'Admin',
                'role' => 'admin',
                'credits' => 100,
                'is_active' => 1,
                'email_verified' => 1,
            ]);
            echo "  ✓ Created admin user (admin@photo2video.local / admin123)\n";
        } else {
            echo "  - Admin user already exists\n";
        }

        // Check if test user exists
        $testExists = $this->db->exists('users', ['email' => 'test@photo2video.local']);

        if (!$testExists) {
            $testId = $this->db->insert('users', [
                'email' => 'test@photo2video.local',
                'password_hash' => password_hash('test123', PASSWORD_DEFAULT),
                'name' => 'Test User',
                'role' => 'user',
                'credits' => 10,
                'is_active' => 1,
                'email_verified' => 1,
            ]);
            echo "  ✓ Created test user (test@photo2video.local / test123)\n";
        } else {
            echo "  - Test user already exists\n";
        }
    }

    /**
     * Seed sample projects
     */
    private function seedProjects(): void
    {
        echo "Seeding projects...\n";

        $testUser = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", ['test@photo2video.local']);
        
        if (!$testUser) {
            echo "  - Test user not found, skipping projects\n";
            return;
        }

        $userId = $testUser['id'];

        $projects = [
            ['name' => 'Summer Vacation', 'description' => 'Memories from our summer trip'],
            ['name' => 'Product Showcase', 'description' => 'Demo videos for products'],
            ['name' => 'Family Events', 'description' => 'Special family moments'],
        ];

        foreach ($projects as $project) {
            if (!$this->db->exists('projects', ['user_id' => $userId, 'name' => $project['name']])) {
                $this->db->insert('projects', [
                    'user_id' => $userId,
                    'name' => $project['name'],
                    'description' => $project['description'],
                    'status' => 'draft',
                    'format' => '16:9',
                ]);
                echo "  ✓ Created project: {$project['name']}\n";
            }
        }
    }

    /**
     * Seed sample videos
     */
    private function seedVideos(): void
    {
        echo "Seeding videos...\n";

        $testUser = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", ['test@photo2video.local']);
        $adminUser = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", ['admin@photo2video.local']);
        
        if (!$testUser || !$adminUser) {
            echo "  - Users not found, skipping videos\n";
            return;
        }

        $sampleVideos = [
            [
                'user_id' => $testUser['id'],
                'title' => 'Sunset Beach Animation',
                'format' => '16:9',
                'preset' => 'cinematic',
                'visibility' => 'public',
                'status' => 'completed',
                'view_count' => 150,
                'like_count' => 12,
            ],
            [
                'user_id' => $testUser['id'],
                'title' => 'City Night Lights',
                'format' => '9:16',
                'preset' => 'smooth',
                'visibility' => 'public',
                'status' => 'completed',
                'view_count' => 89,
                'like_count' => 7,
            ],
            [
                'user_id' => $adminUser['id'],
                'title' => 'Mountain Landscape',
                'format' => '1:1',
                'preset' => 'default',
                'visibility' => 'public',
                'status' => 'completed',
                'view_count' => 256,
                'like_count' => 23,
                'is_featured' => 1,
            ],
        ];

        foreach ($sampleVideos as $video) {
            $this->db->insert('videos', array_merge($video, [
                'original_image' => 'https://example.com/sample.jpg',
                'thumbnail' => 'https://example.com/thumb.jpg',
            ]));
            echo "  ✓ Created video: {$video['title']}\n";
        }
    }

    /**
     * Seed featured videos
     */
    private function seedFeaturedVideos(): void
    {
        echo "Seeding featured videos...\n";

        // Get some public videos and mark them as featured
        $videos = $this->db->fetchAll(
            "SELECT id FROM videos WHERE visibility = 'public' AND status = 'completed' LIMIT 3"
        );

        foreach ($videos as $video) {
            $this->db->update('videos', ['is_featured' => 1], ['id' => $video['id']]);
        }

        echo "  ✓ Marked " . count($videos) . " videos as featured\n";
    }
}

// CLI handling
if (php_sapi_name() === 'cli') {
    $seeder = new Seeder();
    $seeder->run();
}
