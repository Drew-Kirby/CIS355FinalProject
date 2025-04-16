<?php
session_start();
require 'database/database.php'; // Use centralized DB connection

// --- Authentication check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// --- Determine User Role ---
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$userId = $_SESSION['user_id']; // Get current user's ID

// --- Get and validate the Issue ID from the URL ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = 'Invalid Issue ID specified.';
    header('Location: index.php'); // Redirect back if ID is invalid
    exit();
}
$issueId = (int)$_GET['id'];


// --- Initialize message variables ---
// Note: Using session flash messages is generally better after redirects
// $comment_error = null; // Local variables might not persist across redirect
// $comment_success = null;
// $update_error = null;
// $update_success = null;

// --- Handle POST Requests (Update Issue OR Add Comment) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Handle Issue Update - ONLY ADMINS ---
    if (isset($_POST['update_issue']) && $isAdmin) { // <-- Added $isAdmin check here
        $submittedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $issueTitle = trim($_POST['issue_title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? '';
        $validPriorities = ['High', 'Medium', 'Low'];

        // Validate inputs
        if ($submittedId === $issueId && !empty($issueTitle) && in_array($priority, $validPriorities)) {
            try {
                // Prepare and execute update only if the issue is currently open
                $stmt_update = $pdo->prepare("
                    UPDATE issues
                    SET issue = :issue, description = :description, priority = :priority
                    WHERE id = :id AND date_closed IS NULL
                ");
                $stmt_update->execute([
                    'issue'       => $issueTitle,
                    'description' => $description,
                    'priority'    => $priority,
                    'id'          => $issueId
                ]);

                if ($stmt_update->rowCount() > 0) {
                    $_SESSION['success_message'] = "Issue updated successfully!";
                } else {
                    // Check if it failed because it was closed or no change
                    $stmt_check_closed = $pdo->prepare("SELECT date_closed FROM issues WHERE id = :id");
                    $stmt_check_closed->execute(['id' => $issueId]);
                    $current_issue_status = $stmt_check_closed->fetch();
                    if ($current_issue_status && $current_issue_status['date_closed']) {
                         $_SESSION['error_message'] = "Cannot update a closed issue.";
                    } else {
                         $_SESSION['info_message'] = "No changes were detected or needed for the update.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Error updating issue (ID: $issueId): " . $e->getMessage());
                $_SESSION['error_message'] = "Failed to update issue due to a database error.";
            }
        } else {
            // Validation failed
            if (empty($issueTitle)) $_SESSION['error_message'] = "Issue Title cannot be empty.";
            elseif (!in_array($priority, $validPriorities)) $_SESSION['error_message'] = "Invalid Priority selected.";
            else $_SESSION['error_message'] = "Invalid data submitted for update.";
        }
        // Redirect back to the same page to show messages and prevent resubmit
        header("Location: view_issue.php?id=" . $issueId);
        exit();

    // --- Handle Comment Submission - ALL Logged-in Users ---
    } elseif (isset($_POST['add_comment'])) {
        // Non-admin tried to update? Add an error message.
        if (isset($_POST['update_issue']) && !$isAdmin) {
             $_SESSION['error_message'] = "Access Denied: Only administrators can update issues.";
        } else {
            // Proceed with adding comment
            $commentText = trim($_POST['comment_text']);

            // Fetch current issue status *again* right before insert
            $stmt_check_closed_comment = $pdo->prepare("SELECT date_closed FROM issues WHERE id = :id");
            $stmt_check_closed_comment->execute(['id' => $issueId]);
            $issue_status_for_comment = $stmt_check_closed_comment->fetch();

            if ($issue_status_for_comment && $issue_status_for_comment['date_closed'] === null) { // Check if open
                if (!empty($commentText)) {
                    try {
                        $stmt_add = $pdo->prepare("INSERT INTO comments (issue_id, user_id, comment, date_posted) VALUES (:issue_id, :user_id, :comment, NOW())");
                        $stmt_add->execute([
                            'issue_id' => $issueId,
                            'user_id' => $userId, // Use the logged-in user's ID
                            'comment' => $commentText
                        ]);
                        $_SESSION['success_message'] = "Comment added successfully!";
                    } catch (PDOException $e) {
                         error_log("Error adding comment via view_issue.php (Issue ID: $issueId): " . $e->getMessage());
                         $_SESSION['error_message'] = "Failed to add comment due to a database error.";
                    }
                } else {
                     $_SESSION['error_message'] = "Comment cannot be empty.";
                }
            } else {
                $_SESSION['error_message'] = "Cannot comment on a closed or non-existent issue.";
            }
        }
        // Redirect back to the same page
        header("Location: view_issue.php?id=" . $issueId);
        exit();
    }
} // End POST handling

// --- Fetch Data (after potential POST redirects) ---
try {
    // Fetch Issue Details
    $stmt_issue = $pdo->prepare("SELECT * FROM issues WHERE id = :id");
    $stmt_issue->execute(['id' => $issueId]);
    $issue = $stmt_issue->fetch();

    if (!$issue) {
        $_SESSION['error_message'] = 'Issue not found.';
        header('Location: index.php');
        exit();
    }
    $isClosed = !empty($issue['date_closed']); // Determine status

    // Fetch Comments for this Issue
    $stmt_comments = $pdo->prepare("
        SELECT c.comment, c.date_posted, u.first_name, u.last_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.issue_id = :issueId
        ORDER BY c.date_posted ASC
    ");
    $stmt_comments->execute(['issueId' => $issueId]);
    $comments = $stmt_comments->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching issue/comments for view_issue.php (ID: $issueId): " . $e->getMessage());
    $fetchError = 'Error retrieving issue data. Please try again later.';
    $issue = null;
    $comments = [];
}

// --- Get Flash Messages from Session (after potential redirects) ---
$errorMessage = $_SESSION['error_message'] ?? null;
$successMessage = $_SESSION['success_message'] ?? null;
$infoMessage = $_SESSION['info_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message'], $_SESSION['info_message']); // Clear messages

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Issue #<?php echo htmlspecialchars($issueId); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
     <style>
         .comment-item { margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; }
         .comment-meta { font-size: 0.9em; color: #6c757d; }
         .card-header-tabs .nav-link.active { background-color: #f8f9fa; border-color: #dee2e6 #dee2e6 #f8f9fa; }
     </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <a href="index.php" class="btn btn-secondary mb-3">« Back to Issues List</a>

    <!-- Display Fetch Error if Occurred -->
    <?php if (isset($fetchError)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($fetchError); ?></div>
    <?php // Stop rendering the rest if fetch failed and $issue is not set
          elseif ($issue): // Make sure $issue is available
    ?>

    <!-- Display Flash Messages -->
    <?php if ($errorMessage): ?> <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($errorMessage); ?><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div> <?php endif; ?>
    <?php if ($successMessage): ?> <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($successMessage); ?><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div> <?php endif; ?>
    <?php if ($infoMessage): ?> <div class="alert alert-info alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($infoMessage); ?><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button></div> <?php endif; ?>


    <h1>Issue #<?php echo htmlspecialchars($issue['id']); ?></h1>

    <div class="card mb-4">
         <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="issueTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="details-tab" data-toggle="tab" href="#details" role="tab" aria-controls="details" aria-selected="true">Details</a>
                </li>
                <!-- Only show Edit tab link to Admins if the issue is open -->
                <?php if ($isAdmin && !$isClosed): ?>
                <li class="nav-item">
                    <a class="nav-link" id="edit-tab" data-toggle="tab" href="#edit" role="tab" aria-controls="edit" aria-selected="false">Edit Issue</a>
                </li>
                <?php endif; ?>
            </ul>
         </div>
         <div class="card-body tab-content" id="issueTabContent">
             <!-- Details Tab -->
             <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
                 <h5 class="card-title"><?php echo htmlspecialchars($issue['issue']); ?></h5>
                 <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($issue['description'] ?: 'N/A')); ?></p>
                 <hr>
                 <p><strong>Priority:</strong> <span class="badge <?php
                    switch ($issue['priority']) {
                        case 'High': echo 'badge-danger'; break;
                        case 'Medium': echo 'badge-warning'; break;
                        case 'Low': echo 'badge-info'; break;
                        default: echo 'badge-secondary';
                    }
                 ?>"><?php echo htmlspecialchars($issue['priority']); ?></span></p>
                 <p><strong>Opened:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($issue['date_opened']))); ?></p>
                 <p><strong>Status:</strong> <?php echo $isClosed ? '<span class="badge badge-secondary">Closed</span> on ' . htmlspecialchars(date('Y-m-d H:i', strtotime($issue['date_closed']))) : '<span class="badge badge-success">Open</span>'; ?></p>
             </div>

             <!-- Edit Tab Content - Only render for Admins if the issue is open -->
             <?php if ($isAdmin && !$isClosed): ?>
             <div class="tab-pane fade" id="edit" role="tabpanel" aria-labelledby="edit-tab">
                 <h5 class="card-title">Edit Issue Details</h5>
                 <form action="view_issue.php?id=<?php echo $issueId; ?>" method="POST">
                     <input type="hidden" name="id" value="<?php echo $issue['id']; ?>">
                     <div class="form-group">
                         <label for="editIssueTitle">Issue Title</label>
                         <input type="text" id="editIssueTitle" name="issue_title" class="form-control" value="<?php echo htmlspecialchars($issue['issue']); ?>" required>
                     </div>
                     <div class="form-group">
                         <label for="editDescription">Description</label>
                         <textarea id="editDescription" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($issue['description']); ?></textarea>
                     </div>
                     <div class="form-group">
                         <label for="editPriority">Priority</label>
                         <select id="editPriority" name="priority" class="form-control" required>
                             <option value="High" <?php if ($issue['priority'] === 'High') echo 'selected'; ?>>High</option>
                             <option value="Medium" <?php if ($issue['priority'] === 'Medium') echo 'selected'; ?>>Medium</option>
                             <option value="Low" <?php if ($issue['priority'] === 'Low') echo 'selected'; ?>>Low</option>
                         </select>
                     </div>
                     <button type="submit" name="update_issue" class="btn btn-primary">Save Changes</button>
                 </form>
             </div>
             <?php endif; ?>

         </div>
    </div>


    <!-- Comments Section -->
    <div class="card">
         <div class="card-header">
             <h5 class="mb-0">Comments</h5>
         </div>
         <div class="card-body">
            <!-- Display Existing Comments -->
            <div class="mb-4" id="commentsArea">
                <?php if (empty($comments)): ?>
                    <p class="text-muted">No comments have been posted yet.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment-item">
                            <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                            <p class="comment-meta mb-0">
                                Posted by <strong><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?></strong>
                                on <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($comment['date_posted']))); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Add Comment Form (show to ALL logged-in users if issue is open) -->
            <?php if (!$isClosed): ?>
            <hr>
            <h6>Add a New Comment</h6>
             <!-- Removed local comment status variables - rely on session flash messages shown at top -->

             <form action="view_issue.php?id=<?php echo $issueId; ?>" method="POST" class="mt-2">
                 <div class="form-group">
                     <textarea name="comment_text" class="form-control" rows="3" placeholder="Enter your comment..." required></textarea>
                 </div>
                 <button type="submit" name="add_comment" class="btn btn-success">Post Comment</button>
             </form>
            <?php else: ?>
                 <p class="text-muted font-italic mt-3">Commenting is disabled because this issue is closed.</p>
            <?php endif; ?>

         </div>
    </div>

    <?php endif; // End the else for if($fetchError)/if($issue) ?>

</div><!-- /.container -->

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    // Activate Bootstrap tabs
    $(function () {
        $('#issueTab a').on('click', function (e) {
            e.preventDefault()
            $(this).tab('show')
        })

        // Dismiss alerts automatically after a few seconds (optional)
        window.setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove();
            });
        }, 5000); // 5 seconds
    })
</script>
</body>
</html>