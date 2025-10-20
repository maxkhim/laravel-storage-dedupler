<?php

declare(strict_types=1);

namespace Maxkhim\Dedupler\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maxkhim\Dedupler\Models\Article;
use Maxkhim\Dedupler\Models\Deduplicatable;
use Maxkhim\Dedupler\Models\LegacyFileMigration;
use Maxkhim\Dedupler\Models\UniqueFile;

class CheckDeduplerStorageCommand extends Command
{
    /**
     * Имя команды
     *
     * @var string
     */
    protected $signature = 'dedupler:check';

    /**
     * Описание команды.
     *
     * @var string
     */

    protected $description = 'Check the correctness of the Dedupler package';

    /**
     * Исполнение
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        $this->alert('Check Dedupler State');

        $packageIsReady = true;
        try {
            DB::connection(config('dedupler.connection'))
                ->select('select 1');
            $this->info('✅ Database connected!');
        } catch (\Throwable $e) {
            $packageIsReady = false;
            $this->error("DB connection error: " . $e->getMessage());
        }


        try {
            $uniqueFiles = UniqueFile::query()->count();
            $this->info('✅ UniqueFile entity is ready!');

            $deduplicatable = Deduplicatable::query()->count();
            $this->info('✅ Deduplicatable pivot is ready!');

            $legacyFileMigration = LegacyFileMigration::query()->count();
            $this->info('✅ LegacyFileMigration entity is ready!');
        } catch (\Throwable $e) {
            $packageIsReady = false;
            $this->error("Tables error: " . $e->getMessage());
        }

        try {
            $deduplerStorage = Storage::disk(config('dedupler.default_disk'));
            $this->info('✅ Storage is configured!');
            Storage::disk(config('dedupler.default_disk'))->put("/test.txt", "demo");
            Storage::disk(config('dedupler.default_disk'))->delete("/test.txt");
            $this->info('✅ Storage writeable!');
        } catch (\Throwable $e) {
            $packageIsReady = false;
            $this->error("Storage error: " . $e->getMessage());
        }

        if ($packageIsReady) {
            $this->info('✅ It seems package is ready to use!');
        } else {
            $this->error('It seems package is NOT ready to use');
        }

        exit();
        /*
                for ($id = 1; $id <= 1000; $id++) {
                    $article = Article::query()
                        ->find($id);

                    $fileName = $article->title;

                    for ($i = 1; $i <= 10; $i++) {
                        $content = (string)round(rand(1, 5));
                        $fileRelation = $article->storeContentFile($content, $fileName . "-" . $i . '.txt', [
                            'mime_type' => 'text/plain'
                        ]);
                    }
                    // 4. Из сырого контента

        */


        //$this->alert($fileRelation->file->size); // Размер файла
        //$this->info($fileRelation->original_name);  // Оригинальное имя для этой связи
        //$this->info($fileRelation->status); // Статус для этой связи
        // Загрузка с дополнительными параметрами
        /*$fileRelation = $article->storeUniqueFile($file, [
            'disk' => 's3',
            'pivot' => [
                'status' => 'processing',
                'original_name' => 'profile_photo.jpg'
            ]
        ]);*/
        /*  unset($article, $fileName);
        }


        $this->line("");*/
    }
}
