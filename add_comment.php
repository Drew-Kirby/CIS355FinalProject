<?php
session_start();
require 'database/database.php';

header('Content-Type: application/json');

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required to comment.']);
    exit();
}

// Check Request Method and Required Data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['issue_id']) || !isset($_POST['comment'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid comment submission data.']);
    exit();
}

// Sanitize and Validate Input
$issueId = filter_input(INPUT_POST, 'issue_id', FILTER_VALIDATE_INT);
$commentText = trim($_POST['comment']);
$userId = $_SESSION['user_id']; // Get user ID from session

if ($issueId === false || $issueId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Issue ID for comment.']);
    exit();
}
if (empty($commentText)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Comment cannot be empty.']);
    exit();
}

try {
    // Verify the issue exists and is not closed
    $stmtCheck = $pdo->prepare("SELECT id FROM issues WHERE id = :issue_id AND date_closed IS NULL");
    $stmtCheck->execute(['issue_id' => $issueId]);
    if ($stmtCheck->fetchColumn() === false) {
         http_response_code(400); // Use 400 as it's a client error (trying to comment on invalid issue)
         echo json_encode(['status' => 'error', 'message' => 'Cannot add comment: Issue not found or is closed.']);
         exit();
    }

    // Insert the comment
    $stmt = $pdo->prepare('INSERT INTO comments (issue_id, user_id, comment, date_posted) VALUES (:issue_id, :user_id, :comment, NOW())');
    $stmt->execute([
        'issue_id' => $issueId,
        'user_id' => $userId,
        'comment' => $commentText
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Comment added successfully.'
        ]);
    } else {
         http_response_code(500);
         echo json_encode(['status' => 'error', 'message' => 'Failed to save comment to the database.']);
    }

} catch (PDOException $e) {
    error_log("Add comment failed (Issue ID: $issueId, User ID: $userId): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred while adding comment.']);
}
?>
