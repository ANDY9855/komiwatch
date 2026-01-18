<?php
$host = "localhost";
$user = "root";   // XAMPP default
$pass = "";       // leave empty in XAMPP unless you set a password
$dbname = "watchparty";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
} else {
    echo "✅ Connected successfully to database: " . $dbname;
}
?>
