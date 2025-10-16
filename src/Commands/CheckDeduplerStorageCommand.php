<?php

declare(strict_types=1);

namespace Maxkhim\Dedupler\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maxkhim\Dedupler\Models\Article;

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

    protected $description = 'Проверка корректности работы модуля Dedupler  / Check the correctness of the package';

    /**
     * Исполнение
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        $this->alert('Проверка / Check UniqueFileStorage');


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
            unset($article, $fileName);
        }


        $this->line("");
    }
}
