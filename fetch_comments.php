<?php
session_start(); // Start session
require 'database/database.php'; // Include DB connection

header('Content-Type: application/json'); // Set correct header

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit();
}

// Input Check
if (!isset($_GET['issue_id']) || !filter_var($_GET['issue_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid Issue ID is required.']);
    exit();
}

$issueId = (int)$_GET['issue_id'];

try {
    // Select comment data and the user's name who posted it
    $stmt = $pdo->prepare('SELECT c.comment, c.date_posted, u.first_name, u.last_name
                           FROM comments c
                           JOIN users u ON c.user_id = u.id
                           WHERE c.issue_id = :issueId
                           ORDER BY c.date_posted ASC'); // Show oldest comments first
    $stmt->execute(['issueId' => $issueId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($comments); // Return comments array as JSON

} catch (PDOException $e) {
    error_log("Fetch comments failed (Issue ID: $issueId): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred while fetching comments.']);
}
?>
