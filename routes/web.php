<?php

use Illuminate\Support\Facades\Route;


Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::group(['prefix' => '/unique-file-storage'], function () {
        return response()->json(['status' => 'ok']);
    });
});
