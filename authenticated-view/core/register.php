<?php
// authenticated-view/core/register.php
session_start();

// If user is already logged in, redirect them from register page
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php"); // Redirect to dashboard or home
    exit();
}

require '../../admin/database/connection.php'; // Adjust path if necessary

$errors = []; // Array to store validation errors
$username_val = ""; // To repopulate form field on error
$email_val = "";    // To repopulate form field on error
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_val = trim($_POST['username']); 
    $email_val = trim($_POST['email']);       
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic Validations
    if (empty($username_val)) {
        $errors['username'] = "Username is required.";
    } elseif (strlen($username_val) < 3) {
        $errors['username'] = "Username must be at least 3 characters long.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $username_val)) {
        $errors['username'] = "Username can only contain letters, numbers, and underscores.";
    }

    if (empty($email_val)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters long.";
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    // If no basic validation errors so far, check for uniqueness.
    // We perform these checks even if one of the basic validations above failed for username/email,
    // because the user might fix that and still have a uniqueness issue.
    // However, the INSERT will only happen if $errors is completely empty.

    // Check if username already exists (only if username itself is not empty and format is okay)
    if (!isset($errors['username'])) { // Proceed only if username basic validation passed
        $stmt_check_username = $connection->prepare("SELECT user_id FROM Planner_Users WHERE username = ?");
        if ($stmt_check_username) {
            $stmt_check_username->bind_param("s", $username_val);
            $stmt_check_username->execute();
            $result_username = $stmt_check_username->get_result();
            if ($result_username->num_rows > 0) {
                $errors['username'] = "Username already taken. Please choose another.";
            }
            $stmt_check_username->close();
        } else {
            // Add to a general db error if not already set, to avoid overwriting specific field errors
            if (!isset($errors['db'])) $errors['db'] = "Database error (username check).";
            error_log("Register: Username check prepare error - " . $connection->error);
        }
    }

    // Check if email already exists (only if email itself is not empty and format is okay)
    if (!isset($errors['email'])) { // Proceed only if email basic validation passed
        $stmt_check_email = $connection->prepare("SELECT user_id FROM Planner_Users WHERE email = ?");
        if ($stmt_check_email) {
            $stmt_check_email->bind_param("s", $email_val);
            $stmt_check_email->execute();
            $result_email = $stmt_check_email->get_result();
            if ($result_email->num_rows > 0) {
                $errors['email'] = "Email address already registered. Please use another or login.";
            }
            $stmt_check_email->close();
        } else {
            if (!isset($errors['db'])) $errors['db'] = "Database error (email check).";
            error_log("Register: Email check prepare error - " . $connection->error);
        }
    }
    

    // If still no errors after all checks (basic validation + uniqueness), proceed with registration
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $is_deleted = 0; 
        
        $query_insert = $connection->prepare("INSERT INTO Planner_Users (username, email, password, is_deleted) VALUES (?, ?, ?, ?)");
        if ($query_insert) {
            $query_insert->bind_param("sssi", $username_val, $email_val, $hashed_password, $is_deleted);
            if ($query_insert->execute()) {
                $new_user_id = $query_insert->insert_id;

                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['username'] = $username_val; 
                $_SESSION['registration_success'] = "Registration successful! Welcome, " . htmlspecialchars($username_val) . "!";

                header("Location: ../index.php"); 
                exit();

            } else {
                if ($connection->errno == 1062) { 
                    if (strpos($connection->error, $connection->real_escape_string('UQ_UserEmail')) !== false || strpos($connection->error, $connection->real_escape_string('email')) !== false) { // Check for your actual unique key name for email
                         $errors['email'] = "This email address is already registered (insert check).";
                    } elseif (strpos($connection->error, $connection->real_escape_string('UQ_Username')) !== false || strpos($connection->error, $connection->real_escape_string('username_UNIQUE')) !== false) { // Check for your actual unique key name for username
                         $errors['username'] = "This username is already taken (insert check).";
                    } else {
                         $errors['db'] = "An account with this username or email already exists (insert check).";
                    }
                } else {
                    $errors['db'] = "Registration failed. DB Error: " . $query_insert->error;
                }
                error_log("Register: Insert user error - " . $query_insert->error . " (Errno: " . $connection->errno . ")");
            }
            $query_insert->close();
        } else {
            $errors['db'] = "Database error (insert user prepare).";
            error_log("Register: Insert user prepare error - " . $connection->error);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Planner+</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/dark-theme.css"> 
    <link rel="icon" type="image/x-icon" href="../../assets/favicon.ico">
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-md text-center">
        <h2 class="text-2xl font-bold text-[#e63946] mb-6">Create Your Planner+ Account</h2>
        
        <?php if (!empty($errors['db'])): // Display general DB errors prominently ?>
            <p class="text-sm font-semibold text-red-600 mb-4 p-3 bg-red-100 rounded-md"><?php echo htmlspecialchars($errors['db']); ?></p>
        <?php endif; ?>

        <form method="POST" action="register.php" class="flex flex-col gap-4">
            <div>
                <input type="text" name="username" placeholder="Username" required
                    value="<?= htmlspecialchars($username_val) ?>"
                    class="w-full p-3 text-lg border-2 <?= isset($errors['username']) ? 'border-red-500' : 'border-[#e63946]' ?> rounded-md outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-300">
                <?php if (isset($errors['username'])): ?><p class="text-xs text-red-500 mt-1 text-left"><?= htmlspecialchars($errors['username']) ?></p><?php endif; ?>
            </div>
            <div>
                <input type="email" name="email" placeholder="Email Address" required
                    value="<?= htmlspecialchars($email_val) ?>"
                    class="w-full p-3 text-lg border-2 <?= isset($errors['email']) ? 'border-red-500' : 'border-[#e63946]' ?> rounded-md outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-300">
                <?php if (isset($errors['email'])): ?><p class="text-xs text-red-500 mt-1 text-left"><?= htmlspecialchars($errors['email']) ?></p><?php endif; ?>
            </div>
            <div>
                <input type="password" name="password" placeholder="Password (min. 6 characters)" required
                    class="w-full p-3 text-lg border-2 <?= isset($errors['password']) ? 'border-red-500' : 'border-[#e63946]' ?> rounded-md outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-300">
                <?php if (isset($errors['password'])): ?><p class="text-xs text-red-500 mt-1 text-left"><?= htmlspecialchars($errors['password']) ?></p><?php endif; ?>
            </div>
            <div>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required
                    class="w-full p-3 text-lg border-2 <?= isset($errors['confirm_password']) ? 'border-red-500' : 'border-[#e63946]' ?> rounded-md outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-300">
                <?php if (isset($errors['confirm_password'])): ?><p class="text-xs text-red-500 mt-1 text-left"><?= htmlspecialchars($errors['confirm_password']) ?></p><?php endif; ?>
            </div>
            <button type="submit" 
                class="w-full bg-[#e63946] text-white py-3 text-lg font-semibold rounded-md transition hover:bg-red-700 hover:-translate-y-1">
                Register
            </button>
        </form>
        <div class="mt-4">
            <p class="text-gray-700">Already have an account? 
                <a href="login.php" class="text-[#e63946] hover:underline">Log in</a>
            </p>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const htmlElementRegister = document.documentElement;
            if (localStorage.getItem('darkMode') === 'true') {
                htmlElementRegister.classList.add('dark-mode');
            } else {
                htmlElementRegister.classList.remove('dark-mode');
            }
        });
    </script>
</body>
</html>