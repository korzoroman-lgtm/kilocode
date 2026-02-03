<?php

declare(strict_types=1);

/**
 * Database Migration Runner
 * CLI tool to run database migrations
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

class MigrationRunner
{
    private Database $db;
    private string $migrationsPath;
    private Logger $logger;

    public function __construct()
    {
        $config = Config::getInstance();
        $this->db = Database::getInstance();
        $this->migrationsPath = dirname(__DIR__) . '/database/migrations';
        $this->logger = Logger::getInstance();
    }

    /**
     * Run migrations
     */
    public function run(?string $targetVersion = null): void
    {
        echo "Running database migrations...\n";
        
        // Create migrations tracking table if not exists
        $this->createMigrationsTable();
        
        // Get applied migrations
        $appliedMigrations = $this->getAppliedMigrations();
        
        // Get migration files
        $files = glob($this->migrationsPath . '/*.sql');
        sort($files);
        
        $appliedCount = 0;
        $newCount = 0;
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Skip if this migration has already been applied
            if (in_array($filename, $appliedMigrations)) {
                $appliedCount++;
                continue;
            }
            
            echo "Applying migration: {$filename}\n";
            
            try {
                $this->runMigration($file, $filename);
                $newCount++;
                echo "✓ Migration {$filename} applied successfully\n";
            } catch (\Exception $e) {
                echo "✗ Migration {$filename} failed: " . $e->getMessage() . "\n";
                exit(1);
            }
        }
        
        echo "\nMigrations complete!\n";
        echo "Applied migrations: " . count($appliedMigrations) . "\n";
        echo "New migrations applied: {$newCount}\n";
    }

    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL,
                `batch` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->db->query($sql);
    }

    /**
     * Get list of applied migrations
     */
    private function getAppliedMigrations(): array
    {
        $sql = "SELECT `migration` FROM `migrations` ORDER BY `id`";
        $rows = $this->db->fetchAll($sql);
        
        return array_column($rows, 'migration');
    }

    /**
     * Run a single migration file
     */
    private function runMigration(string $file, string $filename): void
    {
        // Read and execute SQL
        $sql = file_get_contents($file);
        
        // Split by semicolons but handle quoted semicolons
        $statements = $this->splitSqlStatements($sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $this->db->query($statement);
            }
        }
        
        // Record migration
        $sql = "INSERT INTO `migrations` (`migration`, `batch`) VALUES (?, 1)";
        $this->db->query($sql, [$filename]);
    }

    /**
     * Split SQL file into individual statements
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            // Handle string literals
            if ($char === "'" || $char === '"') {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    // Check if it's escaped
                    if ($i > 0 && $sql[$i - 1] !== '\\') {
                        $inString = false;
                    }
                }
            }
            
            // Only split on semicolons outside of strings
            if ($char === ';' && !$inString) {
                $statements[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        // Add last statement if exists
        if (!empty(trim($current))) {
            $statements[] = $current;
        }
        
        return $statements;
    }

    /**
     * Reset all migrations (DANGEROUS!)
     */
    public function reset(): void
    {
        echo "⚠️  WARNING: This will drop all tables and re-run migrations!\n";
        echo "Type 'RESET' to confirm: ";
        
        $input = trim(fgets(STDIN));
        
        if ($input !== 'RESET') {
            echo "Aborted.\n";
            return;
        }
        
        echo "Dropping all tables...\n";
        
        // Get all tables
        $tables = $this->db->fetchAll("SHOW TABLES");
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $this->db->query("DROP TABLE IF EXISTS `{$tableName}`");
        }
        
        // Drop migrations table
        $this->db->query("DROP TABLE IF EXISTS `migrations`");
        
        echo "Tables dropped. Running migrations...\n";
        $this->run();
    }

    /**
     * Rollback last migration
     */
    public function rollback(): void
    {
        echo "Rolling back last migration...\n";
        
        // Get last migration
        $sql = "SELECT * FROM `migrations` ORDER BY `id` DESC LIMIT 1";
        $lastMigration = $this->db->fetchOne($sql);
        
        if (!$lastMigration) {
            echo "No migrations to rollback.\n";
            return;
        }
        
        echo "Last migration: {$lastMigration['migration']}\n";
        echo "Rollback is not fully implemented. Please run migrations:reset manually.\n";
    }
}

// CLI handling
if (php_sapi_name() === 'cli') {
    $runner = new MigrationRunner();
    
    $command = $argv[1] ?? 'run';
    
    switch ($command) {
        case 'run':
            $runner->run();
            break;
            
        case 'reset':
            $runner->reset();
            break;
            
        case 'rollback':
            $runner->rollback();
            break;
            
        default:
            echo "Usage: php migrate.php [run|reset|rollback]\n";
            exit(1);
    }
}
