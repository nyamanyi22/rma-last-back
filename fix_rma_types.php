<?php

use App\Models\RMARequest;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::transaction(function () {
    $count = RMARequest::where('rma_type', 'return')->update(['rma_type' => 'simple_return']);
    echo "Updated $count 'return' to 'simple_return'\n";

    $count = RMARequest::where('rma_type', 'warranty')->update(['rma_type' => 'warranty_repair']);
    echo "Updated $count 'warranty' to 'warranty_repair'\n";

    $count = RMARequest::where('rma_type', 'repair')->update(['rma_type' => 'warranty_repair']);
    echo "Updated $count 'repair' to 'warranty_repair'\n";
});
