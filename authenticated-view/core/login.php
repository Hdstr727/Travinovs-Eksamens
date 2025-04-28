<?php
session_start(); // Start session for user login management

// Include the database connection
require '../../admin/database/connection.php';

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
            header("Location: ../index.php");
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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-md text-center">
        <h2 class="text-2xl font-bold text-[#e63946] mb-4">Login to the System</h2>
        
        <?php if ($error): ?>
            <p class="text-sm font-semibold text-red-600 mb-4"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" action="" class="flex flex-col gap-4">
            <input type="text" name="username" placeholder="Username" required
                class="w-full p-3 text-lg border-2 border-[#e63946] rounded-md outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-300">
            <input type="password" name="password" placeholder="Password" required
                class="w-full p-3 text-lg border-2 border-[#e63946] rounded-md outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-300">
            <button type="submit" 
                class="w-full bg-[#e63946] text-white py-3 text-lg font-semibold rounded-md transition hover:bg-red-700 hover:-translate-y-1">
                Log in
            </button>
        </form>

        <div class="mt-4">
            <a href="https://kristovskis.lv/3pt1/travinovs/Travinovs-Eksamens" class="text-[#e63946] hover:underline">← Back to Home Page</a>
        </div>

        <div class="mt-4">
            <p class="text-gray-700">Don't have an account? 
                <a href="register.php" class="text-[#e63946] hover:underline">Sign up</a>
            </p>
        </div>
    </div>
</body>
</html>