<?php
$host = '127.0.0.1';      // localhost IP — correct for phpMyAdmin
$user = 'root';           // default local user normally
$pass = '';               // empty password for local server
$dbname = 'watchparty';   // your actual database name

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
?>
