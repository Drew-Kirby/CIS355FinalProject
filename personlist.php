<?php
session_start();
require 'database/database.php';

// --- Authentication and Authorization Check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Not logged in
    exit();
}
// Only allow admins to access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
     $_SESSION['error_message'] = "Access Denied: You must be an administrator to view the user list.";
     header('Location: index.php'); // Redirect non-admins
     exit();
}
$isAdmin = true; // We know they are admin if they reach this point

// --- Handle POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle Delete User
    if (isset($_POST['deleteUser']) && isset($_POST['id'])) {
        $idToDelete = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        // Prevent admin from deleting themselves? Optional check:
        // if ($idToDelete && $idToDelete != $_SESSION['user_id']) {
        if ($idToDelete) {
            try {
                // Add transaction? Maybe not critical here unless deleting related data
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute(['id' => $idToDelete]);
                $_SESSION['success_message'] = 'User deleted successfully.';
            } catch (PDOException $e) {
                 error_log("Error deleting user: " . $e->getMessage());
                 $_SESSION['error_message'] = 'Error deleting user.';
            }
        } else {
             $_SESSION['error_message'] = 'Invalid user ID for deletion.';
        }
        header('Location: personlist.php');
        exit();
    }

    // Handle Grant Admin Role
    if (isset($_POST['grant_admin']) && isset($_POST['user_id'])) {
        $userIdToGrant = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if ($userIdToGrant) {
            try {
                 // Grant admin role only if they are currently a 'user'
                 $stmtGrant = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = :id AND role = 'user'");
                 $stmtGrant->execute(['id' => $userIdToGrant]);
                 if ($stmtGrant->rowCount() > 0) {
                      $_SESSION['success_message'] = 'Admin privileges granted successfully.';
                 } else {
                      $_SESSION['info_message'] = 'User was already an admin or user not found.';
                 }
            } catch (PDOException $e) {
                 error_log("Error granting admin role: " . $e->getMessage());
                 $_SESSION['error_message'] = 'Error granting admin privileges.';
            }
        } else {
            $_SESSION['error_message'] = 'Invalid user ID for granting admin.';
        }
         header('Location: personlist.php');
         exit();
    }

     // Handle Revoke Admin Role (Optional Addition)
     if (isset($_POST['revoke_admin']) && isset($_POST['user_id'])) {
        $userIdToRevoke = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
         // IMPORTANT: Prevent admin from revoking their own privileges!
        if ($userIdToRevoke && $userIdToRevoke != $_SESSION['user_id']) {
            try {
                 // Revoke admin role only if they are currently an 'admin'
                 $stmtRevoke = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = :id AND role = 'admin'");
                 $stmtRevoke->execute(['id' => $userIdToRevoke]);
                 if ($stmtRevoke->rowCount() > 0) {
                      $_SESSION['success_message'] = 'Admin privileges revoked successfully.';
                 } else {
                      $_SESSION['info_message'] = 'User was already a standard user or user not found.';
                 }
            } catch (PDOException $e) {
                 error_log("Error revoking admin role: " . $e->getMessage());
                 $_SESSION['error_message'] = 'Error revoking admin privileges.';
            }
        } elseif ($userIdToRevoke == $_SESSION['user_id']) {
             $_SESSION['error_message'] = 'You cannot revoke your own admin privileges.';
        } else {
            $_SESSION['error_message'] = 'Invalid user ID for revoking admin.';
        }
         header('Location: personlist.php');
         exit();
    }

    // Existing Update User functionality (from edit_user.php logic if merged)
    // Ensure this logic remains protected by the admin check at the top
    if (isset($_POST['updateUser']) && isset($_POST['id'])) { // Assuming update logic is here now
        // ... your user update code (first name, last name) ...
        // Remember to redirect after processing
    }

} // End POST handling

// --- Fetch User List ---
try {
    // Fetch role along with other details
    $stmt = $pdo->query('SELECT id, first_name, last_name, email, role FROM users ORDER BY last_name, first_name');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
     error_log("Error fetching user list: " . $e->getMessage());
     $users = [];
     $fetchError = "Could not retrieve user list.";
}

// --- Flash Messages ---
$errorMessage = $_SESSION['error_message'] ?? null;
$successMessage = $_SESSION['success_message'] ?? null;
$infoMessage = $_SESSION['info_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message'], $_SESSION['info_message']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container mt-5">
    <h1>Manage Registered Users</h1>
    <a href="index.php" class="btn btn-secondary mb-3">« Back to Issues</a>

     <!-- Flash Messages -->
    <?php if ($errorMessage): ?> <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div> <?php endif; ?>
    <?php if ($successMessage): ?> <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div> <?php endif; ?>
    <?php if ($infoMessage): ?> <div class="alert alert-info"><?php echo htmlspecialchars($infoMessage); ?></div> <?php endif; ?>
    <?php if (isset($fetchError)): ?> <div class="alert alert-warning"><?php echo htmlspecialchars($fetchError); ?></div> <?php endif; ?>


    <table class="table table-bordered table-striped">
        <thead class="thead-dark">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="4" class="text-center text-muted">No users found.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <?php if ($user['role'] === 'admin'): ?>
                           <span class="badge badge-primary">Admin</span>
                        <?php else: ?>
                           <span class="badge badge-secondary">User</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Edit User Link (assuming edit_user.php handles this) -->
                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">Edit Info</a>

                        <!-- Grant/Revoke Admin Buttons -->
                        <?php if ($user['role'] === 'user'): ?>
                            <form action="personlist.php" method="POST" style="display:inline-block;" onsubmit="return confirm('Grant admin privileges to this user?');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="grant_admin" class="btn btn-sm btn-success">Make Admin</button>
                            </form>
                        <?php elseif ($user['id'] != $_SESSION['user_id']): // Show Revoke button only if it's NOT the currently logged-in admin ?>
                             <form action="personlist.php" method="POST" style="display:inline-block;" onsubmit="return confirm('Revoke admin privileges from this user?');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="revoke_admin" class="btn btn-sm btn-warning">Revoke Admin</button>
                            </form>
                        <?php endif; ?>


                        <!-- Delete User Button -->
                        <?php // Prevent deleting own account ?>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form action="personlist.php" method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this user? This cannot be undone.');">
                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="deleteUser" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
