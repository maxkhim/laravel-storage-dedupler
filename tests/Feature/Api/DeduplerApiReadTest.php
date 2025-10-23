<?php

namespace Tests\Feature\Api;

use Maxkhim\Dedupler\Facades\Dedupler;
use Maxkhim\Dedupler\Models\Article;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maxkhim\Dedupler\Models\UniqueFile;

class DeduplerApiReadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @testdox Возвращает по api информацию о файле
     * @test
     */
    public function itReturnsFileInfoViaApi()
    {
        $article = Article::create();

        // First upload a file
        /*$uploadResponse = $this->postJson('/api/v1/files', [
            'file' => UploadedFile::fake()->image('test.jpg'),
            'model_type' => get_class($article),
            'model_id' => $article->id,
        ]);*/
        $file = UploadedFile::fake()->image('fake_uploaded_file.jpg', 100, 100);
        $hash = sha1($file->getContent());
        UniqueFile::query()->find($hash)?->delete();

        $fileRelation = Dedupler::storeFromUploadedFile($file, $article, [
            'pivot' => ['status' => 'completed']
        ]);

        // Then get file info
        $response = $this->getJson("/api/dedupler/v1/files/{$hash}");


        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'hash',
                    'exists',
                    'filename',
                    'mime_type',
                    'size',
                    'size_human',
                    'disk',
                    'status',
                    'links_count'
                ]
            ]);
    }

    /**
     * @testdox Возвращает корректный ответ в случае несущестовавания файла
     * @test
     */
    public function itReturns404ForNonexistentFile()
    {
        $response = $this->getJson('/api/dedupler/v1/files/' . sha1(rand(11111, 99999)));
        $response->assertStatus(404)
            ->assertJsonStructure(['error']);
    }
}
