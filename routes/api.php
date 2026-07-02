<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__.'/api_public.php';
    require __DIR__.'/api_erp.php';
    require __DIR__.'/api_admin.php';
    require __DIR__.'/api_system.php';
});
