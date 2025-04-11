<?php
session_start(); // Start session at the very beginning
require 'database/database.php'; // Include centralized database connection ($pdo is available now)

// Redirect to login if user is not logged in at all
if (!isset($_SESSION['user_id'])) {
    // Determine if it should be admin or user login based on where they might have come from,
    // but defaulting to user login is safest if unsure.
    header('Location: login.php');
    exit();
}

// Determine if the current user is an admin based on the session role
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Error reporting (useful for development, disable or log in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Handle Non-AJAX POST requests: Add, Close, Delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Add New Issue (from the main page form) - ONLY ADMINS
    if (isset($_POST['issue']) && !isset($_POST['closeIssue']) && !isset($_POST['deleteIssue'])) {
        if ($isAdmin) { // <-- Check if user is admin
            $issueTitle = trim($_POST['issue']);
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $priority = $_POST['priority'];
            $dateOpened = date('Y-m-d H:i:s');

            if (!empty($issueTitle) && in_array($priority, ['High', 'Medium', 'Low'])) {
                try {
                    $stmt = $pdo->prepare('INSERT INTO issues (issue, description, priority, date_opened) VALUES (:issue, :description, :priority, :dateOpened)');
                    $stmt->execute(['issue' => $issueTitle, 'description' => $description, 'priority' => $priority, 'dateOpened' => $dateOpened]);
                    $_SESSION['success_message'] = 'Issue added successfully.';
                } catch (PDOException $e) {
                    error_log("Error adding issue: " . $e->getMessage());
                    $_SESSION['error_message'] = "Error adding issue. Please try again.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid data submitted for new issue.";
            }
        } else {
            // Non-admin tried to add issue
            $_SESSION['error_message'] = "Access Denied: Only administrators can add issues.";
        }
        header('Location: index.php');
        exit();
    }

    // Close Issue - ONLY ADMINS
    if (isset($_POST['closeIssue']) && isset($_POST['id'])) {
        if ($isAdmin) { // <-- Check if user is admin
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $dateClosed = date('Y-m-d H:i:s');
                try {
                    $stmt = $pdo->prepare('UPDATE issues SET date_closed = :dateClosed WHERE id = :id AND date_closed IS NULL');
                    $stmt->execute(['id' => $id, 'dateClosed' => $dateClosed]);
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['success_message'] = 'Issue closed successfully.';
                    } else {
                        $_SESSION['error_message'] = 'Issue could not be closed (maybe already closed or not found).';
                    }
                } catch (PDOException $e) {
                    error_log("Error closing issue: " . $e->getMessage());
                    $_SESSION['error_message'] = "Error closing issue. Please try again.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid ID for closing issue.";
            }
        } else {
            // Non-admin tried to close issue
            $_SESSION['error_message'] = "Access Denied: Only administrators can close issues.";
        }
        header('Location: index.php');
        exit();
    }

    // Delete Issue - ONLY ADMINS
    if (isset($_POST['deleteIssue']) && isset($_POST['id'])) {
         if ($isAdmin) { // <-- Check if user is admin
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                try {
                    $pdo->beginTransaction();
                    $stmtComments = $pdo->prepare('DELETE FROM comments WHERE issue_id = :id');
                    $stmtComments->execute(['id' => $id]);
                    $stmt = $pdo->prepare('DELETE FROM issues WHERE id = :id');
                    $stmt->execute(['id' => $id]);
                    $pdo->commit();
                    $_SESSION['success_message'] = 'Issue and associated comments deleted successfully.';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Error deleting issue: " . $e->getMessage());
                    $_SESSION['error_message'] = "Error deleting issue. Please try again.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid ID for deleting issue.";
            }
        } else {
             // Non-admin tried to delete issue
             $_SESSION['error_message'] = "Access Denied: Only administrators can delete issues.";
        }
        header('Location: index.php');
        exit();
    }
} // End POST handling

// --- Fetch issues for display (all users see the list) ---
try {
    $stmt = $pdo->query('SELECT * FROM issues ORDER BY date_closed IS NULL DESC, FIELD(priority, "High", "Medium", "Low"), date_opened DESC');
    $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching issues: " . $e->getMessage());
    $issues = [];
    $fetchError = "Could not retrieve issues from the database.";
}

