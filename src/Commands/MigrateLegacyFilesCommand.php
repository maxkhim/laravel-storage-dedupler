<?php

namespace Maxkhim\Dedupler\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Maxkhim\Dedupler\Facades\Dedupler;
use Maxkhim\Dedupler\Models\LegacyFileMigration;
use Maxkhim\Dedupler\Models\UniqueFile;
use Symfony\Component\Finder\Finder;
use Maxkhim\Dedupler\Helpers\FormatingHelper;

use function Symfony\Component\String\b;

class MigrateLegacyFilesCommand extends Command
{
    protected $signature = 'dedupler:migrate-legacy 
                            {base-dir : The base directory to scan for legacy files}
                            {--rollback : Rollback the migration (restore original files)}
                            {--disk= : Disk to store files in (default: from config)}
                            {--chunk=100 : Number of files to process at a time}
                            {--dry-run : Perform a dry run without making changes}
                            {--force : Skip confirmation prompt}
                            {--keep-backups : Keep backup files after successful migration}';

    protected $description = 'Migrate legacy files to unique file storage or rollback migration';

    protected $processed = 0;
    protected $duplicates = 0;
    protected $errors = 0;
    protected $symlinksCreated = 0;
    protected $symlinksRemoved = 0;
    protected $filesRestored = 0;

