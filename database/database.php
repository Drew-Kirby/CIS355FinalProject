<?php
// Start the session
session_start();

// Database credentials
$host = 'localhost';
$dbname = 'issues_db';
$username = 'issuesadmin';
$password = 'CIS355FinalProject';

try {
    // Create PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}
?>
