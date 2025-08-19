<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/run-migrations', function () {
    Artisan::call('migrate --force');

    return 'Migrations executed successfully.';
});

Route::get('/rollback-migrations', function () {
    Artisan::call('migrate:rollback --step=1');

    return 'Migrations rolled back successfully.';
});
