<?php

// DomainBackupManagerStorageTest.php — Ensures backup paths are created on the local disk.
//
// exports: DomainBackupManagerStorageTest
// used_by: none (PHPUnit entry)
// rules:   Uses an isolated filesystem root — no production storage paths.
// agent:   composer-2.5-fast | cursor | 2026-10-16 | s_db_import | Nested backup directory creation
// message:

namespace Tests\Unit;

use App\Services\Hosting\DomainBackupManager;
use ReflectionMethod;
use Tests\TestCase;

class DomainBackupManagerStorageTest extends TestCase
{
    public function test_put_backup_file_creates_nested_domain_directory(): void
    {
        $root = storage_path('framework/testing/backup-root-'.uniqid());
        config(['filesystems.disks.local.root' => $root]);

        $manager = app(DomainBackupManager::class);
        $method = new ReflectionMethod($manager, 'putBackupFile');
        $method->setAccessible(true);
        $method->invoke($manager, 'backups/domains/4/1-import.sql', '-- dump', 'dump');

        $this->assertFileExists($root.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'domains'.DIRECTORY_SEPARATOR.'4'.DIRECTORY_SEPARATOR.'1-import.sql');

        $this->deleteDirectory($root);
    }

    /**
     * @param  non-empty-string  $directory
     */
    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
