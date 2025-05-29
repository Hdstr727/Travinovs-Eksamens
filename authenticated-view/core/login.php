<?php
// authenticated-view/core/login.php
session_start(); 

// Include the database connection
require '../../admin/database/connection.php'; // Adjust path if necessary

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $login_identifier = htmlspecialchars(trim($_POST['login_identifier'])); // Can be username or email
    $password = $_POST['password'];

    if (empty($login_identifier) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        // Prepare a statement to fetch user data by username OR email
        // Ensure `is_deleted = 0` is part of your user active check
        $query = $connection->prepare("SELECT user_id, username, email, password, is_deleted 
                                       FROM Planner_Users 
                                       WHERE (username = ? OR email = ?) AND is_deleted = 0");
        if ($query) {
            $query->bind_param("ss", $login_identifier, $login_identifier);
            $query->execute();
            $result = $query->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username']; // Store the actual username

                    header("Location: ../index.php"); // Redirect to the dashboard
                    exit();
                } else {
                    $error = "Incorrect username/email or password.";
                }
            } else {
                $error = "Incorrect username/email or password.";
            }
            $query->close();
        } else {
            $error = "Database query error. Please try again later.";
            // Log the actual DB error for administrators
            error_log("Login query preparation error: " . $connection->error);
        }
    }
} 

// $connection->close(); // Usually closed at the end of the script or handled by PHP's lifecycle
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Planotajs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/dark-theme.css"> <!-- Adjust path if needed -->
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-md text-center">
        <h2 class="text-2xl font-bold text-[#e63946] mb-4">Login to Planotajs</h2>
        
        <?php if ($error): ?>
            <p class="text-sm font-semibold text-red-600 mb-4"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php" class="flex flex-col gap-4"> <!-- Action to self -->
            <input type="text" name="login_identifier" placeholder="Username or Email" required
                value="<?= isset($login_identifier) ? htmlspecialchars($login_identifier) : '' ?>"
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
          
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const htmlElementLogin = document.documentElement;
            if (localStorage.getItem('darkMode') === 'true') {
                htmlElementLogin.classList.add('dark-mode');
            } else {
                htmlElementLogin.classList.remove('dark-mode');
            }
        });
    </script>
</body>
</html>