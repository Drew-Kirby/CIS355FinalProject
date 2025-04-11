<?php
session_start(); // Start session
require 'database/database.php'; // Include DB connection

header('Content-Type: application/json'); // Set correct header for JSON response

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit();
}

// Input Check
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
     http_response_code(400); // Bad Request
     echo json_encode(['status' => 'error', 'message' => 'Valid Issue ID is required.']);
     exit();
}

$issueId = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare('SELECT id, issue, description, priority, date_opened, date_closed FROM issues WHERE id = :issueId');
    $stmt->execute(['issueId' => $issueId]);
    $issue = $stmt->fetch(PDO::FETCH_ASSOC); // Already fetches assoc due to db config

    if ($issue) {
        // Format dates if needed, though JS can also handle it
        // $issue['date_opened_formatted'] = date('Y-m-d H:i', strtotime($issue['date_opened']));
        // $issue['date_closed_formatted'] = $issue['date_closed'] ? date('Y-m-d H:i', strtotime($issue['date_closed'])) : null;
        echo json_encode($issue); // Return the issue data as JSON
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'error', 'message' => 'Issue not found.']);
    }

} catch (PDOException $e) {
    error_log("Fetch issue details failed (ID: $issueId): " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred while fetching issue details.']);
}
?>
