<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => '/api/unique-file-storage'], function () {
    Route::get('/',
        function (\Illuminate\Http\Request $request) {
            return response()
                ->json(['status' => 'ok'])
                ->setStatusCode(200)
                ->header('Content-Type', 'application/json');
        })
        ->name('api.unique-file-storage.index');
});
