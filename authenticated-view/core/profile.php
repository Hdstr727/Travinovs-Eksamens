<?php
session_start();
//core/profile.php
require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location:" . LOGIN_URL);
    exit();
}

// Include database connection
require '../../admin/database/connection.php';

// Get user info from database
$user_id = $_SESSION['user_id'];
$sql = "SELECT username, email, full_name, bio, profile_picture FROM Planotajs_Users WHERE user_id = ?"; // Assuming Planotajs_Users is the table name and should not be translated
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Set username from database or session
$username = $user['username'] ?? $_SESSION['username'] ?? "User";

// Get the path stored in the database (e.g., "uploads/profile_pictures/image.jpg")
$db_profile_picture_path = $user['profile_picture'];

// For file_exists(), construct the full server path.
// __DIR__ is the directory of profile.php (authenticated-view/core/)
$full_server_path_to_picture = __DIR__ . '/' . $db_profile_picture_path;

if (!empty($db_profile_picture_path) && file_exists($full_server_path_to_picture)) {
    // For the <img> src attribute, profile.php is in 'core/', and the path
    // in the DB is relative to 'core/', so it can be used directly.
    $user_avatar = $db_profile_picture_path;
} else {
    $user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=e63946&color=fff";
}

// Handle form submission
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $fileExt = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Verify file extension
        if (in_array(strtolower($fileExt), $allowed)) {
            // Create a unique filename
            $newFilename = "user_" . $user_id . "_" . time() . "." . $fileExt;
            $uploadDir = 'uploads/profile_pictures/'; // Directory path, usually not translated
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $uploadPath = $uploadDir . $newFilename;
            
            // Move the uploaded file
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                // Update database with new profile picture path
                $updateSql = "UPDATE Planotajs_Users SET profile_picture = ?, updated_at = NOW() WHERE user_id = ?";
                $updateStmt = $connection->prepare($updateSql);
                $updateStmt->bind_param("si", $uploadPath, $user_id);
                
                if ($updateStmt->execute()) {
                    $message = "Profile picture updated successfully!";
                    $messageType = "success";
                    $user_avatar = $uploadPath; // Update current avatar
                } else {
                    $message = "Failed to update database: " . $connection->error;
                    $messageType = "error";
                }
                $updateStmt->close();
            } else {
                $message = "Failed to upload file.";
                $messageType = "error";
            }
        } else {
            $message = "Invalid file format. Allowed formats: " . implode(", ", $allowed);
            $messageType = "error";
        }
    }
    
    // Handle other profile updates (username, email, etc.)
    // Check if at least one of the profile fields is submitted to avoid empty updates if only picture or password is changed
    if (isset($_POST['username']) || isset($_POST['email']) || isset($_POST['full_name']) || isset($_POST['bio'])) {
        // Only proceed if username is present (assuming it's a key field for this update block)
        if (isset($_POST['username']) && !empty($_POST['username'])) {
            $new_username = trim($_POST['username']);
            $new_email = trim($_POST['email'] ?? $user['email']); // Keep old if not provided
            $new_fullname = trim($_POST['full_name'] ?? $user['full_name']); // Keep old if not provided
            $new_bio = trim($_POST['bio'] ?? $user['bio']); // Keep old if not provided
            
            $updateProfileSql = "UPDATE Planotajs_Users SET 
                               username = ?, 
                               email = ?, 
                               full_name = ?, 
                               bio = ?, 
                               updated_at = NOW() 
                               WHERE user_id = ?";
            
            $updateProfileStmt = $connection->prepare($updateProfileSql);
            $updateProfileStmt->bind_param("ssssi", $new_username, $new_email, $new_fullname, $new_bio, $user_id);
            
            if ($updateProfileStmt->execute()) {
                if (empty($message)) { // Don't overwrite picture upload message
                    $message = "Profile information updated successfully!";
                    $messageType = "success";
                }
                $_SESSION['username'] = $new_username; // Update session
                $username = $new_username; // Update current page
            } else {
                if (empty($message)) {
                    $message = "Failed to update profile information: " . $connection->error;
                    $messageType = "error";
                }
            }
            $updateProfileStmt->close();
        } elseif (empty($_FILES['profile_picture']['name']) && empty($_POST['current_password'])) {
             // Only show error if this is the only action attempted and username is empty
            $message = "Username cannot be empty.";
            $messageType = "error";
        }
    }
    
    // Handle password change
    if (isset($_POST['current_password']) && !empty($_POST['current_password']) && 
        isset($_POST['new_password']) && !empty($_POST['new_password']) && 
        isset($_POST['confirm_password']) && !empty($_POST['confirm_password'])) {
        
        // Get current password from DB
        $passwordSql = "SELECT password FROM Planotajs_Users WHERE user_id = ?";
        $passwordStmt = $connection->prepare($passwordSql);
        $passwordStmt->bind_param("i", $user_id);
        $passwordStmt->execute();
        $passwordResult = $passwordStmt->get_result();
        $passwordData = $passwordResult->fetch_assoc();
        $passwordStmt->close();
        
        $current_password_input = $_POST['current_password'];
        $new_password_input = $_POST['new_password'];
        $confirm_password_input = $_POST['confirm_password'];
        
        // Verify current password
        if (password_verify($current_password_input, $passwordData['password'])) {
            // Check if new passwords match
            if ($new_password_input === $confirm_password_input) {
                // Hash new password and update
                $hashed_password = password_hash($new_password_input, PASSWORD_DEFAULT);
                $updatePasswordSql = "UPDATE Planotajs_Users SET password = ?, updated_at = NOW() WHERE user_id = ?";
                $updatePasswordStmt = $connection->prepare($updatePasswordSql);
                $updatePasswordStmt->bind_param("si", $hashed_password, $user_id);
                
                if ($updatePasswordStmt->execute()) {
                    $message = "Password changed successfully!"; // This can overwrite previous messages
                    $messageType = "success";
                } else {
                    $message = "Failed to change password: " . $connection->error;
                    $messageType = "error";
                }
                $updatePasswordStmt->close();
            } else {
                $message = "New passwords do not match!";
                $messageType = "error";
            }
        } else {
            $message = "Current password is incorrect!";
            $messageType = "error";
        }
    }
    
    // Refresh user data after updates
    $stmt = $connection->prepare($sql); // $sql is defined at the top
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc(); // Update $user array with new data
    $stmt->close();

    // Re-evaluate avatar if profile picture was part of the update
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0 && $messageType == "success") {
        // $user_avatar already updated if picture upload was successful
    } else { // Or if other user data changed, ensure avatar reflects current state
        $db_profile_picture_path = $user['profile_picture'];
        $full_server_path_to_picture = __DIR__ . '/' . $db_profile_picture_path;
        if (!empty($db_profile_picture_path) && file_exists($full_server_path_to_picture)) {
            $user_avatar = $db_profile_picture_path;
        } else {
            $user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=e63946&color=fff";
        }
    }
}

