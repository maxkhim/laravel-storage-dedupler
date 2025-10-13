<?php

declare(strict_types=1);

namespace Maxkhim\UniqueFileStorage\Commands;

use Exception;
use Illuminate\Console\Command;

class CheckUniqueFileStorageCommand extends Command
{
    /**
     * Имя команды
     *
     * @var string
     */
    protected $signature = 'unique-file-storage:check';

    /**
     * Описание команды.
     *
     * @var string
     */

    protected $description = 'Проверка корректности работы модуля UniqueFileStorage. Check the correctness of the package';

    /**
     * Исполнение
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        $this->alert('Проверка / Check UniqueFileStorage');

        $this->line("");
    }
}
