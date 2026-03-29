<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

// Simple migration runner
echo "Running migrations manually...\n";

try {
    // Load environment
    $app = require_once 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    // Run migrations
    Artisan::call('migrate', ['--force' => true]);

    echo "Migrations completed successfully!\n";
    echo Artisan::output();

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
