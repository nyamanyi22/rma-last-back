<?php
$host = '127.0.0.1';
$user = 'postgres';
$pass = 'liz';
$dbname = 'rma-db';

echo "Testing PostgreSQL connection...\n";

$conn = pg_connect("host=$host user=$user password=$pass dbname=$dbname");

if (!$conn) {
    echo "Connection failed: " . pg_last_error() . "\n";
    echo "Please update the password in this file and .env\n";
} else {
    echo "Connection successful!\n";
    echo "PostgreSQL version: " . pg_version($conn)['server'] . "\n";
    pg_close($conn);
}
?>