    public function handle(): int
    {

        $baseDir = $this->argument('base-dir');
        $rollback = $this->option('rollback');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (!is_dir($baseDir)) {
            $this->error("Base directory does not exist: {$baseDir}");
            return 1;
        }

        if ($dryRun) {
            $this->info('🔍 Performing dry run - no files will be ' . ($rollback ? 'restored' : 'migrated'));
        }

        $action = $rollback ? 'rollback' : 'migrate';

        if (!$dryRun && !$force && !$this->confirm("This will {$action} legacy files. Continue?")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        if ($rollback) {
            $this->rollbackMigration($baseDir, $dryRun);
        } else {
            $this->migrateFiles($baseDir, $dryRun);
            if (!$dryRun && !$this->option('keep-backups')) {
                $this->cleanupBackupFiles($baseDir);
            }
        }

        $this->showOperationSummary($rollback);

        if ($dryRun) {
            $this->info('✅ Dry run completed. Review the output above before running without --dry-run');
        } else {
            $this->info('✅ ' . ($rollback ? 'Rollback' : 'Migration') . ' completed!');
        }

        return 0;
    }

    protected function migrateFiles(string $baseDir, bool $dryRun): void
    {
        $this->info("🔄 Migrating files from: {$baseDir}");

        $finder = new Finder();
        $finder->files()->in($baseDir)->ignoreDotFiles(true);

        $totalFiles = iterator_count($finder);
        $this->info("Found {$totalFiles} files to process");

        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->start();

        foreach ($finder as $file) {
            try {
                $this->migrateSingleFile($file->getRealPath(), $baseDir, $dryRun);
                $progressBar->advance();
            } catch (\Exception $e) {
                $this->error("Error processing file {$file->getRealPath()}: {$e->getMessage()}");
                $this->errors++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();
    }

    protected function migrateSingleFile(string $filePath, string $baseDir, bool $dryRun): void
    {
        // Skip if already a symlink
        if (is_link($filePath)) {
            $this->info("Skipping symlink: {$filePath}");
            return;
        }

        // Check if file already exists in storage by content
        $fileHash = sha1_file($filePath);
        $existingFile = UniqueFile::query()
            ->find($fileHash);

        $legacyFileMigration = $this->createLegacyMigrationFileRecord($filePath, $dryRun);


        if ($existingFile) {
            $this->duplicates++;
            $this->info("Duplicate found: {$filePath} -> {$existingFile->id}");
            $legacyFileMigration->has_duplicates = true;
        } else {
            // New file - migrate to storage
            if (!$dryRun) {
                $disk = $this->option('disk') ?? config('dedupler.default_disk');
                // Create file record without model binding
                $fileRecord = $this->createUniqueFileRecord($filePath, $disk);
                if ($fileRecord) {
                    $this->info("Migrated: {$filePath} -> {$fileRecord->id}");
                } else {
                    $legacyFileMigration->status = LegacyFileMigration::STATUS_ERROR;
                    $legacyFileMigration->save();
                    throw new \Exception("Failed to create file record");
                }
            } else {
                $this->info("Would migrate: {$filePath}");
            }
        }

        if (!$dryRun) {
            $legacyFileMigration->status = LegacyFileMigration::STATUS_MIGRATED;
            $legacyFileMigration->migrated_at = now();
            $legacyFileMigration->attachUniqueFile($fileHash);
            $legacyFileMigration->save();
        }

        unset($legacyFileMigration);


        // Create symlink pointing to storage file
        if (!$dryRun && ($existingFile || isset($fileRecord))) {
            $targetFile = $existingFile ?? $fileRecord;
            $this->createSymlink($filePath, $targetFile);
        }

        $this->processed++;
    }

    protected function createLegacyMigrationFileRecord(
        string $filePath,
        bool $dryRun = true
    ): ?LegacyFileMigration {


        $legacyFileMigration = null;
        $fileHash = sha1_file($filePath);
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileUpdatedAt = filemtime($filePath);
        $originalFileName = basename($filePath);
        $originalFilePath = pathinfo($filePath, PATHINFO_DIRNAME);
        $legacyFileMigration = [
            "original_path" => $originalFilePath,
            "original_filename" => $originalFileName,
            "sha1_hash" => $fileHash,
            "file_size" => $fileSize,
            "mime_type" => $mimeType,
            "status" => LegacyFileMigration::STATUS_PENDING,
            "file_modification_time" => $fileUpdatedAt,
        ];

        if (!$dryRun) {
            $legacyFileMigration = LegacyFileMigration::query()
                ->create($legacyFileMigration);
        } else {
            $legacyFileMigration = new LegacyFileMigration($legacyFileMigration);
        }


        return $legacyFileMigration;
    }

    protected function createUniqueFileRecord(string $filePath, string $disk): ?UniqueFile
    {
        $content = file_get_contents($filePath);
        $fileHash = sha1($content);
        $filename = basename($filePath);
        $path = $this->generateStoragePath($filePath, $fileHash);
        Storage::disk($disk)->put($path, $content);
        return UniqueFile::query()->
            create([
                'id' => $fileHash,
                'sha1_hash' => $fileHash,
                'md5_hash' => md5($content),
                'filename' => $filename,
                'path' => $path,
                'mime_type' => mime_content_type($filePath) ?: 'application/octet-stream',
                'size' => filesize($filePath),
                'status' => 'completed',
                'disk' => $disk,
                'original_name' => basename($filePath),
            ]);
    }

    protected function createSymlink(string $originalPath, UniqueFile $storageFile): void
    {
        try {
            // Get the actual storage path for local disks
            if (config("filesystems.disks.{$storageFile->disk}.driver") === 'local') {
                $storagePath = Storage::disk($storageFile->disk)->path($storageFile->path);

                // Create backup of original file
                $backupPath = $originalPath . '.backup';
                if (!file_exists($backupPath)) {
                    rename($originalPath, $backupPath);
                }

                // Create symlink
                if (symlink($storagePath, $originalPath)) {
                    $this->symlinksCreated++;
                } else {
                    // Restore backup if symlink failed
                    if (file_exists($backupPath)) {
                        rename($backupPath, $originalPath);
                    }
                    throw new \Exception('Failed to create symlink');
                }
            }
        } catch (\Exception $e) {
            $this->warn("Could not create symlink for {$originalPath}: {$e->getMessage()}");
        }
    }

    protected function rollbackMigration(string $baseDir, bool $dryRun): void
    {
        $this->info("🔄 Rolling back migration in: {$baseDir}");

        $finder = new Finder();
        $finder->files()->in($baseDir)->ignoreDotFiles(true);

        $totalFiles = iterator_count($finder);
        $this->info("Found {$totalFiles} files to check");

        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->start();

        foreach ($finder as $file) {
            try {
                $filePath = $file->getRealPath();
                $this->rollbackSingleFile($filePath, $dryRun);
                $progressBar->advance();
            } catch (\Exception $e) {
                $this->error("Error processing file {$file->getRealPath()}: {$e->getMessage()}");
                $this->errors++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();

        // Clean up backup files if requested
        if (!$dryRun && !$this->option('keep-backups')) {
            $this->alert('Cleaning up backup files...');
            $this->cleanupBackupFiles($baseDir);
        }
    }

    protected function rollbackSingleFile(string $filePath, bool $dryRun): void
    {
        $backupPath = $filePath . '.backup';

        // If it's a symlink and backup exists, restore original file
        if (is_link($filePath) && file_exists($backupPath)) {
            if (!$dryRun) {
                // Remove symlink
                unlink($filePath);

                // Restore backup
                rename($backupPath, $filePath);

                $this->symlinksRemoved++;
                $this->filesRestored++;
                $this->info("Restored: {$filePath}");
            } else {
                $this->info("Would restore: {$filePath}");
            }
        } elseif (is_link($filePath)) {
            $this->warn("Symlink without backup: {$filePath}");
        } elseif (file_exists($backupPath)) {
            $this->warn("Backup exists but original is not a symlink: {$filePath}");
        }

        $this->processed++;
    }

    protected function cleanupBackupFiles(string $baseDir): void
    {
        $this->info("Cleaning up backup files...");

        $finder = new Finder();
        $finder->files()->in($baseDir)->name('*.backup');

        $backupCount = iterator_count($finder);
        $removedCount = 0;

        foreach ($finder as $backupFile) {
            try {
                unlink($backupFile->getRealPath());
                $removedCount++;
            } catch (\Exception $e) {
                $this->warn("Could not remove backup: {$backupFile->getRealPath()}");
            }
        }

        $this->info("Removed {$removedCount} backup files");
    }

    protected function generateStoragePath(string $filePath, string $fileHash): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        // Use hash-based directory structure
        return substr($fileHash, 0, 2) . '/' .
            substr($fileHash, 2, 2) . '/' .
            $fileHash . ($extension ? '.' . $extension : '');
    }

    protected function showOperationSummary(bool $rollback): void
    {
        $this->info('');
        $this->info('📊 ' . ($rollback ? 'Rollback' : 'Migration') . ' Summary:');

        if ($rollback) {
            $this->line("   Files processed: {$this->processed}");
            $this->line("   Symlinks removed: {$this->symlinksRemoved}");
            $this->line("   Files restored: {$this->filesRestored}");
        } else {
            $this->line("   Files processed: {$this->processed}");
            $this->line("   Duplicates found: {$this->duplicates}");
            $this->line("   Symlinks created: {$this->symlinksCreated}");

            if ($this->duplicates > 0) {
                $savedSpace = $this->calculateSavedSpace();
                $this->info("   Estimated space saved: {$savedSpace}");
            }
        }

        $this->line("   Errors: {$this->errors}");
    }

    protected function calculateSavedSpace(): string
    {
        // Estimate space saved by deduplication
        $avgFileSize = 1024 * 1024; // 1MB average for estimation
        $savedBytes = $this->duplicates * $avgFileSize;
        return  FormatingHelper::formatBytes($savedBytes);
    }
}