// --- Display Session Flash Messages ---
$errorMessage = $_SESSION['error_message'] ?? null;
$successMessage = $_SESSION['success_message'] ?? null;
$infoMessage = $_SESSION['info_message'] ?? null; // Added for consistency
unset($_SESSION['error_message'], $_SESSION['success_message'], $_SESSION['info_message']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Display Admin status in title -->
    <title>Issue Tracker <?php echo $isAdmin ? '- Admin' : ''; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .table td .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
         tr.issue-closed td {
             background-color: #f8f9fa !important;
             color: #6c757d;
             text-decoration: line-through;
         }
    </style>
</head>
<body>
<div class="container mt-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <!-- Display Admin badge in heading -->
        <h1>Issue Tracker <?php if ($isAdmin) echo '<span class="badge badge-primary align-middle">Admin</span>'; ?></h1>
        <div>
            <!-- Show Manage Users link only to Admins -->
            <?php if ($isAdmin): ?>
                <a href="personlist.php" class="btn btn-info mr-2">Manage Users</a>
            <?php endif; ?>
            <form action="logout.php" method="POST" style="display:inline;">
                <button type="submit" class="btn btn-danger">Logout</button>
            </form>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($errorMessage); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($successMessage); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
    <?php endif; ?>
     <?php if ($infoMessage): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($infoMessage); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>
    <?php endif; ?>
    <?php if (isset($fetchError)): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($fetchError); ?></div>
    <?php endif; ?>

    <!-- Issue Creation Form - Show ONLY to Admins -->
    <?php if ($isAdmin): ?>
        <form action="" method="POST" class="border p-3 mb-4 bg-light rounded shadow-sm">
            <h5 class="mb-3">Add New Issue</h5>
            <div class="form-row align-items-end">
                <div class="col-md-4 mb-2">
                    <label for="newIssueTitle" class="sr-only">Issue Title</label>
                    <input type="text" id="newIssueTitle" name="issue" class="form-control" placeholder="Issue Title" required>
                </div>
                <div class="col-md-4 mb-2">
                    <label for="newIssueDesc" class="sr-only">Description</label>
                    <input type="text" id="newIssueDesc" name="description" class="form-control" placeholder="Description (Optional)">
                </div>
                <div class="col-md-2 mb-2">
                    <label for="newIssuePriority" class="sr-only">Priority</label>
                    <select id="newIssuePriority" name="priority" class="form-control" required>
                        <option value="" disabled selected>Priority</option>
                        <option value="High">High</option>
                        <option value="Medium">Medium</option>
                        <option value="Low">Low</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <button type="submit" class="btn btn-primary btn-block">Add Issue</button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <!-- Issues Table -->
    <div class="table-responsive shadow-sm">
        <table class="table table-bordered table-hover table-striped">
            <thead class="thead-dark">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Issue</th>
                    <th scope="col">Description</th>
                    <th scope="col">Priority</th>
                    <th scope="col">Opened</th>
                    <th scope="col">Closed</th>
                    <!-- Show Actions column header ONLY for Admins -->
                    <?php if ($isAdmin): ?>
                        <th scope="col">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($issues) && !isset($fetchError)): ?>
                     <tr><td colspan="<?php echo $isAdmin ? '7' : '6'; // Adjust colspan based on role ?>" class="text-center text-muted">No issues found.<?php echo $isAdmin ? ' Add one above!' : ''; ?></td></tr>
                <?php endif; ?>
                <?php foreach ($issues as $issue):
                    $isClosed = !empty($issue['date_closed']);
                    $rowClass = $isClosed ? 'issue-closed' : '';
                ?>
                    <tr id="issue-row-<?php echo $issue['id']; ?>" class="<?php echo $rowClass; ?>">
                        <td><?php echo htmlspecialchars($issue['id']); ?></td>
                        <td class="truncate issue-title" title="<?php echo htmlspecialchars($issue['issue']); ?>"><?php echo htmlspecialchars($issue['issue']); ?></td>
                        <td class="truncate issue-description" title="<?php echo htmlspecialchars($issue['description']); ?>"><?php echo htmlspecialchars($issue['description'] ?: '-'); ?></td>
                        <td class="issue-priority"><?php echo htmlspecialchars($issue['priority']); ?></td>
                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($issue['date_opened']))); ?></td>
                        <td class="issue-closed-date"><?php echo $isClosed ? htmlspecialchars(date('Y-m-d', strtotime($issue['date_closed']))) : '<span class="badge badge-success">Open</span>'; ?></td>

                        <!-- Show Actions column data ONLY for Admins -->
                        <?php if ($isAdmin): ?>
                        <td>
                            <!-- Close Button Form -->
                            <form action="" method="POST" style="display:inline-block;">
                                <input type="hidden" name="id" value="<?php echo $issue['id']; ?>">
                                <button type="submit" name="closeIssue" class="btn btn-secondary btn-sm issue-close-btn" title="Close Issue" <?php echo $isClosed ? 'disabled' : ''; ?>>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/></svg> Close
                                </button>
                            </form>

                            <!-- View/Edit Link (Goes to view_issue.php for details/comments/editing) -->
                            <a href="view_issue.php?id=<?php echo $issue['id']; ?>"
                               class="btn btn-warning btn-sm"
                               title="View Issue Details and Comments">
                               <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg> View/Edit
                            </a>

                            <!-- Delete Button Form -->
                            <form action="" method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to permanently delete this issue and ALL its comments? This cannot be undone.');">
                                <input type="hidden" name="id" value="<?php echo $issue['id']; ?>">
                                <button type="submit" name="deleteIssue" class="btn btn-danger btn-sm" title="Delete Issue">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg> Delete
                                </button>
                            </form>
                        </td>
                        <?php endif; // End $isAdmin check for actions column ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
         <!-- Add message for non-admins if there are issues -->
         <?php if (!$isAdmin && !empty($issues)): ?>
             <p class="text-center text-muted mt-3"><i>Viewing issues only. Contact an administrator for modifications.</i></p>
         <?php endif;?>
    </div>
</div> <!-- /container -->

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
