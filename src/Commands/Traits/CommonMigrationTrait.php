<?php

namespace Maxkhim\Dedupler\Commands\Traits;

use Illuminate\Support\Facades\Storage;
use Maxkhim\Dedupler\Models\LegacyFileMigration;
use Maxkhim\Dedupler\Models\UniqueFile;
use Maxkhim\Dedupler\Helpers\FormatingHelper;

trait CommonMigrationTrait
{
    protected function createOrFirstLegacyMigrationFileRecord(
        string $filePath,
        bool $dryRun = true
    ): ?LegacyFileMigration {
        $fileHash = sha1_file($filePath);
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileUpdatedAt = filemtime($filePath);
        $originalFileName = basename($filePath);
        $originalFilePath = pathinfo($filePath, PATHINFO_DIRNAME);

        $legacyFileMigrationAttributes = [
            "original_path" => $originalFilePath,
            "original_filename" => $originalFileName,
            "sha1_hash" => $fileHash,
            "file_size" => $fileSize,
            "mime_type" => $mimeType,
            "status" => LegacyFileMigration::STATUS_PENDING,
            "file_modificated_at" => $fileUpdatedAt,
        ];

        if (!$dryRun) {
            $legacyFileMigration = LegacyFileMigration::findLegacyFileMigrationByOriginalPath(
                $originalFilePath,
                $originalFileName
            );
            if (!$legacyFileMigration) {
                $legacyFileMigration = LegacyFileMigration::query()->create($legacyFileMigrationAttributes);
            }
        } else {
            $legacyFileMigration = new LegacyFileMigration($legacyFileMigrationAttributes);
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

        return UniqueFile::query()->create([
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

    protected function generateStoragePath(string $filePath, string $fileHash): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        return substr($fileHash, 0, 2) . '/' .
            substr($fileHash, 2, 2) . '/' .
            $fileHash . ($extension ? '.' . $extension : '');
    }

    protected function calculateSavedSpace(): string
    {
        $savedBytes = $this->savedSpace ?? 0;
        return FormatingHelper::formatBytes($savedBytes);
    }

    protected function cleanupBackupFiles(string $baseDir): void
    {
        $this->info("Cleaning up backup files...");

        $finder = new \Symfony\Component\Finder\Finder();
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
}
