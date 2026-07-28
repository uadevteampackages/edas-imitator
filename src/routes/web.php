<?php

use Illuminate\Support\Facades\Route;
use Uadevteampackages\Imitator\Http\Controllers\ImitatorController;

Route::middleware(['web', 'auth', 'imitator.local'])
    ->prefix('imitator')
    ->name('imitator.')
    ->group(function (): void {
        Route::get('/', [ImitatorController::class, 'index'])->name('index');
        Route::get('/search', [ImitatorController::class, 'search'])->name('search');
        Route::post('/', [ImitatorController::class, 'store'])->name('store');
        Route::delete('/', [ImitatorController::class, 'destroy'])->name('destroy');
    });
