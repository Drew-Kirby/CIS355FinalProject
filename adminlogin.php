<?php
session_start();
require 'database/database.php'; // Use centralized connection

$error = null; // Initialize error variable

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        // Retrieve the user from the database by email
        $stmt = $pdo->prepare('SELECT id, email, password, role FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the user exists, verify password, AND check role
        if ($user && password_verify($password, $user['password'])) {
            // Check if the role is 'admin'
            if ($user['role'] === 'admin') {
                // Regenerate session ID for security
                session_regenerate_id(true);
                // Start a session and store user data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role']; // Store the role
                $_SESSION['email'] = $user['email']; // Optional: store email if needed

                // Redirect admin to the main index page (or an admin dashboard if you have one)
                header('Location: index.php');
                exit();
            } else {
                 // Correct password but not an admin
                 $error = "Access Denied: You do not have administrator privileges.";
            }
        } else {
            // Invalid email or password
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Email and password are required.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container mt-5">
    <div class="col-md-6 offset-md-3">
        <h2 class="text-center">Admin Login</h2>
        <hr>
        <?php if (isset($error)): ?>
            <div class='alert alert-danger'><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="adminlogin.php" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter admin email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login as Admin</button>
        </form>
        <hr>
        <div class="text-center">
             <a href="login.php">Not an admin? Login here.</a><br>
             <!-- Link to admin registration - consider protecting this -->
             <a href="adminregister.php">Register new Admin account?</a>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