$title = "Edit Profile - Planner+"; // Assuming Plānotājs+ translates to Planner+
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col">
   
    <!-- Header -->
    <header class="bg-white shadow-md p-4 flex justify-between items-center">
        <h1 class="text-xl font-bold text-[#e63946]">Planner+</h1>
        <nav class="flex gap-4">
            <a href="../index.php" class="text-gray-700 hover:text-[#e63946] ml-8">Back to dashboard</a>
        </nav>
        <div class="flex items-center gap-4">
            <a href="profile.php" class="relative group">
                <img src="<?= htmlspecialchars($user_avatar) ?>" class="w-10 h-10 rounded-full border group-hover:opacity-90 transition-opacity object-cover" alt="Avatar">
                <div class="absolute opacity-0 group-hover:opacity-100 transition-opacity text-xs bg-black text-white px-2 py-1 rounded -bottom-8 left-1/2 transform -translate-x-1/2 whitespace-nowrap">
                    Edit profile
                </div>
            </a>
            <span class="font-semibold"><?= htmlspecialchars($username) ?></span>
            <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-700">Logout</a>
        </div>
    </header>

    <!-- Content -->
    <main class="flex-grow container mx-auto p-6">
        <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold mb-6 text-center text-[#e63946]">Edit Profile</h2>
            
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-md <?= $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <div class="flex justify-center mb-6">
                <div class="relative">
                    <img src="<?= htmlspecialchars($user_avatar) ?>" id="profile-preview" class="w-24 h-24 rounded-full border-4 border-[#e63946] object-cover" alt="Avatar Preview">
                </div>
            </div>
            
            <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                <!-- Profile Picture Upload -->
                <div class="flex justify-center">
                    <input type="file" name="profile_picture" id="profile_picture" class="hidden" accept="image/png, image/jpeg, image/gif">
                    <label for="profile_picture" class="bg-[#e63946] text-white px-4 py-2 rounded-lg hover:bg-red-700 cursor-pointer transition-colors">
                        Upload new picture
                    </label>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" id="username" name="username" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" value="<?= htmlspecialchars($user['username'] ?? $username) ?>" required>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="email" name="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>
                    
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                    </div>
                    
                    <div>
                        <label for="bio" class="block text-sm font-medium text-gray-700">About me</label>
                        <textarea id="bio" name="bio" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    
                    <hr class="my-6">
                    
                    <h3 class="text-lg font-medium">Change Password</h3>
                    
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
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-200 text-center p-4 text-gray-600">
        © <?= date("Y") ?> Planner+. All rights reserved.
    </footer>
    
    <script>
    // Show selected image preview before upload
    document.getElementById('profile_picture').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Update the main profile preview
                document.getElementById('profile-preview').src = e.target.result;
                
                // Also update the header avatar if needed
                const headerAvatar = document.querySelector('header img.rounded-full');
                if (headerAvatar) {
                    headerAvatar.src = e.target.result;
                }
            }
            reader.readAsDataURL(e.target.files[0]);
        }
    });
    </script>
</body>
</html>