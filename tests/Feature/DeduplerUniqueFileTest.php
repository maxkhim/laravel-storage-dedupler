<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maxkhim\Dedupler\Facades\Dedupler;
use Maxkhim\Dedupler\FileSources\LocalFileAdapter;
use Maxkhim\Dedupler\Models\Article;
use Maxkhim\Dedupler\Models\UniqueFile;
use Mockery;

class DeduplerUniqueFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        //Storage::fake('public');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /**
     * @testdox Возможность сохранения файла из загруженного по HTTP
     * @test
     */
    public function itCanStoreFileFromUploadedFile()
    {
        $file = UploadedFile::fake()->image('fake_direct_uploaded_file.jpg', rand(100, 700), rand(100, 700));
        $hash = sha1($file->getContent());
        UniqueFile::query()->find($hash)?->delete();

        $uniqueFile = Dedupler::storeFromUploadedFile($file);

        $this->assertNotNull($uniqueFile);
        $this->assertEquals(
            'completed',
            $uniqueFile->status,
            'File status is not "completed"'
        );
        $this->assertEquals(
            $hash,
            $uniqueFile->sha1_hash,
            "File hash is incorrect"
        );
        $this->assertTrue(
            Storage::disk(config('dedupler.default_disk'))->exists($uniqueFile->path),
            "File is not stored correctly at disk: " .
            config('dedupler.default_disk') . ", file: " . $uniqueFile->path
        );
    }

    /**
     * @testdox Возможность сохранения файла из локальной структуры
     * @test
     */
    public function itCanStoreOnlyFileFromLocalFile()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, Str::random(100));

        $uniqueFile = Dedupler::storeFromPath($tempFile);

        $this->assertTrue(
            Storage::disk(config('dedupler.default_disk'))->exists($uniqueFile->path),
            "File is not stored correctly at disk: " .
            config('dedupler.default_disk') . ", file: " . $uniqueFile->path
        );

        unlink($tempFile);
    }

    /**
     * @testdox Возможность обнаружения дубликата файла
     * @test
     */
    public function itDetectsDuplicateFiles()
    {

        $file = UploadedFile::fake()->create('document.pdf', rand(10, 100));

        // First upload
        $firstFile = Dedupler::storeFromUploadedFile($file);

        // Second upload of same file
        $secondFile = Dedupler::storeFromUploadedFile($file);

        $this->assertEquals(
            $firstFile->id,
            $secondFile->id,
            "Files are not the same"
        );
    }

    /**
     * @testdox Возможность сохранения файла из потока
     * @test
     */
    public function itCanStoreFileFromStream()
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, Str::random(100));
        rewind($stream);

        $fileName = "stream_file_direct_" . date("YmdHis") . ".txt";

        $uniqueFile = Dedupler::storeFromStream(
            $stream,
            $fileName
        );

        $this->assertNotNull(
            $uniqueFile,
            "Relation is undefined"
        );

        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * @testdox Возможность сохранения файла непосредственно указывая содержимое при сохранении
     * @test
     */
    public function itCanStoreFileFromContent()
    {
        $content = 'This is file content: ' . Str::random(100);

        $uniqueFile = Dedupler::storeFromContent(
            $content,
            'direct_content_file.txt',
        );

        $this->assertNotNull($uniqueFile, "Relation is undefined");
        $this->assertEquals(strlen($content), $uniqueFile->size, "File size is incorrect");
    }

    /**
     * @testdox Возможность сохранить несколько файлов в одном запросе
     * @test
     */
    public function itCanStoreMultipleFiles()
    {
        $files = [
            UploadedFile::fake()->image('image' . rand(1, 100) . '.jpg', rand(100, 700), rand(100, 700)),
            UploadedFile::fake()->image('image' . rand(1, 100) . '.jpg', rand(100, 700), rand(100, 700)),
            UploadedFile::fake()->create(
                'document_' . rand(10000, 20000) . '.pdf',
                rand(1, 100),
                "application/pdf"
            ),
        ];

        $results = Dedupler::storeBatch($files);

        $this->assertCount(3, $results, "Incorrect number of processed files");
        $this->assertTrue($results[0]->exists);
        $this->assertEquals('completed', $results[0]->status);
    }
}
