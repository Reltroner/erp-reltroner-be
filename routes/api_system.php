<?php

use App\Http\Middleware\RuntimeProtectionMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Apply RuntimeProtectionMiddleware to protect health/runtime routes
Route::middleware([RuntimeProtectionMiddleware::class])->group(function () {

    Route::get('/system/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::get('/system/runtime', function () {
        return response()->json([
            'status' => 'healthy',
            'runtime' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'os' => PHP_OS,
                'memory_usage' => memory_get_usage(true),
            ],
        ]);
    });

    Route::get('/system/db-health', function () {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (Exception $e) {
            $dbStatus = 'error: '.$e->getMessage();
        }

        return response()->json([
            'status' => $dbStatus === 'connected' ? 'healthy' : 'unhealthy',
            'database' => [
                'connection' => config('database.default'),
                'status' => $dbStatus,
            ],
        ]);
    });
});
