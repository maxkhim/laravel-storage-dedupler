<?php

use Illuminate\Support\Facades\Route;
use Maxkhim\Dedupler\Http\Controllers\DeduplerController;

Route::group(['prefix' => '/api/dedupler'], function () {
    Route::get(
        '/',
        function (\Illuminate\Http\Request $request) {
            return response()
                ->json(['status' => 'ok'])
                ->setStatusCode(200)
                ->header('Content-Type', 'application/json');
        }
    )
        ->name('api.dedupler.index');

    Route::prefix('/v1/files')->name('unique-files.')->group(function () {
        // Загрузка одного файла
        Route::post('/', [DeduplerController::class, 'store'])
            ->name('store');

        // Загрузка нескольких файлов
        Route::post('/batch', [DeduplerController::class, 'storeMultiple'])
            ->name('store-multiple');

        // Получение информации о файле
        Route::get('/{hash}', [DeduplerController::class, 'show'])
            ->name('show')
            ->where('hash', '[a-fA-F0-9]{40}');

        // Скачивание файла
        Route::get('/{hash}/download', [DeduplerController::class, 'download'])
            ->name('download')
            ->where('hash', '[a-fA-F0-9]{40}');

        // Прямая отдача файла (для встраивания)
        Route::get('/{hash}/stream', [DeduplerController::class, 'stream'])
            ->name('stream')
            ->where('hash', '[a-fA-F0-9]{40}');
    });
});
