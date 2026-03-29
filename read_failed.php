<?php
$f = DB::table('failed_jobs')->latest()->first();
if ($f) {
    echo "ID: " . $f->id . "\n";
    echo "Connection: " . $f->connection . "\n";
    echo "Queue: " . $f->queue . "\n";
    echo "Payload: " . substr($f->payload, 0, 100) . "...\n";
    echo "Exception: " . $f->exception . "\n";
} else {
    echo "No failed jobs found.\n";
}
