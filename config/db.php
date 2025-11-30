<?php
// connects to postgres database

// database credentials
$host = 'localhost';
$dbname = 'Event_Management';
$username = 'postgres';
$password = ''; // no password needed for local setup

try {
    // create connection using PDO
    $conn = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    // set error mode so we can see whats wrong if something breaks
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // if connection fails show error and stop
    die("Connection failed: " . $e->getMessage());
}
?>
