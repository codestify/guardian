<?php

\Illuminate\Support\Facades\Route::get('/guardian', function () {
    return 'i am guardian';
})->name('guardian.welcome');
