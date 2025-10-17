<?php

declare(strict_types=1);

namespace Maxkhim\Dedupler\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Maxkhim\Dedupler\Helpers\FormatingHelper;
use Maxkhim\Dedupler\Models\UniqueFile;
use Maxkhim\Dedupler\Models\Deduplicatable;
use Illuminate\Support\Facades\DB;

class CleanupFilesCommand extends Command
{
    /**
     * Имя команды
     *
     * @var string
     */
    protected $signature = 'dedupler:files-cleanup
                            {--dry-run : Perform a dry run without deleting anything / Только просмотр без удаления}
                            {--force : Skip confirmation prompt / Пропустить подтверждение}
                            {--chunk=1000 : Number of records process at a time / Кол-во записей для обработки за раз}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очистить несвязанные файлы и связи  /  Clean up orphaned files and relationships';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        DB::setDefaultConnection("dedupler");
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk');

        if ($dryRun) {
            $this->info('🔍 Performing dry run - no files will be deleted');
            $this->info('🔍 Выполняется сухой прогон — файлы не будут удалены');
        } else {
            if (
                !$force && !$this->confirm(
                    'This will permanently delete orphaned files and relationships. Continue?' .
                    ' Это удалит несвязанные файлы и связи. Продолжить?'
                )
            ) {
                $this->info('Cleanup cancelled.');
                $this->info('Очистка отменена.');
                return 0;
            }
        }

        $this->cleanupOrphanedRelationships($dryRun, $chunkSize);
        // Очистка файлов
        $this->cleanupOrphanedFiles($dryRun, $chunkSize);

        if ($dryRun) {
            $this->info('✅ Dry run completed. Review the output above before running without --dry-run');
            $this->info('✅ Сухой прогон завершен. Просмотрите вывод выше перед запуском без --dry-run');
        } else {
            $this->info('✅ Cleanup completed successfully!');
            $this->info('✅ Очистка завершена успешно!');
        }

        return 0;
    }

    /**
     * Clean up relationships that point to non-existent files
     */
    protected function cleanupOrphanedRelationships(bool $dryRun, int $chunkSize): void
    {
        $this->info('Checking for orphaned relationships...');
        $this->info('Проверка на наличие несвязанных связей...');

        $orphanedRelationsCount = DB::table('dedupler_deduplicatables as rel')
            ->leftJoin('dedupler_unique_files as file', 'rel.sha1_hash', '=', 'file.id')
            ->whereNull('file.id')
            ->count();

        if ($orphanedRelationsCount === 0) {
            $this->info('No orphaned relationships found.');
            $this->info('Несвязанные связи не найдены.');
            return;
        }

        $this->warn("Found {$orphanedRelationsCount} orphaned relationships.");
        $this->warn("Найдено {$orphanedRelationsCount} несвязанных связей.");

        if (!$dryRun) {
            $progressBar = $this->output->createProgressBar($orphanedRelationsCount);
            $progressBar->start();

            DB::table('dedupler_deduplicatables as rel')
                ->select('rel.id')
                ->leftJoin('dedupler_unique_files as file', 'rel.sha1_hash', '=', 'file.id')
                ->whereNull('file.id')
                ->chunkById($chunkSize, function ($relations) use ($progressBar) {
                    $ids = $relations->pluck('id')->toArray();
                    Deduplicatable::query()
                        ->whereIn('id', $ids)->delete();
                    $progressBar->advance(count($ids));
                });
            $progressBar->finish();
            $this->newLine();
        }

        $this->info("Orphaned relationships cleanup " .
            ($dryRun ? 'would remove' : 'removed') . " {$orphanedRelationsCount} records.");
        $this->info("Очистка несвязанных связей " .
            ($dryRun ? 'была бы удалена' : 'удалена') . " {$orphanedRelationsCount} записей.");
    }

    /**
     * Clean up files that have no relationships
     */
    protected function cleanupOrphanedFiles(bool $dryRun, int $chunkSize): void
    {
        $this->info('Checking for orphaned files...');
        $this->info('Проверка на наличие несвязанных файлов...');

        $orphanedFilesCount = DB::table('dedupler_unique_files as file')
            ->leftJoin('dedupler_deduplicatables as rel', 'file.id', '=', 'rel.sha1_hash')
            ->whereNull('rel.id')
            ->count();

        if ($orphanedFilesCount === 0) {
            $this->info('No orphaned files found.');
            $this->info('Несвязанные файлы не найдены.');
            return;
        }

        $this->warn("Found {$orphanedFilesCount} orphaned files.");
        $this->warn("Найдено {$orphanedFilesCount} несвязанных файлов.");

        $deletedFiles = 0;
        $deletedBytes = 0;
        $errors = [];

        if (!$dryRun) {
            $progressBar = $this->output->createProgressBar($orphanedFilesCount);
            $progressBar->start();

            DB::table('dedupler_unique_files as file')
                ->leftJoin('dedupler_deduplicatables as rel', 'file.id', '=', 'rel.sha1_hash')
                ->whereNull('rel.id')
                ->select('file.*')
                ->chunkById(
                    $chunkSize,
                    function ($files) use (&$deletedFiles, &$deletedBytes, &$errors, $progressBar, $dryRun) {
                        foreach ($files as $file) {
                            try {
                                if (!$dryRun) {
                                    // Delete physical file from storage
                                    if (Storage::disk($file->disk)->exists($file->path)) {
                                        Storage::disk($file->disk)->delete($file->path);
                                        $deletedBytes += $file->size;
                                    }

                                    // Delete database record
                                    UniqueFile::query()->where('id', $file->id)->delete();
                                }
                                $deletedFiles++;
                            } catch (\Exception $e) {
                                $errors[] = "Failed to delete file {$file->id}: {$e->getMessage()}";
                                $errors[] = "Не удалось удалить файл {$file->id}: {$e->getMessage()}";
                            }
                            $progressBar->advance();
                        }
                    }
                );

            $progressBar->finish();
            $this->newLine();
        } else {
            $deletedFiles = $orphanedFilesCount;
            $deletedBytes = DB::table('dedupler_unique_files as file')
                ->leftJoin('dedupler_deduplicatables as rel', 'file.id', '=', 'rel.sha1_hash')
                ->whereNull('rel.id')
                ->sum('file.size');
        }

        $this->info("Orphaned files cleanup " . ($dryRun ? 'would remove' : 'removed') . " {$deletedFiles} files.");
        $this->info(
            "Очистка несвязанных файлов " .
            ($dryRun ? 'была бы удалена' : 'удалена') . " {$deletedFiles} файлов."
        );
        $this->info("Total storage space " .
            ($dryRun ? 'that would be freed' : 'freed') . ": " . FormatingHelper::formatBytes((int)$deletedBytes));
        $this->info("Общий объем освобожденного пространства " .
            ($dryRun ? 'который был бы освобожден' : 'который был освобожден') .
            ": " . FormatingHelper::formatBytes((int)$deletedBytes));

        if (!empty($errors)) {
            $this->error('Errors encountered during cleanup:');
            $this->error('Во время очистки возникли ошибки:');
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }
    }
}
