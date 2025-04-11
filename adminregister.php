<?php
// Note: This registration should ideally be protected.
// How will the *first* admin be created? Manually in DB?
// Or is this page only accessible if already logged in as admin?
// For now, we'll make it functional but unsecured.
session_start();
require 'database/database.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic Validation
    if (!empty($firstName) && !empty($lastName) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($password)) {

        // Check if email already exists
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmtCheck->execute(['email' => $email]);
        if ($stmtCheck->fetch()) {
            $error = "Email address already registered.";
        } else {
            // Hash the password before storing it
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Prepare statement including the 'role'
            $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password, role) VALUES (:first_name, :last_name, :email, :password, :role)');

            try {
                $stmt->execute([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'role' => 'admin' // <-- Set role to 'admin' here
                ]);
                // Redirect to admin login after successful registration
                $_SESSION['success_message'] = 'Admin account registered successfully. Please login.';
                header('Location: adminlogin.php');
                exit();
            } catch (PDOException $e) {
                 error_log("Admin Registration failed: " . $e->getMessage());
                 if ($e->getCode() == '23000') { // Integrity constraint violation (like duplicate email - though checked above)
                      $error = "Registration failed: Email might already be taken.";
                 } else {
                      $error = "Registration failed due to a database error.";
                 }
            }
        }
    } else {
        $error = "Please fill in all fields correctly.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container mt-5">
     <div class="col-md-6 offset-md-3">
        <h2 class="text-center">Admin Registration</h2>
        <p class="text-center text-muted">(Use this only for creating administrator accounts)</p>
        <hr>
        <?php if (isset($error)): ?><div class='alert alert-danger'><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (isset($success)): ?><div class='alert alert-success'><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <form action="adminregister.php" method="POST">
            <div class="form-group">
                 <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" class="form-control" placeholder="First Name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                 <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="form-control" placeholder="Last Name" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                 <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                 <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Register Admin Account</button>
        </form>
         <hr>
        <div class="text-center">
             <a href="adminlogin.php">Already have an admin account? Login</a><br>
              <a href="login.php">User Login</a>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
