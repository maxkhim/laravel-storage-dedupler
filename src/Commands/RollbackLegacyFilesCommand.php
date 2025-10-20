<?php

namespace Maxkhim\Dedupler\Commands;

use Illuminate\Console\Command;
use Maxkhim\Dedupler\Models\LegacyFileMigration;
use Symfony\Component\Finder\Finder;
use Maxkhim\Dedupler\Commands\Traits\CommonMigrationTrait;
use Symfony\Component\Finder\SplFileInfo;

class RollbackLegacyFilesCommand extends Command
{
    use CommonMigrationTrait;

    protected $signature = 'dedupler:rollback-legacy
                            {base-dir : The base directory to scan for legacy files}
                            {--dry-run : Perform a dry run without making changes}
                            {--force : Skip confirmation prompt}
                            {--keep-backups : Keep backup files after rollback}';

    protected $description = 'Rollback legacy files migration';

    protected int $processed = 0;
    protected int $errors = 0;
    protected int $symlinksRemoved = 0;
    protected int $filesRestored = 0;

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
            $this->info('🔍 Performing dry run - no files will be restored');
        }

        if (!$dryRun && !$force && !$this->confirm("This will rollback legacy files migration. Continue?")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->rollbackMigration($baseDir, $dryRun);

        if (!$dryRun && !$this->option('keep-backups')) {
            $this->alert('Cleaning up backup files...');
            $this->cleanupBackupFiles($baseDir);
        }

        $this->showOperationSummary();

        if ($dryRun) {
            $this->info('✅ Dry run completed. Review the output above before running without --dry-run');
        } else {
            $this->info('✅ Rollback completed!');
        }

        return 0;
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
                $this->rollbackSingleFile($filePath, $dryRun, $file);
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

    protected function rollbackSingleFile(string $filePath, bool $dryRun, SplFileInfo $file): void
    {
        $legacyFileMigration =
            LegacyFileMigration::findLegacyFileMigrationByOriginalPath($file->getPath(), $file->getFilename());

        $backupFile = null;
        if ($file->isLink() && $file->isFile() && $legacyFileMigration) {
            $hash = pathinfo($file->getLinkTarget(), PATHINFO_FILENAME);
            $backupFile = $file->getLinkTarget();
        }

        // If it's a symlink and backup exists, restore original file
        if ($file->isLink() && $legacyFileMigration && is_file($backupFile)) {
            if (!$dryRun) {
                // Remove symlink
                unlink($file->getPathname());
                // Restore backup
                copy($backupFile, $file->getPathname());
                $this->symlinksRemoved++;
                $this->filesRestored++;
                $this->info("Restored: " . $file->getPathname() . " ($filePath)");
            } else {
                $this->info("Would restore: {$filePath}");
            }
        } else {
            $this->warn("Unrestorable file: $filePath ( " . ($backupFile ?? "No linked file") . " )");
        }

        $this->processed++;
    }

    protected function showOperationSummary(): void
    {
        $this->info('');
        $this->info('📊 Rollback Summary:');
        $this->line("   Files processed: {$this->processed}");
        $this->line("   Symlinks removed: {$this->symlinksRemoved}");
        $this->line("   Files restored: {$this->filesRestored}");
        $this->line("   Errors: {$this->errors}");
    }
}
