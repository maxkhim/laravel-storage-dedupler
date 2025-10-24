<?php

declare(strict_types=1);

namespace Maxkhim\Dedupler\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Maxkhim\Dedupler\Providers\DeduplerServiceProvider;
use Spatie\LaravelPackageTools\Commands\Concerns\AskToStarRepoOnGitHub;

class DeduplerInstallCommand extends Command
{
    /**
     * Command signature
     *
     * @var string
     */
    protected $signature = 'dedupler:install';

    /**
     * Command description
     *
     * @var string
     */

    protected $description = 'Dedupler package installation';

    /**
     * Исполнение
     *
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        $this->alert('Dedupler package installation');

        Artisan::call(
            "migrate",
            [
                "--path" => DeduplerServiceProvider::getMigrationPath(),
                "--realpath" => true,
                "--force" => true
            ]
        );
        $this->info(Artisan::output());


        $this->alert('Dedupler package check ready to use');
        Artisan::call("dedupler:check");
        $this->info(Artisan::output());
    }
}
