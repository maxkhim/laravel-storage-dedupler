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
use Maxkhim\Dedupler\Providers\DeduplerServiceProvider;

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
            $version = DB::connection(config('dedupler.db_connection'))
                ->select('select version() as vrs;');
            $this->info('✅ Database connected: ' . $version[0]->vrs . '!');
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
            $repoUrl = "https://github.com/" .
                DeduplerServiceProvider::$vendor .
                "/" . DeduplerServiceProvider::$name;
            $this->alert('Please, star our repo on GitHub! : ' . $repoUrl);
        } else {
            $this->error('It seems package is NOT ready to use');
        }
    }
}
