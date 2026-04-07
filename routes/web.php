<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return 'Hello World';
});

Route::get('/', function () {
    return response()->json([
        'message' => Setting::portalName() . ' API',
        'version' => '1.0.0',
        'status' => 'running',
        'endpoints' => [
            'api' => '/api',
            'docs' => '/api/documentation (if available)',
        ]
    ]);
});
