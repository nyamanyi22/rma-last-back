<?php
// diagnostic.php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $class = 'App\Http\Controllers\Api\Admin\RMAController';
    if (class_exists($class)) {
        $reflector = new ReflectionClass($class);
        echo "Class $class is defined in: " . $reflector->getFileName() . PHP_EOL;
    }
    else {
        echo "Class $class is NOT defined." . PHP_EOL;
    }
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
