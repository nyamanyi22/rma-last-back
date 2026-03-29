<?php
$f = DB::table('failed_jobs')->latest('failed_at')->first();
if ($f) {
    file_put_contents('failed_exception.txt', $f->exception);
    echo "Exception written to failed_exception.txt\n";
} else {
    echo "No failed jobs found.\n";
}
