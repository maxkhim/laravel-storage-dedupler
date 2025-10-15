<?php

use Illuminate\Support\Facades\Route;


Route::middleware(['web', 'auth', 'verified'])->group(function () {
    Route::group(['prefix' => '/dedupler'], function () {
        return response()->json(['status' => 'ok']);
    });
});
