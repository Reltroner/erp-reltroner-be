<?php

use App\Http\Middleware\EnsureKeycloakBearerToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/public/runtime', function () {
    return response()->json([
        'status' => 'healthy',
        'deployment_id' => env('BE_DEPLOYMENT_ID', 'erp-reltroner-be-sha-local'),
        'contract_version' => env('CONTRACT_VERSION', '2026-07-ERP-P1'),
        'php_version' => PHP_VERSION,
        'environment' => app()->environment()
    ]);
});

Route::middleware([EnsureKeycloakBearerToken::class])->get('/auth/me', function (Request $request) {
    $userProfile = $request->attributes->get('user_profile');
    return response()->json([
        'id' => $userProfile->id,
        'email' => $userProfile->email,
        'display_name' => $userProfile->display_name,
        'status' => $userProfile->status,
        'is_admin' => $userProfile->isAdmin()
    ]);
});
