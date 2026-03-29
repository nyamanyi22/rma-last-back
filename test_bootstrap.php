<?php

require 'vendor/autoload.php';

try {
    $app = require_once 'bootstrap/app.php';
    echo 'Application bootstrapped successfully' . PHP_EOL;
} catch (Exception $e) {
    echo 'Bootstrap error: ' . $e->getMessage() . PHP_EOL;
    echo 'File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    echo 'Stack trace:' . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
