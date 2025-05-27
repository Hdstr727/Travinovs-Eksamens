<?php
// project_settings_content.php

// Ensure session is started (if not already by an includer script)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Path relative to where project_settings.php (which includes this file) is located.
    // If project_settings.php is in authenticated-view/, then this path is correct.
    header("Location: core/login.php");
    exit();
}

// Include database connection - path relative to the EXECUTING script (project_settings.php)
require_once '../admin/database/connection.php';

// Include core functions - path relative to the EXECUTING script (project_settings.php)
require_once 'core/functions.php';


$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Retrieve flash messages from session
if (isset($_SESSION['settings_message'])) {
    $message = $_SESSION['settings_message'];
    unset($_SESSION['settings_message']);
}
if (isset($_SESSION['settings_error'])) {
    $error = $_SESSION['settings_error'];
    unset($_SESSION['settings_error']);
}

// Get list of boards/projects for the current user (excluding deleted)
$sql_boards = "SELECT board_id, board_name, is_archived
               FROM Planotajs_Boards 
               WHERE user_id = ? AND is_deleted = 0
               ORDER BY board_name ASC";
$stmt_boards = $connection->prepare($sql_boards);
$stmt_boards->bind_param("i", $user_id);
$stmt_boards->execute();
$result_boards = $stmt_boards->get_result();
$boards = [];
while ($board = $result_boards->fetch_assoc()) {
    $boards[] = $board;
}
$stmt_boards->close();

// Handle active board/project selection
$active_board_id = isset($_GET['board_id']) ? (int)$_GET['board_id'] : (isset($boards[0]['board_id']) ? $boards[0]['board_id'] : 0);

