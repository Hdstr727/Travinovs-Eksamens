<?php
session_start(); // Start session for user login management

// Include the database connection
require '../admin/database/connection.php';

// Initialize an error message
$error = "";
$success = false;

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input to prevent XSS attacks
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if passwords match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username is already taken
        $query = $connection->prepare("SELECT user_id FROM Planotajs_Users WHERE username = ?");
        $query->bind_param("s", $username);
        $query->execute();
        $result = $query->get_result();

        if ($result->num_rows > 0) {
            $error = "Username is already taken.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user into the database
            $insert = $connection->prepare("INSERT INTO Planotajs_Users (username, password, is_deleted, created_at) VALUES (?, ?, 0, NOW())");
            $insert->bind_param("ss", $username, $hashed_password);

            if ($insert->execute()) {
                // Get the inserted user's ID
                $user_id = $insert->insert_id;
                
                // Set session variables to log in the user
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                
                // Set success flag to trigger notification
                $success = true;
            } else {
                $error = "Something went wrong. Please try again.";
            }
            $insert->close();
        }
        $query->close();
    }
}

// Close the database connection
$connection->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        <?php if ($success): ?>
            let count = 3;
            function countdown() {
                document.getElementById('countdown').textContent = count;
                if (count > 0) {
                    count--;
                    setTimeout(countdown, 1000);
                } else {
                    window.location.href = 'index.php';
                }
            }
            window.onload = countdown;
        <?php endif; ?>
    </script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-md text-center">
        <?php if ($success): ?>
            <div class="text-green-600 font-semibold text-lg mb-4">
                Registration successful! Redirecting in <span id="countdown">3</span> seconds...
            </div>
        <?php else: ?>
            <h2 class="text-2xl font-bold text-[#e63946] mb-4">Create an Account</h2>
            <?php if ($error): ?>
                <p class="text-sm font-semibold text-red-600 mb-4"><?php echo $error; ?></p>
            <?php endif; ?>
            <form method="POST" action="" class="flex flex-col gap-4">
                <input type="text" name="username" placeholder="Username" required
                    class="w-full p-3 text-lg border-2 border-[#e63946] rounded-md outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-300">
                <input type="password" name="password" placeholder="Password" required
                    class="w-full p-3 text-lg border-2 border-[#e63946] rounded-md outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-300">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required
                    class="w-full p-3 text-lg border-2 border-[#e63946] rounded-md outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-300">
                <button type="submit" 
                    class="w-full bg-[#e63946] text-white py-3 text-lg font-semibold rounded-md transition hover:bg-red-700 hover:-translate-y-1">
                    Sign Up
                </button>
            </form>
            <div class="mt-4">
                <a href="https://kristovskis.lv/3pt1/travinovs/Travinovs-Eksamens" class="text-[#e63946] hover:underline">← Back to Home Page</a>
            </div>
            <div class="mt-4">
                <p class="text-gray-700">Already have an account? 
                    <a href="login.php" class="text-[#e63946] hover:underline">Log in</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>