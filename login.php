<?php
session_start();
require 'database/database.php'; // Use centralized connection

$error = null; // Initialize error variable

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
     header('Location: index.php'); // Or specific page based on role if needed later
     exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        // Retrieve the user from the database by email
        $stmt = $pdo->prepare('SELECT id, email, password, role FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the user exists and verify the password
        if ($user && password_verify($password, $user['password'])) {
            // Role check is optional here if admins use adminlogin.php
            // but good practice to ensure non-admins use this page
             if ($user['role'] === 'user') {
                 // Regenerate session ID for security
                 session_regenerate_id(true);
                 // Start a session and store user data
                 $_SESSION['user_id'] = $user['id'];
                 $_SESSION['role'] = $user['role']; // Store the role
                 $_SESSION['email'] = $user['email']; // Optional

                 // Redirect user to the main index page
                 header('Location: index.php');
                 exit();
            } else {
                 // User is an admin, should use admin login
                 $error = "Administrators must use the Admin Login page.";
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
    <title>User Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container mt-5">
     <div class="col-md-6 offset-md-3">
        <h2 class="text-center">User Login</h2>
         <hr>
        <?php if (isset($error)): ?>
             <div class='alert alert-danger'><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
         <?php // Display success message if redirected from registration
               $successMessage = $_SESSION['success_message'] ?? null;
               unset($_SESSION['success_message']);
               if ($successMessage):
         ?>
             <div class='alert alert-success'><?php echo htmlspecialchars($successMessage); ?></div>
         <?php endif; ?>


        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
                 <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
         <hr>
        <div class="text-center">
            <a href="register.php">Don't have an account? Register here.</a><br>
            <a href="adminlogin.php">Admin Login</a>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