// Get board details
$board_details = [];
if ($active_board_id > 0) {
    $sql_details = "SELECT * FROM Planotajs_Boards WHERE board_id = ? AND user_id = ? AND is_deleted = 0";
    $stmt_details = $connection->prepare($sql_details);
    $stmt_details->bind_param("ii", $active_board_id, $user_id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    $board_details = $result_details->fetch_assoc();
    if (!$board_details) { 
        $active_board_id = 0; 
    }
    $stmt_details->close();
}


// Get collaborators for the active board
$collaborators = [];
if ($active_board_id > 0 && $board_details) {
    $sql_create_table = "CREATE TABLE IF NOT EXISTS Planotajs_Collaborators (
        collaboration_id INT AUTO_INCREMENT PRIMARY KEY,
        board_id INT NOT NULL,
        user_id INT NOT NULL,
        permission_level ENUM('view', 'edit', 'admin') NOT NULL DEFAULT 'view',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (board_id) REFERENCES Planotajs_Boards(board_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES Planotajs_Users(user_id) ON DELETE CASCADE,
        UNIQUE KEY (board_id, user_id)
    )";
    $connection->query($sql_create_table);
    
    $sql_collaborators = "SELECT c.collaboration_id, c.permission_level, u.user_id, u.username, u.email
                         FROM Planotajs_Collaborators c
                         JOIN Planotajs_Users u ON c.user_id = u.user_id
                         WHERE c.board_id = ?";
    $stmt_collaborators = $connection->prepare($sql_collaborators);
    $stmt_collaborators->bind_param("i", $active_board_id);
    $stmt_collaborators->execute();
    $result_collaborators = $stmt_collaborators->get_result();
    while ($collab = $result_collaborators->fetch_assoc()) {
        $collaborators[] = $collab;
    }
    $stmt_collaborators->close();
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $active_board_id > 0 && $board_details) {
    // Update project settings
    if (isset($_POST['update_project'])) {
        $board_name = trim($_POST['board_name']);
        $board_description = trim($_POST['board_description']);
        $project_tags = isset($_POST['project_tags']) ? trim($_POST['project_tags']) : ($board_details['tags'] ?? '');
        
        if (!empty($board_name)) {
            $sql_update = "UPDATE Planotajs_Boards SET 
                           board_name = ?, board_description = ?, tags = ?, updated_at = CURRENT_TIMESTAMP
                           WHERE board_id = ? AND user_id = ?";
            $stmt_update = $connection->prepare($sql_update);
            $stmt_update->bind_param("sssii", $board_name, $board_description, $project_tags, $active_board_id, $user_id);
            
            if ($stmt_update->execute()) {
                $message = "Project settings updated successfully!";
                if (function_exists('log_activity')) {
                    log_activity($connection, $active_board_id, $user_id, 'settings_updated', "Project settings for \"{$board_name}\" were updated.");
                }
                $stmt_details_refresh = $connection->prepare($sql_details); 
                $stmt_details_refresh->bind_param("ii", $active_board_id, $user_id);
                $stmt_details_refresh->execute();
                $board_details = $stmt_details_refresh->get_result()->fetch_assoc();
                $stmt_details_refresh->close();

                $stmt_boards_refresh = $connection->prepare($sql_boards); 
                $stmt_boards_refresh->bind_param("i", $user_id);
                $stmt_boards_refresh->execute();
                $result_boards_refresh = $stmt_boards_refresh->get_result();
                $boards = [];
                while ($board_item = $result_boards_refresh->fetch_assoc()) { $boards[] = $board_item; }
                $stmt_boards_refresh->close();
            } else { $error = "Error updating project settings: " . $connection->error; }
            $stmt_update->close();
        } else { $error = "Project name cannot be empty!"; }
    }
    
    // Add collaborator
    if (isset($_POST['add_collaborator'])) {
        $email = trim($_POST['collaborator_email']); $permission = $_POST['permission_level'];
        if (!empty($email)) {
            $sql_check_user = "SELECT user_id, username FROM Planotajs_Users WHERE email = ?"; 
            $stmt_check_user = $connection->prepare($sql_check_user); $stmt_check_user->bind_param("s", $email); $stmt_check_user->execute(); $result_check_user = $stmt_check_user->get_result();
            if ($result_check_user->num_rows > 0) {
                $collab_user = $result_check_user->fetch_assoc(); $collab_user_id = $collab_user['user_id']; $collab_username = $collab_user['username'];
                if ($collab_user_id == $user_id) { $error = "You cannot add yourself as a collaborator."; }
                else {
                    $sql_check_collab = "SELECT collaboration_id FROM Planotajs_Collaborators WHERE board_id = ? AND user_id = ?"; 
                    $stmt_check_collab = $connection->prepare($sql_check_collab); $stmt_check_collab->bind_param("ii", $active_board_id, $collab_user_id); $stmt_check_collab->execute();
                    if ($stmt_check_collab->get_result()->num_rows === 0) {
                        $sql_add_collab = "INSERT INTO Planotajs_Collaborators (board_id, user_id, permission_level) VALUES (?, ?, ?)"; 
                        $stmt_add_collab = $connection->prepare($sql_add_collab); $stmt_add_collab->bind_param("iis", $active_board_id, $collab_user_id, $permission);
                        if ($stmt_add_collab->execute()) {
                            $message = "User added as a collaborator!"; 
                            if (function_exists('log_activity')) { log_activity($connection, $active_board_id, $user_id, 'collaborator_added', "User {$collab_username} ({$email}) added as collaborator with {$permission} permission."); }
                            $stmt_collaborators_refresh = $connection->prepare($sql_collaborators); 
                            $stmt_collaborators_refresh->bind_param("i", $active_board_id); $stmt_collaborators_refresh->execute(); $result_collaborators_refresh = $stmt_collaborators_refresh->get_result(); $collaborators = []; 
                            while ($collab_item = $result_collaborators_refresh->fetch_assoc()) { $collaborators[] = $collab_item; } $stmt_collaborators_refresh->close();
                        } else { $error = "Error adding collaborator: " . $connection->error; }
                        $stmt_add_collab->close();
                    } else { $error = "This user is already a collaborator."; }
                    $stmt_check_collab->close();
                }
            } else { $error = "User with this email not found."; }
            $stmt_check_user->close();
        } elseif(empty($email)) { $error = "Please enter an email address!"; }
    }
    
    // Remove collaborator
    if (isset($_POST['remove_collaborator'])) {
        $collaboration_id = (int)$_POST['collaboration_id'];
        $sql_get_collab_details = "SELECT u.username, u.email FROM Planotajs_Collaborators c JOIN Planotajs_Users u ON c.user_id = u.user_id WHERE c.collaboration_id = ? AND c.board_id = ?"; 
        $stmt_get_collab = $connection->prepare($sql_get_collab_details); $stmt_get_collab->bind_param("ii", $collaboration_id, $active_board_id); $stmt_get_collab->execute(); $collab_info_log = $stmt_get_collab->get_result()->fetch_assoc(); $stmt_get_collab->close();
        $sql_remove = "DELETE FROM Planotajs_Collaborators WHERE collaboration_id = ? AND board_id = ?"; 
        $stmt_remove = $connection->prepare($sql_remove); $stmt_remove->bind_param("ii", $collaboration_id, $active_board_id);
        if ($stmt_remove->execute()) {
            $message = "Collaborator removed successfully!"; 
            if (function_exists('log_activity') && $collab_info_log) { log_activity($connection, $active_board_id, $user_id, 'collaborator_removed', "Collaborator {$collab_info_log['username']} ({$collab_info_log['email']}) removed."); }
            $stmt_collaborators_refresh = $connection->prepare($sql_collaborators); 
            $stmt_collaborators_refresh->bind_param("i", $active_board_id); $stmt_collaborators_refresh->execute(); $result_collaborators_refresh = $stmt_collaborators_refresh->get_result(); $collaborators = []; 
            while ($collab_item = $result_collaborators_refresh->fetch_assoc()) { $collaborators[] = $collab_item; } $stmt_collaborators_refresh->close();
        } else { $error = "Error removing collaborator: " . $connection->error; }
        $stmt_remove->close();
    }

    // Update collaborator permission
    if (isset($_POST['update_collaborator_permission'])) {
        $collaboration_id_to_update = (int)$_POST['collaboration_id'];
        $new_permission_level = $_POST['new_permission_level'];
        $valid_permissions = ['view', 'edit', 'admin'];

        if (in_array($new_permission_level, $valid_permissions)) {
            $sql_get_collab_user = "SELECT u.username, u.email FROM Planotajs_Collaborators pc JOIN Planotajs_Users u ON pc.user_id = u.user_id WHERE pc.collaboration_id = ? AND pc.board_id = ?";
            $stmt_get_collab_user = $connection->prepare($sql_get_collab_user); $stmt_get_collab_user->bind_param("ii", $collaboration_id_to_update, $active_board_id); $stmt_get_collab_user->execute(); $collab_user_details_log = $stmt_get_collab_user->get_result()->fetch_assoc(); $stmt_get_collab_user->close();

            $sql_update_permission = "UPDATE Planotajs_Collaborators SET permission_level = ? WHERE collaboration_id = ? AND board_id = ? AND board_id IN (SELECT board_id FROM Planotajs_Boards WHERE user_id = ?)";
            $stmt_update_permission = $connection->prepare($sql_update_permission);
            $stmt_update_permission->bind_param("siii", $new_permission_level, $collaboration_id_to_update, $active_board_id, $user_id);

            if ($stmt_update_permission->execute()) {
                if ($stmt_update_permission->affected_rows > 0) {
                    $message = "Collaborator permission updated successfully!";
                    if (function_exists('log_activity') && $collab_user_details_log) { log_activity($connection, $active_board_id, $user_id, 'collaborator_permission_changed', "Permission for collaborator {$collab_user_details_log['username']} ({$collab_user_details_log['email']}) changed to {$new_permission_level}.");}
                } else { $error = "Could not update permission. You might not be the project owner, the collaborator was not found, or the permission was already set to this level."; }
                $stmt_collaborators_refresh = $connection->prepare($sql_collaborators); 
                $stmt_collaborators_refresh->bind_param("i", $active_board_id); $stmt_collaborators_refresh->execute(); $result_collaborators_refresh = $stmt_collaborators_refresh->get_result(); $collaborators = []; 
                while ($collab_item = $result_collaborators_refresh->fetch_assoc()) { $collaborators[] = $collab_item; } $stmt_collaborators_refresh->close();
            } else { $error = "Error updating collaborator permission: " . $connection->error; }
            $stmt_update_permission->close();
        } else { $error = "Invalid permission level selected."; }
    }

    // Archive Project
    if (isset($_POST['archive_project'])) {
        $sql_archive = "UPDATE Planotajs_Boards SET is_archived = 1, updated_at = CURRENT_TIMESTAMP WHERE board_id = ? AND user_id = ?";
        $stmt_archive = $connection->prepare($sql_archive); $stmt_archive->bind_param("ii", $active_board_id, $user_id);
        if ($stmt_archive->execute()) { $message = "Project archived successfully!"; $board_details['is_archived'] = 1; if (function_exists('log_activity')) { log_activity($connection, $active_board_id, $user_id, 'project_archived', 'Project "' . $board_details['board_name'] . '" was archived.'); }
        } else { $error = "Error archiving project: " . $connection->error; } $stmt_archive->close();
    }

    // Unarchive Project
    if (isset($_POST['unarchive_project'])) {
        $sql_unarchive = "UPDATE Planotajs_Boards SET is_archived = 0, updated_at = CURRENT_TIMESTAMP WHERE board_id = ? AND user_id = ?";
        $stmt_unarchive = $connection->prepare($sql_unarchive); $stmt_unarchive->bind_param("ii", $active_board_id, $user_id);
        if ($stmt_unarchive->execute()) { $message = "Project unarchived successfully!"; $board_details['is_archived'] = 0; if (function_exists('log_activity')) { log_activity($connection, $active_board_id, $user_id, 'project_unarchived', 'Project "' . $board_details['board_name'] . '" was unarchived.'); }
        } else { $error = "Error unarchiving project: " . $connection->error; } $stmt_unarchive->close();
    }

    // Delete Project (Soft Delete)
    if (isset($_POST['delete_project'])) {
        $board_name_to_log = $board_details['board_name'];
        $sql_delete = "UPDATE Planotajs_Boards SET is_deleted = 1, updated_at = CURRENT_TIMESTAMP WHERE board_id = ? AND user_id = ?";
        $stmt_delete = $connection->prepare($sql_delete); $stmt_delete->bind_param("ii", $active_board_id, $user_id);
        if ($stmt_delete->execute()) { if (function_exists('log_activity')) { log_activity($connection, $active_board_id, $user_id, 'project_deleted', 'Project "' . $board_name_to_log . '" was deleted.'); }
            $_SESSION['settings_message'] = "Project '" . htmlspecialchars($board_name_to_log) . "' deleted successfully!"; header("Location: project_settings.php"); exit();
        } else { $error = "Error deleting project: " . $connection->error; } $stmt_delete->close();
    }
}
?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Sidebar with projects list -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold mb-4">My Projects</h2>
        <?php if (empty($boards)): ?>
            <p class="text-gray-500">No projects found. <a href="create_board.php" class="text-blue-600 hover:underline">Create one?</a></p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($boards as $board): ?>
                    <a href="project_settings.php?board_id=<?= $board['board_id'] ?>" 
                       class="block p-3 rounded-lg hover:bg-gray-100 <?= ($board['board_id'] == $active_board_id) ? 'bg-gray-100 border-l-4 border-[#e63946]' : '' ?> <?= ($board['is_archived'] ?? 0) ? 'opacity-60' : '' ?>">
                        <?= htmlspecialchars($board['board_name']) ?>
                        <?php if ($board['is_archived'] ?? 0): ?><span class="text-xs text-gray-500 ml-1">(Archived)</span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="mt-6"><a href="kanban.php" class="text-blue-600 hover:underline flex items-center gap-2"><i class="fas fa-arrow-left"></i> Back to Kanban</a></div>
    </div>
    
    <!-- Main settings content -->
    <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-3">
        <?php if (empty($board_details)): ?>
            <div class="text-center py-8">
                <i class="fas fa-project-diagram text-4xl text-gray-400 mb-4"></i>
                <?php if (empty($boards)): ?>
                    <h3 class="text-xl font-semibold text-gray-600">No projects yet</h3>
                    <p class="text-gray-500 mt-2">Go to the <a href="kanban.php" class="text-blue-600 hover:underline">Kanban view</a> to create your first project.</p>
                <?php else: ?>
                    <h3 class="text-xl font-semibold text-gray-600">Select a project from the left menu</h3>
                    <p class="text-gray-500 mt-2">or create a new project in the <a href="kanban.php" class="text-blue-600 hover:underline">Kanban view</a></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if ($message): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            
            <div class="mb-6 border-b"><ul class="flex flex-wrap -mb-px" id="settings-tabs-nav">
                <li class="mr-2"><a href="#general" class="inline-block py-2 px-4 border-b-2 border-[#e63946] text-[#e63946] font-medium" onclick="showTab('general', this); return false;">General Settings</a></li>
                <li class="mr-2"><a href="#collaborators" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('collaborators', this); return false;">Collaborators</a></li>
                <li class="mr-2"><a href="#notifications" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('notifications', this); return false;">Notifications</a></li>
                <li class="mr-2"><a href="#advanced" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('advanced', this); return false;">Advanced Settings</a></li>
                <li class="mr-2"><a href="#activity" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('activity', this); return false;">Activity Log</a></li>
            </ul></div>
            
            <div id="general-tab" class="settings-tab">
                <h2 class="text-xl font-bold mb-4">Project Settings for "<?= htmlspecialchars($board_details['board_name']) ?>"</h2>
                <?php if ($board_details['is_archived']): ?><div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700"><p><i class="fas fa-archive mr-2"></i>This project is currently archived. Some functionalities might be limited.</p></div><?php endif; ?>
                <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>">
                    <div class="mb-4"><label for="board_name" class="block text-gray-700 font-medium mb-2">Project Name</label><input type="text" id="board_name" name="board_name" value="<?= htmlspecialchars($board_details['board_name'] ?? '') ?>" required <?= ($board_details['is_archived'] ?? 0) ? 'disabled' : '' ?> class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946] <?= ($board_details['is_archived'] ?? 0) ? 'bg-gray-100 cursor-not-allowed' : '' ?>"></div>
                    <div class="mb-4"><label for="board_description" class="block text-gray-700 font-medium mb-2">Description</label><textarea id="board_description" name="board_description" rows="4" <?= ($board_details['is_archived'] ?? 0) ? 'disabled' : '' ?> class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946] <?= ($board_details['is_archived'] ?? 0) ? 'bg-gray-100 cursor-not-allowed' : '' ?>"><?= htmlspecialchars($board_details['board_description'] ?? '') ?></textarea></div>
                    <div class="mb-4"><label for="project_tags" class="block text-gray-700 font-medium mb-2">Tags (comma-separated)</label><input type="text" id="project_tags" name="project_tags" value="<?= htmlspecialchars($board_details['tags'] ?? '') ?>" placeholder="e.g., work, personal, urgent" <?= ($board_details['is_archived'] ?? 0) ? 'disabled' : '' ?> class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946] <?= ($board_details['is_archived'] ?? 0) ? 'bg-gray-100 cursor-not-allowed' : '' ?>"></div>
                    <div class="flex justify-end"><button type="submit" name="update_project" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700 <?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= ($board_details['is_archived'] ?? 0) ? 'disabled' : '' ?>>Save Changes</button></div>
                </form>
            </div>
            
            <div id="collaborators-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Collaborators</h2>
                <?php if ($board_details['is_archived']): ?><div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700"><p><i class="fas fa-info-circle mr-2"></i>Collaborator management is disabled for archived projects.</p></div><?php endif; ?>
                <div class="mb-8 p-4 border rounded-lg bg-gray-50 <?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <h3 class="text-lg font-semibold mb-3">Add Collaborator</h3>
                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" class="flex flex-wrap gap-3 items-end">
                        <div class="flex-grow min-w-[200px]"><label for="collaborator_email" class="block text-gray-700 font-medium mb-2">Email Address</label><input type="email" id="collaborator_email" name="collaborator_email" placeholder="user@example.com" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"></div>
                        <div class="min-w-[150px]"><label for="permission_level" class="block text-gray-700 font-medium mb-2">Permission Level</label><select id="permission_level" name="permission_level" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"><option value="view">View</option><option value="edit">Edit</option><option value="admin">Admin</option></select></div>
                        <div><button type="submit" name="add_collaborator" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">Add</button></div>
                    </form>
                </div>
                <div class="<?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <div class="flex justify-between items-center mb-4"><h3 class="text-lg font-semibold">Current Collaborators</h3><button onclick="openInvitationModal()" class="bg-[#e63946] text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2"><i class="fas fa-user-plus"></i> Invite</button></div>
                    <?php if (empty($collaborators)): ?>
                        <p class="text-gray-500">No collaborators added yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border">
                                <thead><tr class="bg-gray-100"><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Username</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Email</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Permission</th><th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($collaborators as $collab): ?>
                                        <tr class="border-t hover:bg-gray-50">
                                            <td class="py-3 px-4"><?= htmlspecialchars($collab['username']) ?></td>
                                            <td class="py-3 px-4"><?= htmlspecialchars($collab['email']) ?></td>
                                            <td class="py-3 px-4">
                                                <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" class="flex items-center gap-2">
                                                    <input type="hidden" name="collaboration_id" value="<?= $collab['collaboration_id'] ?>">
                                                    <select name="new_permission_level" class="px-2 py-1 border rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-[#e63946] w-auto">
                                                        <option value="view" <?= ($collab['permission_level'] == 'view') ? 'selected' : '' ?>>View</option>
                                                        <option value="edit" <?= ($collab['permission_level'] == 'edit') ? 'selected' : '' ?>>Edit</option>
                                                        <option value="admin" <?= ($collab['permission_level'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                                    </select>
                                                    <button type="submit" name="update_collaborator_permission" class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">Save</button>
                                                </form>
                                            </td>
                                            <td class="py-3 px-4 text-right">
                                                <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" onsubmit="return confirm('Are you sure you want to remove this collaborator?');" style="display: inline;">
                                                    <input type="hidden" name="collaboration_id" value="<?= $collab['collaboration_id'] ?>">
                                                    <button type="submit" name="remove_collaborator" class="text-red-600 hover:underline text-sm">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="invitationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-auto">
                        <div class="flex justify-between items-center border-b px-6 py-4"><h3 class="text-xl font-bold">Invite Collaborator</h3><button onclick="closeInvitationModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button></div>
                        <form id="sendInvitationForm" method="post" action="contents/send_invitation.php" class="p-6">
                            <input type="hidden" name="board_id" value="<?= $active_board_id ?>"><input type="hidden" name="board_name" value="<?= htmlspecialchars($board_details['board_name'] ?? '') ?>">
                            <div class="mb-4"><label for="invitation_email" class="block text-gray-700 font-medium mb-2">Email Address</label><input type="email" id="invitation_email" name="email" required placeholder="user@example.com" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"></div>
                            <div class="mb-4"><label for="invitation_permission_level" class="block text-gray-700 font-medium mb-2">Permission Level</label><select id="invitation_permission_level" name="permission_level" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"><option value="view">View</option><option value="edit">Edit</option><option value="admin">Admin</option></select></div>
                            <div class="mb-4"><label for="custom_message" class="block text-gray-700 font-medium mb-2">Personal Message (Optional)</label><textarea id="custom_message" name="custom_message" rows="3" placeholder="Add a personal message..." class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"></textarea></div>
                            <div class="mt-6 flex justify-end"><button type="button" onclick="closeInvitationModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg mr-3">Cancel</button><button type="submit" name="send_invitation" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">Send Invitation</button></div>
                            <div id="invitationStatus" class="mt-4"></div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Tab -->
            <div id="notifications-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Notification Settings</h2>
                <?php if ($board_details['is_archived']): ?>
                    <div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700">
                        <p><i class="fas fa-info-circle mr-2"></i>Notification settings are disabled for archived projects.</p>
                    </div>
                <?php endif; ?>
                <div class="<?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>">
                        <input type="hidden" name="update_notifications_settings" value="1"> <!-- Placeholder for actual handling -->
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="notify_task_assignment" name="notify_task_assignment" class="mr-3 h-5 w-5 text-[#e63946] focus:ring-[#e63946] border-gray-300 rounded" checked>
                                <label for="notify_task_assignment" class="text-gray-700">New task assigned</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="notify_task_status" name="notify_task_status" class="mr-3 h-5 w-5 text-[#e63946] focus:ring-[#e63946] border-gray-300 rounded" checked>
                                <label for="notify_task_status" class="text-gray-700">Task status changed</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="notify_comments" name="notify_comments" class="mr-3 h-5 w-5 text-[#e63946] focus:ring-[#e63946] border-gray-300 rounded" checked>
                                <label for="notify_comments" class="text-gray-700">New comments</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="notify_deadline" name="notify_deadline" class="mr-3 h-5 w-5 text-[#e63946] focus:ring-[#e63946] border-gray-300 rounded" checked>
                                <label for="notify_deadline" class="text-gray-700">Approaching deadline</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="notify_collaborator" name="notify_collaborator" class="mr-3 h-5 w-5 text-[#e63946] focus:ring-[#e63946] border-gray-300 rounded" checked>
                                <label for="notify_collaborator" class="text-gray-700">New collaborator added</label>
                            </div>
                        </div>
                        
                        <div class="mt-8">
                            <h3 class="font-semibold mb-3 text-lg">Notification Channels</h3>
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <input type="checkbox" id="channel_email" name="channel_email" class="mr-3 h-5 w-5 text-[#e63946] focus:ring-[#e63946] border-gray-300 rounded" checked>
                                    <label for="channel_email" class="text-gray-700">Email</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="channel_app" name="channel_app" class="mr-3 h-5 w-5 text-[#e63946] focus:ring-[#e63946] border-gray-300 rounded" checked>
                                    <label for="channel_app" class="text-gray-700">In-app notifications</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-8">
                            <button type="submit" name="update_notifications" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">
                                Save Notification Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="advanced-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Advanced Settings</h2>
                <div class="mb-8 p-4 border rounded-lg <?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>"><h3 class="text-lg font-semibold mb-3">Export Project Data</h3><p class="text-gray-500 mb-3">Export functionality is not yet implemented.</p><div class="flex flex-wrap gap-3"><button disabled class="bg-gray-200 text-gray-400 px-4 py-2 rounded-lg flex items-center gap-2 cursor-not-allowed"><i class="fas fa-file-excel"></i> Export to Excel</button> <button disabled class="bg-gray-200 text-gray-400 px-4 py-2 rounded-lg flex items-center gap-2 cursor-not-allowed"><i class="fas fa-file-csv"></i> Export to CSV</button> <button disabled class="bg-gray-200 text-gray-400 px-4 py-2 rounded-lg flex items-center gap-2 cursor-not-allowed"><i class="fas fa-file-pdf"></i> Export to PDF</button></div></div>
                <div class="mb-8 p-4 border rounded-lg <?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>"><h3 class="text-lg font-semibold mb-3">Integrations</h3><p class="text-gray-500 mb-3">Integrations are not yet implemented.</p><div class="flex flex-wrap gap-3"><button disabled class="bg-gray-200 text-gray-400 px-4 py-2 rounded-lg flex items-center gap-2 cursor-not-allowed"><i class="fab fa-google"></i> Connect Google Calendar</button> <button disabled class="bg-gray-200 text-gray-400 px-4 py-2 rounded-lg flex items-center gap-2 cursor-not-allowed"><i class="fab fa-slack"></i> Connect Slack</button> <button disabled class="bg-gray-200 text-gray-400 px-4 py-2 rounded-lg flex items-center gap-2 cursor-not-allowed"><i class="fab fa-github"></i> Connect GitHub</button></div></div>
                <div class="p-4 border border-red-300 rounded-lg bg-red-50"><h3 class="text-lg font-semibold mb-3 text-red-700">Danger Zone</h3><p class="text-gray-700 mb-4">These actions may be irreversible. Please proceed with caution.</p><div class="flex flex-wrap gap-3">
                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" onsubmit="return confirm('Are you sure you want to <?= ($board_details['is_archived'] ?? 0) ? 'unarchive' : 'archive' ?> this project?');" class="inline-block"><?php if ($board_details['is_archived'] ?? 0): ?><button type="submit" name="unarchive_project" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center gap-2"><i class="fas fa-undo"></i> Unarchive Project</button><?php else: ?><button type="submit" name="archive_project" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg flex items-center gap-2"><i class="fas fa-archive"></i> Archive Project</button><?php endif; ?></form>
                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" onsubmit="return confirm('Are you absolutely sure you want to delete this project? This action cannot be undone and will remove all associated data.');" class="inline-block"><button type="submit" name="delete_project" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center gap-2" <?= ($board_details['is_archived'] ?? 0) ? 'disabled title="Unarchive project first to delete"' : '' ?>><i class="fas fa-trash-alt"></i> Delete Project</button></form>
                </div><?php if ($board_details['is_archived'] ?? 0): ?><p class="text-sm text-yellow-700 mt-2">Note: To delete this project, you must unarchive it first.</p><?php endif; ?></div>
            </div>

            <div id="activity-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Project Activity Log</h2>
                <div class="mb-4 flex flex-wrap justify-between items-center gap-3"><div><label for="activity-filter" class="sr-only">Filter activities</label><select id="activity-filter" class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"><option value="all">All Activities</option><option value="task">Tasks</option><option value="collaborator">Collaborators</option><option value="settings">Settings</option><option value="project">Project Status</option></select></div><div><button class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg flex items-center gap-2"><i class="fas fa-calendar-alt"></i> Filter by Date</button></div></div>
                <?php
                $sql_create_activity = "CREATE TABLE IF NOT EXISTS Planotajs_ActivityLog (activity_id INT AUTO_INCREMENT PRIMARY KEY, board_id INT NOT NULL, user_id INT NOT NULL, activity_type VARCHAR(50) NOT NULL, activity_description TEXT NOT NULL, related_entity_id INT NULL, related_entity_type VARCHAR(50) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (board_id) REFERENCES Planotajs_Boards(board_id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES Planotajs_Users(user_id) ON DELETE CASCADE)"; $connection->query($sql_create_activity);
                $activities = []; if ($active_board_id > 0) { $sql_activities = "SELECT a.*, u.username FROM Planotajs_ActivityLog a JOIN Planotajs_Users u ON a.user_id = u.user_id WHERE a.board_id = ? ORDER BY a.created_at DESC LIMIT 50"; $stmt_activities = $connection->prepare($sql_activities); $stmt_activities->bind_param("i", $active_board_id); $stmt_activities->execute(); $result_activities = $stmt_activities->get_result(); while ($activity = $result_activities->fetch_assoc()) { $activities[] = $activity; } $stmt_activities->close(); }
                ?>
                <div class="bg-white border rounded-lg overflow-hidden">
                    <?php if (empty($activities)): ?><div class="p-8 text-center"><i class="far fa-clock text-4xl text-gray-300 mb-3"></i><p class="text-gray-500">No activity recorded for this project yet.</p></div>
                    <?php else: ?><div class="divide-y divide-gray-200"><?php foreach ($activities as $activity): ?><?php
                        $icon_class = 'fas fa-info-circle text-blue-500'; $activity_type_key = strtolower($activity['activity_type']);
                        $icon_map = ['task_created' => 'fas fa-plus-circle text-green-500', 'task_updated' => 'fas fa-edit text-blue-500', 'task_deleted' => 'fas fa-trash-alt text-red-500', 'task_moved'   => 'fas fa-arrows-alt text-indigo-500', 'task_completed' => 'fas fa-check-circle text-green-600', 'task_reopened'  => 'fas fa-undo text-yellow-500', 'comment_added'  => 'fas fa-comment text-gray-500', 'collaborator_added' => 'fas fa-user-plus text-purple-500', 'collaborator_removed' => 'fas fa-user-minus text-orange-500', 'collaborator_permission_changed' => 'fas fa-user-shield text-teal-500', 'settings_updated' => 'fas fa-cog text-gray-600', 'board_created' => 'fas fa-chalkboard text-pink-500', 'project_archived' => 'fas fa-archive text-yellow-600', 'project_unarchived' => 'fas fa-undo text-green-600', 'project_deleted' => 'fas fa-trash-alt text-red-700'];
                        if (array_key_exists($activity_type_key, $icon_map)) { $icon_class = $icon_map[$activity_type_key]; }
                        $activity_date = new DateTime($activity['created_at'], new DateTimeZone('UTC')); $activity_date->setTimezone(new DateTimeZone(date_default_timezone_get())); $formatted_date = $activity_date->format('M d, Y H:i');
                        ?><div class="p-4 hover:bg-gray-50 flex items-start" data-activity-type="<?= htmlspecialchars($activity['activity_type']) ?>" data-activity-category="<?= explode('_', $activity['activity_type'])[0] ?>"><div class="mr-4 mt-1"><i class="<?= $icon_class ?> text-lg"></i></div><div class="flex-grow"><div class="flex justify-between items-start"><div><span class="font-semibold text-gray-800"><?= htmlspecialchars($activity['username']) ?></span><span class="text-gray-600 ml-1"><?= htmlspecialchars($activity['activity_description']) ?></span></div><div class="text-xs text-gray-500 whitespace-nowrap ml-2"><?= $formatted_date ?></div></div><?php if ($activity['related_entity_type'] == 'task' && $activity['related_entity_id']): ?><div class="mt-1"><a href="kanban.php?board_id=<?= $active_board_id ?>&task_id=<?= $activity['related_entity_id'] ?>" class="text-blue-600 hover:underline text-sm">View Task</a></div><?php endif; ?></div></div><?php endforeach; ?></div><?php endif; ?>
                </div><?php if (count($activities) >= 50): ?><div class="mt-6 text-center"><button class="text-blue-600 hover:underline">Load More Activities</button></div><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Link to the external JavaScript file -->
<!-- This path is relative to the main page (project_settings.php in authenticated-view/) -->
<script src="js/project_settings.js" defer></script>