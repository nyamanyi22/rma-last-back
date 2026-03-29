<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

try {
    Mail::raw('Test from Antigravity Script', function($m) {
        $m->to('test@example.com')->subject('Manual Test Script');
    });
    echo "Mail command sent successfully\n";
} catch (\Exception $e) {
    echo "Mail Error: " . $e->getMessage() . "\n";
    Log::error("Manual Mail Test Error: " . $e->getMessage());
}
