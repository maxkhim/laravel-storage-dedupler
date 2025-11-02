<?php

namespace Maxkhim\Dedupler\Commands;

use Illuminate\Console\Command;
use Maxkhim\Dedupler\Helpers\FormatingHelper;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

class AnalyseLegacyFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dedupler:analyse-legacy' .
        ' {directory : The base directory to scan for legacy files analysis}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Find duplicate files by SHA1 hash in directory and subdirectories' .
        ' and calculate potential disk space savings';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public const PROGRESS_CHUNK_SIZE = 100;

    private function getProgressChunkSize(): int
    {
        return self::PROGRESS_CHUNK_SIZE;
    }

    public function handle()
    {
        $directory = $this->argument('directory');
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);

        if (!is_dir($directory)) {
            $this->error("The specified directory does not exist: {$directory}");
            return 1;
        }

        $this->info("Searching for duplicate files in directory: {$directory}");
        $this->info("This may take some time...");
        $this->newLine();

        $fileHashes = [];

        // Статистика для прогрессбара
        $processedFiles = 0;
        $processedSize = 0;
        $currentDuplicateCount = 0;
        $currentDuplicateSize = 0;

        // Получаем общее количество файлов для прогрессбара
        $totalFiles = $this->countFiles($directory);

        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->setFormat(
            "%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%\n%message%"
        );

        $progressBar->setMessage('Analyzing ' . $totalFiles . ' file(s)...');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
            )
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                $progressBar->advance();
                continue;
            }

            $filePath = $file->getRealPath();
            if ($filePath === false) {
                $progressBar->advance();
                continue;
            }

            $fileSize = $file->getSize();
            $hash = @sha1_file($filePath);

            if ($hash === false) {
                $progressBar->advance();
                continue;
            }

            // Обновляем статистику
            $processedFiles++;
            $processedSize += $fileSize;

            // Обрабатываем уникальность
            if (!isset($fileHashes[$hash])) {
                $fileHashes[$hash] = [
                    'count' => 1,
                    'size' => $fileSize,
                ];
            } else {
                $fileHashes[$hash]['count']++;
                $fileHashes[$hash]['size'] += $fileSize;
                if ($fileHashes[$hash]['count'] == 2) {
                    $currentDuplicateCount += 2;
                    $currentDuplicateSize += $fileHashes[$hash]['size'];
                } else {
                    $currentDuplicateCount++;
                    $currentDuplicateSize += $fileSize;
                }
            }

            // Обновляем статус прогрессбара каждые PROGRESS_CHUNK_SIZE файлов или на последнем файле
            if (
                $processedFiles % $this->getProgressChunkSize() == 0
                ||
                $processedFiles == $totalFiles
            ) {
                $message = sprintf(
                    "Processed: %s files (%s) | Duplicates: %s files (%s)",
                    number_format($processedFiles),
                    FormatingHelper::formatBytes($processedSize),
                    number_format($currentDuplicateCount),
                    FormatingHelper::formatBytes($currentDuplicateSize)
                );
                $progressBar->setMessage($message);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();


        $totalDuplicatesSize = 0;
        $totalDuplicatesCount = 0;
        // Финальный расчет
        foreach ($fileHashes as $hashData) {
            if ($hashData['count'] > 1) {
                $totalDuplicatesCount += $hashData['count'];
                $totalDuplicatesSize += $hashData['size'];
            }
        }

        // Вывод результатов
        $this->info("=== Analysis Results ===");
        $this->line("Total files processed: <comment>" . number_format($processedFiles) . "</comment>");
        $this->line("Total files size: <comment>" . FormatingHelper::formatBytes($processedSize) . "</comment>");
        $this->line("Duplicate files count: <comment>" . number_format($totalDuplicatesCount) . " files</comment>");
        $this->line("Total duplicates size: <comment>" .
            FormatingHelper::formatBytes($totalDuplicatesSize) . "</comment>");

        if ($totalDuplicatesCount > 0) {
            $this->newLine();
            $uniqueGroups = count(array_filter($fileHashes, fn ($data) => $data['count'] > 1));
            $potentialSavings = $totalDuplicatesSize - ($totalDuplicatesSize / $totalDuplicatesCount * $uniqueGroups);
            $this->warn(
                "Potential disk space savings: " .
                FormatingHelper::formatBytes($potentialSavings) .
                " [ "  . round(($potentialSavings / ($processedSize * 1.0)) * 100, 2) . " % ]"
            );
        }

        return 0;
    }

    /**
     * Подсчитывает общее количество файлов в директории
     */
    private function countFiles(string $directory): int
    {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }
}
