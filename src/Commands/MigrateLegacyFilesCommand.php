<?php

namespace Maxkhim\Dedupler\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Maxkhim\Dedupler\Models\LegacyFileMigration;
use Maxkhim\Dedupler\Models\UniqueFile;
use Symfony\Component\Finder\Finder;
use Maxkhim\Dedupler\Commands\Traits\CommonMigrationTrait;

class MigrateLegacyFilesCommand extends Command
{
    use CommonMigrationTrait;

    protected $signature = 'dedupler:migrate-legacy 
                            {base-dir : The base directory to scan for legacy files}
                            {--disk= : Disk to store files in (default: from config)}
                            {--chunk=100 : Number of files to process at a time}
                            {--dry-run : Perform a dry run without making changes}
                            {--force : Skip confirmation prompt}
                            {--keep-backups : Keep backup files after successful migration}';

    protected $description = 'Migrate legacy files to unique file storage';

    protected int $processed = 0;
    protected int $duplicates = 0;
    protected int $errors = 0;
    protected int $symlinksCreated = 0;

    protected int $savedSpace = 0;

    public function handle(): int
    {
        $baseDir = $this->argument('base-dir');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (!is_dir($baseDir)) {
            $this->error("Base directory does not exist: {$baseDir}");
            return 1;
        }

        if ($dryRun) {
            $this->info('🔍 Performing dry run - no files will be migrated');
        }

        if (!$dryRun && !$force && !$this->confirm("This will migrate legacy files. Continue?")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->migrateFiles($baseDir, $dryRun);

        if (!$dryRun && !$this->option('keep-backups')) {
            $this->cleanupBackupFiles($baseDir);
        }

        $this->showOperationSummary();

        if ($dryRun) {
            $this->info('✅ Dry run completed. Review the output above before running without --dry-run');
        } else {
            $this->info('✅ Migration completed!');
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
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %memory:6s% \n %message%");
        $progressBar->start();

        foreach ($finder as $file) {
            try {
                if (($file->isFile() && !$file->isLink()) || $dryRun) {
                    $this->migrateSingleFile($file->getRealPath(), $baseDir, $dryRun);
                }
                $progressBar->setMessage($file->getRelativePathname());
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
        // Check if file already exists in storage by content
        $fileHash = sha1_file($filePath);
        $existingFile = UniqueFile::query()->find($fileHash);
        $legacyFileMigration = $this->createOrFirstLegacyMigrationFileRecord($filePath, $dryRun);

        if ($existingFile) {
            $this->duplicates++;
            $this->info("Duplicate found: {$filePath} -> {$existingFile->id}");
            $this->savedSpace += $existingFile->size;
            $legacyFileMigration->has_duplicates = true;
        } else {
            // New file - migrate to storage
            if (!$dryRun) {
                $disk = $this->option('disk') ?? config('dedupler.default_disk');
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

    protected function showOperationSummary(): void
    {
        $this->info('');
        $this->info('📊 Migration Summary:');
        $this->line("   Files processed: {$this->processed}");
        $this->line("   Duplicates found: {$this->duplicates}");
        $this->line("   Symlinks created: {$this->symlinksCreated}");

        if ($this->duplicates > 0) {
            $savedSpace = $this->calculateSavedSpace();
            $this->info("   Estimated space saved: {$savedSpace}");
        }

        $this->line("   Errors: {$this->errors}");
    }
}
