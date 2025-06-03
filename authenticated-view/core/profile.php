<?php
// authenticated-view/core/profile.php
session_start();
require_once dirname(dirname(dirname(__FILE__))) . '/config.php'; // Ensure this path is correct
if (!isset($_SESSION['user_id'])) {
    header("Location:" . LOGIN_URL); // Make sure LOGIN_URL is defined in config.php
    exit();
}

require '../../admin/database/connection.php'; // Adjust path

$user_id = $_SESSION['user_id'];
$message = "";
$messageType = ""; // "success" or "error"

// Fetch current user data
$sql_user_data = "SELECT username, email, full_name, bio, profile_picture FROM Planner_Users WHERE user_id = ?";
$stmt_user_data = $connection->prepare($sql_user_data);
$stmt_user_data->bind_param("i", $user_id);
$stmt_user_data->execute();
$result_user_data = $stmt_user_data->get_result();
$user = $result_user_data->fetch_assoc();
$stmt_user_data->close();

if (!$user) {
    // Should not happen if session is valid, but good to check
    session_destroy();
    header("Location:" . LOGIN_URL);
    exit();
}

$current_username = $user['username']; // For checking if username changed
$current_email = $user['email'];       // For checking if email changed

// Avatar logic (remains the same as your original)
$db_profile_picture_path = $user['profile_picture'];
$full_server_path_to_picture = __DIR__ . '/' . $db_profile_picture_path; // Assumes profile_picture path is relative to core/
if (!empty($db_profile_picture_path) && file_exists($full_server_path_to_picture)) {
    $user_avatar = $db_profile_picture_path;
} else {
    $user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($current_username) . "&background=e63946&color=fff";
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $update_attempted = false; // Flag to see if any update was meant to happen

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $update_attempted = true;
        // ... (your existing picture upload logic, seems okay) ...
        // Ensure $uploadPath is relative to the 'core' directory if that's how it's stored.
        // e.g., $uploadPath = 'uploads/profile_pictures/' . $newFilename;
        // and the actual move is to __DIR__ . '/uploads/profile_pictures/' . $newFilename;
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($fileExt, $allowed)) {
            $newFilename = "user_" . $user_id . "_" . time() . "." . $fileExt;
            $uploadDirRelative = 'uploads/profile_pictures/'; // Relative to /core/ for DB
            $uploadDirAbsolute = __DIR__ . '/' . $uploadDirRelative; 
            
            if (!is_dir($uploadDirAbsolute)) {
                mkdir($uploadDirAbsolute, 0755, true);
            }
            $uploadPathAbsolute = $uploadDirAbsolute . $newFilename;
            $uploadPathRelative = $uploadDirRelative . $newFilename; // For DB
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPathAbsolute)) {
                $updatePicSql = "UPDATE Planner_Users SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?";
                $updatePicStmt = $connection->prepare($updatePicSql);
                $updatePicStmt->bind_param("si", $uploadPathRelative, $user_id);
                if ($updatePicStmt->execute()) {
                    $message = "Profile picture updated successfully!"; $messageType = "success";
                    $user['profile_picture'] = $uploadPathRelative; // Update current user array
                    $user_avatar = $uploadPathRelative;
                } else { $message = "Failed to update database for picture: " . $connection->error; $messageType = "error"; }
                $updatePicStmt->close();
            } else { $message = "Failed to upload file."; $messageType = "error"; }
        } else { $message = "Invalid file format. Allowed: " . implode(", ", $allowed); $messageType = "error"; }
    }
    
    // Handle other profile updates
    $username_updated = false;
    $email_updated = false;

    if (isset($_POST['update_profile_info_submit'])) { // Use a named submit button
        $update_attempted = true;
        $new_username = trim($_POST['username']);
        $new_email = trim($_POST['email']);
        $new_fullname = trim($_POST['full_name'] ?? ''); // Default to empty if not set
        $new_bio = trim($_POST['bio'] ?? '');           // Default to empty if not set

        $profile_errors = [];

        // Validate username
        if (empty($new_username)) {
            $profile_errors[] = "Username cannot be empty.";
        } elseif (strlen($new_username) < 3) {
            $profile_errors[] = "Username must be at least 3 characters.";
        } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $new_username)) {
            $profile_errors[] = "Username can only contain letters, numbers, and underscores.";
        } elseif ($new_username !== $current_username) { // Check uniqueness only if changed
            $stmt_check_username = $connection->prepare("SELECT user_id FROM Planner_Users WHERE username = ? AND user_id != ?");
            $stmt_check_username->bind_param("si", $new_username, $user_id);
            $stmt_check_username->execute();
            if ($stmt_check_username->get_result()->num_rows > 0) {
                $profile_errors[] = "Username '{$new_username}' is already taken.";
            }
            $stmt_check_username->close();
            $username_updated = true;
        }

        // Validate email
        if (empty($new_email)) {
            $profile_errors[] = "Email cannot be empty.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $profile_errors[] = "Invalid email format.";
        } elseif ($new_email !== $current_email) { // Check uniqueness only if changed
            $stmt_check_email = $connection->prepare("SELECT user_id FROM Planner_Users WHERE email = ? AND user_id != ?");
            $stmt_check_email->bind_param("si", $new_email, $user_id);
            $stmt_check_email->execute();
            if ($stmt_check_email->get_result()->num_rows > 0) {
                $profile_errors[] = "Email '{$new_email}' is already registered to another account.";
            }
            $stmt_check_email->close();
            $email_updated = true;
        }

        if (empty($profile_errors)) {
            $updateProfileSql = "UPDATE Planner_Users SET 
                               username = ?, email = ?, full_name = ?, bio = ?, updated_at = NOW() 
                               WHERE user_id = ?";
            $updateProfileStmt = $connection->prepare($updateProfileSql);
            $updateProfileStmt->bind_param("ssssi", $new_username, $new_email, $new_fullname, $new_bio, $user_id);
            
            if ($updateProfileStmt->execute()) {
                if (empty($message)) { // Don't overwrite picture upload message if it was set
                    $message = "Profile information updated successfully!"; $messageType = "success";
                } elseif ($messageType == "success") { // Append if previous was also success
                    $message .= "<br>Profile information updated successfully!";
                }

                $_SESSION['username'] = $new_username; // Update session username
                $user['username'] = $new_username;     // Update local $user array
                $user['email'] = $new_email;
                $user['full_name'] = $new_fullname;
                $user['bio'] = $new_bio;
                $current_username = $new_username; // Update for avatar logic
                $current_email = $new_email;
            } else {
                $message = "Failed to update profile information: " . $connection->error; $messageType = "error";
            }
            $updateProfileStmt->close();
        } else {
            $message = implode("<br>", $profile_errors); $messageType = "error";
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password_submit'])) { // Use a named submit button
        $update_attempted = true;
        $current_password_input = $_POST['current_password'] ?? '';
        $new_password_input = $_POST['new_password'] ?? '';
        $confirm_password_input = $_POST['confirm_password'] ?? '';

        if (empty($current_password_input) || empty($new_password_input) || empty($confirm_password_input)) {
            $message = "All password fields are required to change password."; $messageType = "error";
        } elseif (strlen($new_password_input) < 6) {
            $message = "New password must be at least 6 characters long."; $messageType = "error";
        } elseif ($new_password_input !== $confirm_password_input) {
            $message = "New passwords do not match!"; $messageType = "error";
        } else {
            $passwordSql = "SELECT password FROM Planner_Users WHERE user_id = ?";
            // ... (your existing password change logic, seems okay) ...
            $passwordStmt = $connection->prepare($passwordSql);
            $passwordStmt->bind_param("i", $user_id);
            $passwordStmt->execute();
            $passwordResult = $passwordStmt->get_result();
            $passwordData = $passwordResult->fetch_assoc();
            $passwordStmt->close();
            
            if (password_verify($current_password_input, $passwordData['password'])) {
                $hashed_password = password_hash($new_password_input, PASSWORD_DEFAULT);
                $updatePasswordSql = "UPDATE Planner_Users SET password = ?, updated_at = NOW() WHERE user_id = ?";
                $updatePasswordStmt = $connection->prepare($updatePasswordSql);
                $updatePasswordStmt->bind_param("si", $hashed_password, $user_id);
                if ($updatePasswordStmt->execute()) {
                    $message = "Password changed successfully!"; $messageType = "success";
                } else { $message = "Failed to change password: " . $connection->error; $messageType = "error"; }
                $updatePasswordStmt->close();
            } else { $message = "Current password is incorrect!"; $messageType = "error"; }
        }
    }

    // If no specific submit button was pressed but some POST data exists (e.g. old form structure)
    // and no message has been set yet, it might be an unintended submission.
    if (!$update_attempted && !empty($_POST) && empty($message)) {
        // $message = "No changes submitted or unrecognized action."; $messageType = "info";
    }
    
    // Re-evaluate avatar if username changed (for ui-avatars fallback) or picture was updated
    if ($username_updated || (isset($_FILES['profile_picture']) && $messageType == "success")) {
        $db_profile_picture_path = $user['profile_picture']; // Use updated user array
        $full_server_path_to_picture = __DIR__ . '/' . $db_profile_picture_path;
        if (!empty($db_profile_picture_path) && file_exists($full_server_path_to_picture)) {
            $user_avatar = $db_profile_picture_path;
        } else {
            $user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=e63946&color=fff";
        }
    }
}

