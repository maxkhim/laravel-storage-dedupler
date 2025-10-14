<?php

declare(strict_types=1);

namespace Maxkhim\UniqueFileStorage\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Maxkhim\UniqueFileStorage\Models\UniqueUploadedFile;
use Maxkhim\UniqueFileStorage\Models\UniqueUploadedFileToModel;
use Illuminate\Support\Facades\DB;

class FileStorageStatsCommand extends Command
{
    /**
     * Имя команды
     *
     * @var string
     */
    protected $signature = 'unique-file-storage:files-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display file storage statistics / Отображение статистики хранилища файлов';

    public function handle(): int
    {
        DB::setDefaultConnection("unique_file_storage");
        $stats = $this->getStorageStats();

        $this->info('📊 File Storage Statistics / Статистика хранилища файлов');
        $this->line('');

        $this->table(
            ['Metric / Метрика', 'Value / Значение'],
            [
                ['Total Files / Всего файлов', $stats['total_files']],
                ['Total Relationships / Всего связей', $stats['total_relationships']],
                ['Files with Relationships / Файлов со связями', $stats['files_with_relationships']],
                ['Orphaned Files / Несвязанных файлов', $stats['files_without_relationships']],
                ['Total Storage Used / Всего хранилища использовано', $this->formatBytes($stats['total_storage_used'])],
            ]
        );

        // Disk usage breakdown
        $diskUsage = DB::table('unique_uploaded_files')
            ->select('disk', DB::raw('COUNT(*) as file_count'), DB::raw('SUM(size) as total_size'))
            ->groupBy('disk')
            ->get();

        if ($diskUsage->isNotEmpty()) {
            $this->line('');
            $this->info('💽 Disk Usage Breakdown / Распределение дисков');
            $diskData = $diskUsage->map(function ($disk) {
                return [
                    'Disk' => $disk->disk,
                    'Files' => $disk->file_count,
                    'Size' => $this->formatBytes((int)$disk->total_size),
                ];
            })->toArray();

            $this->table(['Disk / Диск', 'Files / Файлов', 'Size / Размер'], $diskData);
        }

        // File type breakdown
        $fileTypes = DB::table('unique_uploaded_files')
            ->select(DB::raw('SUBSTRING_INDEX(mime_type, "/", 1) as file_type'), DB::raw('COUNT(*) as count'))
            ->whereNotNull('mime_type')
            ->groupBy('file_type')
            ->orderByDesc('count')
            ->get();

        if ($fileTypes->isNotEmpty()) {
            $this->line('');
            $this->info('📄 File Type Breakdown / Распределение типов файлов');
            $typeData = $fileTypes->map(function ($type) {
                return [
                    'Type' => $type->file_type,
                    'Count' => $type->count,
                ];
            })->toArray();

            $this->table(['File Type / Тип файла', 'Count / Шт.'], $typeData);
        }

        return 0;
    }

    protected function getStorageStats(): array
    {
        $totalFiles = UniqueUploadedFile::query()
            ->count();
        $totalRelationships = UniqueUploadedFileToModel::query()
            ->count();

        $filesWithRelationships = DB::table('unique_uploaded_files as file')
            ->join('unique_uploaded_files_to_models as rel', 'file.id', '=', 'rel.sha1_hash')
            ->distinct('file.id')
            ->count('file.id');

        $filesWithoutRelationships = $totalFiles - $filesWithRelationships;

        $totalStorageUsed = UniqueUploadedFile::query()->sum('size');

        return [
            'total_files' => $totalFiles,
            'total_relationships' => $totalRelationships,
            'files_with_relationships' => $filesWithRelationships,
            'files_without_relationships' => $filesWithoutRelationships,
            'total_storage_used' => (int)$totalStorageUsed,
        ];
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}