<?php

declare(strict_types=1);

namespace Maxkhim\Dedupler\Commands;

use Exception;
use Illuminate\Console\Command;

class DeduplerInitCommand extends Command
{
    /**
     * Command signature
     *
     * @var string
     */
    protected $signature = 'dedupler:init';

    /**
     * Command description
     *
     * @var string
     */

    protected $description = 'Dedupler package initialization';

    /**
     * Исполнение
     *
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        $this->alert('Dedupler package initialization');
    }
}
