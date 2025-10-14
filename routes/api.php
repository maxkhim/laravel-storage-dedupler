<?php

use Illuminate\Support\Facades\Route;
use Maxkhim\UniqueFileStorage\Http\Controllers\UniqueFileStorageController;

Route::group(['prefix' => '/api/unique-file-storage'], function () {
    Route::get(
        '/',
        function (\Illuminate\Http\Request $request) {
            return response()
                ->json(['status' => 'ok'])
                ->setStatusCode(200)
                ->header('Content-Type', 'application/json');
        }
    )
        ->name('api.unique-file-storage.index');

    Route::prefix('/v1/files')->name('unique-files.')->group(function () {
        // Загрузка одного файла
        Route::post('/', [UniqueFileStorageController::class, 'store'])
            ->name('store');

        // Загрузка нескольких файлов
        Route::post('/batch', [UniqueFileStorageController::class, 'storeMultiple'])
            ->name('store-multiple');

        // Получение информации о файле
        Route::get('/{hash}', [UniqueFileStorageController::class, 'show'])
            ->name('show')
            ->where('hash', '[a-fA-F0-9]{40}');

        // Скачивание файла
        Route::get('/{hash}/download', [UniqueFileStorageController::class, 'download'])
            ->name('download')
            ->where('hash', '[a-fA-F0-9]{40}');

        // Прямая отдача файла (для встраивания)
        Route::get('/{hash}/stream', [UniqueFileStorageController::class, 'stream'])
            ->name('stream')
            ->where('hash', '[a-fA-F0-9]{40}');
    });
});
