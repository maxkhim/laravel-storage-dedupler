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

class DeduplerTest extends TestCase
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

        $article = Article::create();

        $file = UploadedFile::fake()->image('fake_uploaded_file.jpg', 100, 100);
        $hash = sha1($file->getContent());
        UniqueFile::query()->find($hash)?->delete();

        $uniqueFile = Dedupler::storeFromUploadedFile($file);

        $fileRelation = Dedupler::attach($uniqueFile->id, $article, [
            'pivot' => ['status' => 'completed']
        ]);

        $this->assertNotNull($fileRelation);
        $this->assertEquals(
            'completed',
            $fileRelation->status,
            'File status is not "completed"'
        );
        $this->assertEquals(
            'fake_uploaded_file.jpg',
            $fileRelation->file->original_name,
            "File original_name is not fake_uploaded_file.jpg"
        );
        $this->assertTrue(
            Storage::disk(config('dedupler.default_disk'))->exists($fileRelation->file->path),
            "File is not stored correctly at disk: " .
            config('dedupler.default_disk') . ", file: " . $fileRelation->file->path
        );
    }



    /**
     * @testdox Возможность сохранения файла из локальной структуры (без связи)
     * @test
     */
    public function itCanStoreOnlyFileFromLocalFile()
    {
        /** @var Article $article */
        $article = Article::create();
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
     * @testdox Возможность сохранения файла из локальной структуры
     * @test
     */
    public function itCanStoreFileFromLocalFile()
    {
        /** @var Article $article */
        $article = Article::create();
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        $file = Dedupler::storeFromPath($tempFile);

        $fileRelation = Dedupler::attach($file->id, $article, [
            'original_name' => 'imported_file.txt'
        ]);

        $this->assertNotNull($fileRelation);
        $this->assertEquals('imported_file.txt', $fileRelation->file->original_name);
        unlink($tempFile);
    }

    /**
     * @testdox Возможность обнаружения дубликата файла
     * @test
     */
    public function itDetectsDuplicateFiles()
    {
        $article = Article::create();
        $file = UploadedFile::fake()->create('document.pdf', rand(10, 100));

        // First upload
        $firstRelation = Dedupler::storeFromUploadedFile($file, $article);


        // Second upload of same file
        $secondRelation = Dedupler::storeFromUploadedFile($file, $article);

        $this->assertEquals(
            $firstRelation->file->id,
            $secondRelation->file->id,
            "Files are not the same"
        );
    }


    /**
     * @testdox Возможность сохранения файла из потока
     * @test
     */
    public function itCanStoreFileFromStream()
    {
        $article = Article::create();
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, Str::random(100));//'stream content'
        rewind($stream);

        $fileName = "stream_file" . date("YmdHis") . ".txt";

        $fileRelation = Dedupler::storeFromStream(
            $stream,
            $fileName,
            $article,
            [
                'mime_type' => 'text/plain',
            ]
        );

        $this->assertNotNull(
            $fileRelation,
            "Relation is undefined"
        );

        $this->assertEquals(
            $fileName,
            $fileRelation->file->original_name,
            "Incorrect file original_name"
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
        $article = Article::create();
        $content = 'This is file content';

        $fileRelation = Dedupler::storeFromContent(
            $content,
            'direct_content_file.txt',
            $article,
            ['mime_type' => 'text/plain']
        );

        $this->assertNotNull($fileRelation, "Relation is undefined");
        $this->assertEquals(strlen($content), $fileRelation->file->size, "File size is incorrect");
    }


    /**
     * @testdox Возможность присоединить загруженный файл к двум моделям с разным названием
     * @test
     */
    public function itCanAttachFileToModel()
    {
        $article1 = Article::create();
        $article2 = Article::create();
        $file = UploadedFile::fake()->image('avatar.jpg');

        // Store file with first
        $firstRelation = Dedupler::storeFromUploadedFile($file, $article1);
        $fileHash = $firstRelation->file->id;

        // Attach same file to second
        $secondRelation = Dedupler::attach($fileHash, $article2, [
            'original_name' => 'different_name.jpg'
        ]);

        $this->assertNotNull($secondRelation, "Relation is undefined");
        $this->assertEquals(
            'different_name.jpg',
            $secondRelation->original_name,
            "Incorrect file original_name"
        );
    }

    /**
     * @testdox Возможность отсоединить загруженный файл от модели
     * @test
     */
    public function itCanDetachFileFromModel()
    {
        $article = Article::create();
        $article2 = Article::create();
        $file = UploadedFile::fake()->image('test.jpg', rand(10, 100), rand(10, 100));

        $fileRelation = Dedupler::storeFromUploadedFile($file, $article);
        $fileHash = $fileRelation->file->id;

        $secondRelation = Dedupler::attach($fileHash, $article2, [
            'original_name' => 'different_name.jpg'
        ]);

        $success = Dedupler::detach($fileHash, $article);

        $this->assertTrue($success, "Detach failed");
        $this->assertEquals(0, $article->uniqueFiles()->count(), "Some files are left");
    }


    /**
     * @testdox Нет возможности удалить файл, если он используется (есть ссылки на него)
     * @test
     */
    public function itCannotDeleteFileWhenNoReferencesRemain()
    {
        $article = Article::create();
        $file = UploadedFile::fake()->image('test_ref_exists.jpg', rand(10, 100), rand(10, 100));

        $fileRelation = Dedupler::storeFromUploadedFile($file, $article);
        $fileHash = $fileRelation->file->id;

        // Then delete
        $success = Dedupler::delete($fileHash);

        $this->assertFalse($success, "Delete is success");
        $this->assertNotNull(
            UniqueFile::query()->find($fileHash),
            "File is exists after deletion"
        );
    }

    /**
     * @testdox Форсированное удаление файла, если он используется (есть ссылки на него)
     * @test
     */
    public function itCanForceDeleteFileWhenNoReferencesRemain()
    {
        $article = Article::create();
        $file = UploadedFile::fake()->image('test_ref_exists_force.jpg', rand(10, 100), rand(10, 100));

        $fileRelation = Dedupler::storeFromUploadedFile($file, $article);
        $fileHash = $fileRelation->file->id;

        // Then delete
        $success = Dedupler::delete($fileHash, true);

        $this->assertTrue($success, "Delete is failed");
        $this->assertNull(
            UniqueFile::query()->find($fileHash),
            "File is exists after deletion"
        );
    }

    /**
     * @testdox Возможность получить URL файла
     * @test
     */
    public function itReturnsFileUrl()
    {
        $article = Article::create();
        $file = UploadedFile::fake()->image('test_url.jpg');

        $fileRelation = Dedupler::storeFromUploadedFile($file, $article);
        $url = Dedupler::getUrl($fileRelation->file->id);
        $this->assertStringContainsString($fileRelation->file->path, $url);
    }

    /**
     * @testdox Возможность сохранить несколько файлов в одном запросе
     * @test
     */
    public function itCanStoreMultipleFiles()
    {
        $article = Article::create();
        $files = [
            UploadedFile::fake()->image('image1.jpg'),
            UploadedFile::fake()->image('image2.jpg'),
            UploadedFile::fake()->create('document_' . rand(10, 100) . '.pdf', rand(10, 100)),
        ];

        $results = Dedupler::storeBatch($files, $article, [
            'pivot' => ['status' => 'completed']
        ]);

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->file->exists);
        $this->assertEquals('completed', $results[0]->status);
    }


    /**
     * @testdox Проверка конкурентной загрузки
     * @test
     */
    /*public function itProtectsAgainstConcurrentUploads()
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $quickHash = Dedupler::getQuickHash($file);

        // Acquire lock
        $lockAcquired = Dedupler::acquireUploadLock($quickHash);
        $this->assertTrue($lockAcquired);

        // Try to acquire same lock again
        $secondLock = Dedupler::acquireUploadLock($quickHash);
        $this->assertFalse($secondLock);

        // Check if file is processing
        $isProcessing = Dedupler::isFileProcessing($quickHash);
        $this->assertTrue($isProcessing);

        // Release lock
        Dedupler::releaseUploadLock($quickHash);

        // Check again after release
        $isProcessingAfterRelease = Dedupler::isFileProcessing($quickHash);
        $this->assertFalse($isProcessingAfterRelease);
    }*/
}
