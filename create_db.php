<?php
$host = '127.0.0.1';
$user = 'postgres';
$pass = 'liz';
$dbname = 'rma-db';

$conn = pg_connect("host=$host user=$user password=$pass");

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

$sql = "CREATE DATABASE \"$dbname\"";
$result = pg_query($conn, $sql);

if ($result) {
    echo "Database created successfully\n";
} else {
    echo "Error creating database: " . pg_last_error() . "\n";
}

pg_close($conn);
?>
