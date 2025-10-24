<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Storage;
use Maxkhim\Dedupler\Models\Deduplicatable;
use Maxkhim\Dedupler\Models\LegacyFileMigration;
use Mockery;

class DeduplerLegacyMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        //Storage::fake('dedupler');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    private function createFakedLegacyFile(): LegacyFileMigration
    {
        /** @var LegacyFileMigration $legacyFile */
        $legacyFile = LegacyFileMigration::factory()->create();
        return $legacyFile;
    }

    /**
     * @testdox Создание записи о файле из Legacy структуры в LegacyFileMigration
     * @test
     */
    public function canCreateLegacyFileMigration(): void
    {

        $legacyFile = $this->createFakedLegacyFile();
        $this->assertNotNull($legacyFile, "Can't create legacy file");

        $this->assertEquals(
            LegacyFileMigration::STATUS_PENDING,
            $legacyFile->status,
            'File status is not "pending"'
        );
    }

    /**
     * @testdox Может создать уникальный файл из Legacy структуры
     * @test
     */
    public function canCreateUniqueFileFromLegacyFile(): void
    {
        $legacyFile = $this->createFakedLegacyFile();
        $this->assertNotNull($legacyFile, "Can't create legacy file");

        $uniqueFile = $legacyFile->storeLocalFile(
            $legacyFile->original_path . "/" . $legacyFile->original_filename,
            ["original_name" => "file_1.data"]
        );

        $this->assertNotNull(
            $uniqueFile,
            'Cant create unique file from legacy file'
        );
    }

    /**
     * @testdox Проверка на дублирование файлов при синхронизации
     * @test
     */
    public function canCheckDuplicatedFile(): void
    {

        $legacyFile = $this->createFakedLegacyFile();
        $this->assertNotNull($legacyFile, "Can't create legacy file");

        $uniqueFile1 = $legacyFile->storeLocalFile(
            $legacyFile->original_path . "/" . $legacyFile->original_filename,
            ["original_name" => "file_1.data"]
        );
        $uniqueFile2 = $legacyFile->storeLocalFile(
            $legacyFile->original_path . "/" . $legacyFile->original_filename,
            ["original_name" => "file_2.data"]
        );

        $countLinkedFiles = Deduplicatable::query()->where(
            [
                "deduplable_id" => $legacyFile->getKey(),
                "deduplable_type" => LegacyFileMigration::class,
                "sha1_hash" => $uniqueFile2->sha1_hash,
            ]
        )->count();

        $this->assertEquals(
            $countLinkedFiles,
            1,
            "Not only one attachment exists"
        );

        $this->assertNotNull(
            $uniqueFile1,
            'Cant create unique file from legacy file'
        );

        $this->assertNotNull(
            $uniqueFile1,
            'Cant create second unique file from legacy file'
        );

        $this->assertEquals(
            $uniqueFile1->id,
            $uniqueFile2->id,
            "Files are not the same"
        );
    }

    /**
     * @testdox Ошибка при осуществлении миграции из Legacy структуры (копирование файла + невозможно создание symlink)
     * @test
     */
    public function cannotMigrateLegacyFile(): void
    {
        $legacyFile = $this->createFakedLegacyFile();
        $this->assertNotNull($legacyFile, "Can't create legacy file");

        $uniqueFile = $legacyFile->copyLegacyFileToUniqueFile();

        $this->assertNotNull(
            $uniqueFile,
            "Can't create unique file from legacy file"
        );

        $this->assertEquals(
            LegacyFileMigration::STATUS_PROCESSING,
            $legacyFile->status,
            'Can\'t perform file copy to UniqueFile'
        );

        $this->assertTrue(
            Storage::disk(config('dedupler.default_disk'))->delete($uniqueFile->path),
            "Can't delete file: " . $uniqueFile->path
        );

        $this->assertFalse(
            $legacyFile->replaceLegacyFileWithSymlinkToUniqueFile(),
            "Successfully symlink creation for not existed unique file"
        );
    }

    /**
     * @testdox Может осуществить миграцию из Legacy структуры (копирование файла + создание symlink)
     * @test
     */
    public function canMigrateLegacyFile(): void
    {
        $legacyFile = $this->createFakedLegacyFile();
        $this->assertNotNull($legacyFile, "Can't create legacy file");

        $uniqueFile = $legacyFile->copyLegacyFileToUniqueFile();

        $this->assertNotNull(
            $uniqueFile,
            'Cant create unique file from legacy file'
        );

        $this->assertEquals(
            LegacyFileMigration::STATUS_PROCESSING,
            $legacyFile->status,
            'Can\'t perform file copy to UniqueFile'
        );

        $this->assertTrue(
            $legacyFile->replaceLegacyFileWithSymlinkToUniqueFile(),
            "Can't replace legacy file with symlink to UniqueFile: " . $legacyFile->error_message
        );

        $this->assertEquals(
            LegacyFileMigration::STATUS_MIGRATED,
            $legacyFile->status,
            'Can\'t complete file symlink UniqueFile -> LegacyFile'
        );
    }

}