$title = "Edit Profile - Planotajs";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/dark-theme.css"> 
    <link rel="icon" type="image/x-icon" href="../../assets/favicon.ico">
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col">
   
    <header class="bg-white shadow-md p-4 flex justify-between items-center">
        <h1 class="text-xl font-bold text-[#e63946]">Planotajs</h1>
        <nav>
            <a href="../index.php" class="text-gray-700 hover:text-[#e63946] ml-40">Back to Dashboard</a>
        </nav>
        <div class="flex items-center gap-4">
            <button id="dark-mode-toggle-profile" title="Toggle dark mode" class="bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition">🌙</button>
            <a href="profile.php" class="relative group">    
                <img src="<?= htmlspecialchars($user_avatar) ?>" class="w-10 h-10 rounded-full border group-hover:opacity-90 transition-opacity object-cover" alt="Avatar">
            </a>
            <span class="font-semibold"><?= htmlspecialchars($user['username']) ?></span>
            <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-700">Logout</a>
        </div>
    </header>

    <main class="flex-grow container mx-auto p-6">
        <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold mb-6 text-center text-[#e63946]">Edit Profile</h2>
            
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-md <?= $messageType === 'success' ? 'bg-green-100 text-green-700' : ($messageType === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>">
                    <?= $message /* HTML is allowed here if $profile_errors implode with <br> */ ?>
                </div>
            <?php endif; ?>
            
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <div class="flex justify-center mb-6">
                    <div class="relative">
                        <img src="<?= htmlspecialchars($user_avatar) ?>" id="profile-preview" class="w-24 h-24 rounded-full border-4 border-[#e63946] object-cover" alt="Avatar Preview">
                    </div>
                </div>
                <div class="flex justify-center mb-6">
                    <input type="file" name="profile_picture" id="profile_picture" class="hidden" accept="image/png, image/jpeg, image/gif">
                    <label for="profile_picture" class="bg-[#e63946] text-white px-4 py-2 rounded-lg hover:bg-red-700 cursor-pointer transition-colors">
                        Upload New Picture
                    </label>
                </div>
                
                <hr class="my-6">
                <h3 class="text-lg font-medium mb-4">Profile Information</h3>
                <div class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" id="username" name="username" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="email" name="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="bio" class="block text-sm font-medium text-gray-700">About me</label>
                        <textarea id="bio" name="bio" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                     <div class="flex justify-end">
                        <button type="submit" name="update_profile_info_submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            Save Profile Information
                        </button>
                    </div>
                </div>
            </form>
            
            <hr class="my-8">
            
            <form action="profile.php" method="POST">
                <h3 class="text-lg font-medium mb-4">Change Password</h3>
                <div class="space-y-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" autocomplete="current-password">
                    </div>
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" autocomplete="new-password">
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" autocomplete="new-password">
                    </div>
                </div>
                 <div class="flex justify-end mt-6">
                    <button type="submit" name="change_password_submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-colors">
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </main>

    <footer class="bg-gray-200 text-center p-4 text-gray-600">© <?= date("Y") ?> Planotajs. All rights reserved.</footer>
    
    <script>
    // Script for image preview and dark mode toggle (remains the same as your original profile.php)
    document.addEventListener('DOMContentLoaded', function () {
        const profilePictureInput = document.getElementById('profile_picture');
        if (profilePictureInput) {
            profilePictureInput.addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        const profilePreviewImg = document.getElementById('profile-preview');
                        if (profilePreviewImg) profilePreviewImg.src = ev.target.result;
                        const headerAvatar = document.querySelector('header img.rounded-full');
                        if (headerAvatar) headerAvatar.src = ev.target.result;
                    }
                    reader.readAsDataURL(e.target.files[0]);
                }
            });
        }

        const darkModeToggleProfile = document.getElementById('dark-mode-toggle-profile');
        const htmlElementProfile = document.documentElement;
        function setProfileDarkMode(isDark) {
            if (isDark) {
                htmlElementProfile.classList.add('dark-mode');
                if (darkModeToggleProfile) darkModeToggleProfile.textContent = '☀️';
            } else {
                htmlElementProfile.classList.remove('dark-mode');
                if (darkModeToggleProfile) darkModeToggleProfile.textContent = '🌙';
            }
        }
        if (localStorage.getItem('darkMode') === 'true') {
            setProfileDarkMode(true);
        } else {
            setProfileDarkMode(false);
        }
        if (darkModeToggleProfile) {
            darkModeToggleProfile.addEventListener('click', () => {
                const isCurrentlyDark = htmlElementProfile.classList.contains('dark-mode');
                setProfileDarkMode(!isCurrentlyDark);
                localStorage.setItem('darkMode', !isCurrentlyDark);
            });
        }
    });
    </script>
</body>
</html>