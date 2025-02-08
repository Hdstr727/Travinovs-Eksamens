<?php
session_start(); // Start session for user login management

// Include the database connection
require '../admin/database/connection.php';

// Initialize an error message
$error = "";

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input to prevent XSS attacks
    $username = htmlspecialchars($_POST['username']);
    $password = $_POST['password'];

    // Prepare a statement to securely fetch user data
    $query = $connection->prepare("SELECT user_id, username, password, is_deleted FROM Planotajs_Users WHERE username = ? AND is_deleted = 0");
    $query->bind_param("s", $username);
    $query->execute();
    $result = $query->get_result();

    // Check if a user with the given username exists
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verify the hashed password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];

            // Redirect to the main page
            header("Location: index.php");
            exit();
        } else {
            $error = "Incorrect username or password.";
        }
    } else {
        $error = "Incorrect username or password.";
    }

    // Close the prepared statement
    $query->close();
}

// Close the database connection
$connection->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="login-container">
        <h2>Login to the System</h2>
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Log in</button>
        </form>
    </div>
</body>
</html>
