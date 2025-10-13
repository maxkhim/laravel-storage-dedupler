<?php

namespace Maxkhim\UniqueFileStorage\Facades;

use Illuminate\Support\Facades\Facade;

class UniquieFileStorage extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'unique-file-storage';
    }
}