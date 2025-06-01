<?php
// project_settings_content.php (NEW PHP LOGIC)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: core/login.php");
    exit();
}

require_once '../admin/database/connection.php';
require_once 'core/functions.php'; // Ensure log_and_notify is here for logging actions

$user_id = $_SESSION['user_id']; // Logged-in user
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

// --- Get Owned Boards (My Projects) ---
$sql_owned_boards = "SELECT board_id, board_name, is_archived
                     FROM Planner_Boards 
                     WHERE user_id = ? AND is_deleted = 0
                     ORDER BY board_name ASC";
$stmt_owned_boards = $connection->prepare($sql_owned_boards);
$stmt_owned_boards->bind_param("i", $user_id);
$stmt_owned_boards->execute();
$result_owned_boards = $stmt_owned_boards->get_result();
$owned_boards = [];
while ($board = $result_owned_boards->fetch_assoc()) {
    $owned_boards[] = $board;
}
$stmt_owned_boards->close();

// --- Get Shared Boards (Projects Shared With Me) ---
$sql_shared_boards = "SELECT b.board_id, b.board_name, b.is_archived, c.permission_level
                      FROM Planner_Boards b
                      JOIN Planner_Collaborators c ON b.board_id = c.board_id
                      WHERE c.user_id = ? AND b.user_id != ? AND b.is_deleted = 0
                      ORDER BY b.board_name ASC";
$stmt_shared_boards = $connection->prepare($sql_shared_boards);
$stmt_shared_boards->bind_param("ii", $user_id, $user_id);
$stmt_shared_boards->execute();
$result_shared_boards = $stmt_shared_boards->get_result();
$shared_boards = [];
while ($board = $result_shared_boards->fetch_assoc()) {
    $shared_boards[] = $board;
}
$stmt_shared_boards->close();

// --- Handle active board/project selection ---
$active_board_id = isset($_GET['board_id']) ? (int)$_GET['board_id'] : 0;
if ($active_board_id == 0) {
    if (!empty($owned_boards)) {
        $active_board_id = $owned_boards[0]['board_id'];
    } elseif (!empty($shared_boards)) {
        $active_board_id = $shared_boards[0]['board_id'];
    }
}

$board_details = null;
$current_user_permission_on_active_board = null; // 'owner', 'admin', 'edit', 'view', or null

$log_access_admin_setting = true;  // Default admin to true
$log_access_edit_setting  = false; // Default edit to false
$log_access_view_setting  = false; // Default view to false

if ($active_board_id > 0) {
    // Fetch board details, including 'activity_log_permissions'
    $sql_details = "SELECT * FROM Planner_Boards WHERE board_id = ? AND is_deleted = 0";
    $stmt_details = $connection->prepare($sql_details);
    
    if ($stmt_details) {
        $stmt_details->bind_param("i", $active_board_id);
        $stmt_details->execute();
        $result_details = $stmt_details->get_result();
        $board_details = $result_details->fetch_assoc(); // $board_details is populated here
        $stmt_details->close();
    } else {
        error_log("Error preparing board details SQL: " . $connection->error);
        if (!$error) $error = "Error fetching project details.";
    }

    if ($board_details) {
        // Determine current user's permission on this specific board
        if ($board_details['user_id'] == $user_id) {
            $current_user_permission_on_active_board = 'owner';
        } else {
            $sql_collab_perm = "SELECT permission_level FROM Planner_Collaborators WHERE board_id = ? AND user_id = ?";
            $stmt_collab_perm = $connection->prepare($sql_collab_perm);
            if($stmt_collab_perm){
                $stmt_collab_perm->bind_param("ii", $active_board_id, $user_id);
                $stmt_collab_perm->execute();
                $result_collab_perm = $stmt_collab_perm->get_result();
                if ($collab_perm_row = $result_collab_perm->fetch_assoc()) {
                    $current_user_permission_on_active_board = $collab_perm_row['permission_level'];
                }
                $stmt_collab_perm->close();
            } else {
                 error_log("Error preparing collab permission SQL: " . $connection->error);
            }
        }

        if ($current_user_permission_on_active_board === null) {
            $board_details = null; 
            if (!$error) $error = "You do not have permission to access settings for this project.";
        } else {
            // Board details are valid, and user has permission. Now parse log settings.
            $log_perms_from_db = json_decode($board_details['activity_log_permissions'] ?? '', true);
            
            if (is_array($log_perms_from_db)) {
                // Use values from DB if present, otherwise stick to the initial defaults
                $log_access_admin_setting = $log_perms_from_db['admin'] ?? true;
                $log_access_edit_setting  = $log_perms_from_db['edit']  ?? false;
                $log_access_view_setting  = $log_perms_from_db['view']  ?? false;
            } else {
                // JSON was null or invalid, stick to the initial defaults set before this if block
            }
        }
    } else {
      if (!$error && $active_board_id > 0) $error = "The selected project could not be found or has been deleted.";
    }
}


// --- Notification Settings Logic ---
$notification_settings = [];
$default_event_notification_settings = [
    'notify_task_created' => 1, 'notify_task_assignment' => 1,
    'notify_task_status_changed' => 1, 'notify_task_updated' => 1,
    'notify_task_deleted' => 1, 'notify_new_comment' => 1,
    'notify_deadline_reminder' => 1, 'notify_collaborator_added' => 1,
    'notify_new_chat_message' => 1, 'notify_column_changes' => 0,
    'notify_project_management' => 1,
];
$default_channel_settings = ['channel_app' => 1, 'channel_email' => 0];

if ($active_board_id > 0 && $board_details && $current_user_permission_on_active_board) {
    $current_settings_from_db = [];
    $sql_notif_settings = "SELECT * FROM Planner_NotificationSettings WHERE user_id = ? AND board_id = ?";
    $stmt_notif_settings = $connection->prepare($sql_notif_settings);
    if ($stmt_notif_settings) {
        $stmt_notif_settings->bind_param("ii", $user_id, $active_board_id);
        $stmt_notif_settings->execute();
        $result_notif_settings = $stmt_notif_settings->get_result();
        if ($result_notif_settings->num_rows > 0) {
            $current_settings_from_db = $result_notif_settings->fetch_assoc();
            $current_settings_from_db['source'] = 'board_specific';
        } else {
            $sql_global_notif_settings = "SELECT * FROM Planner_NotificationSettings WHERE user_id = ? AND board_id IS NULL";
            $stmt_global_notif_settings = $connection->prepare($sql_global_notif_settings);
            if ($stmt_global_notif_settings) {
                $stmt_global_notif_settings->bind_param("i", $user_id);
                $stmt_global_notif_settings->execute();
                $result_global_notif_settings = $stmt_global_notif_settings->get_result();
                if ($result_global_notif_settings->num_rows > 0) {
                    $current_settings_from_db = $result_global_notif_settings->fetch_assoc();
                    $current_settings_from_db['source'] = 'global';
                } else {
                    $current_settings_from_db['source'] = 'default';
                }
                $stmt_global_notif_settings->close();
            } else { if(!$error) $error = "Error preparing global notification settings."; $current_settings_from_db['source'] = 'default'; }
        }
        $stmt_notif_settings->close();
    } else { if(!$error) $error = "Error preparing board notification settings."; $current_settings_from_db['source'] = 'default';}
    
    $notification_settings = array_merge(
        $default_event_notification_settings, 
        $default_channel_settings, 
        $current_settings_from_db 
    );
    if (isset($current_settings_from_db['source'])) {
        $notification_settings['source'] = $current_settings_from_db['source'];
    }
} else {
    $notification_settings = array_merge($default_event_notification_settings, $default_channel_settings);
    $notification_settings['source'] = 'default_no_board';
}

// Get collaborators for the active board (owner or admin collaborator can see this list)
$collaborators = [];
if ($active_board_id > 0 && $board_details && ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin')) {
    $sql_create_table_collab = "CREATE TABLE IF NOT EXISTS Planner_Collaborators (
        collaboration_id INT AUTO_INCREMENT PRIMARY KEY,
        board_id INT NOT NULL,
        user_id INT NOT NULL,
        permission_level ENUM('view', 'edit', 'admin') NOT NULL DEFAULT 'view',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (board_id) REFERENCES Planner_Boards(board_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES Planner_Users(user_id) ON DELETE CASCADE,
        UNIQUE KEY (board_id, user_id)
    )";
    $connection->query($sql_create_table_collab);
    
    $sql_collaborators = "SELECT c.collaboration_id, c.user_id as collaborator_user_id, c.permission_level, u.username, u.email
                         FROM Planner_Collaborators c
                         JOIN Planner_Users u ON c.user_id = u.user_id
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

// --- Process form submissions ---
$redirect_to_hash = "#general"; // Default hash

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $active_board_id > 0 && $board_details && $current_user_permission_on_active_board) {
    
    // Update project general settings
    if (isset($_POST['update_project'])) {
        $redirect_to_hash = "#general";
        if ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin') {
            $board_name = trim($_POST['board_name']);
            $board_description = trim($_POST['board_description']);
            $project_tags = isset($_POST['project_tags']) ? trim($_POST['project_tags']) : ($board_details['tags'] ?? '');
            
            if (!empty($board_name)) {
                $sql_update = "UPDATE Planner_Boards SET 
                               board_name = ?, board_description = ?, tags = ?, updated_at = CURRENT_TIMESTAMP
                               WHERE board_id = ? AND user_id = ?"; 
                $stmt_update = $connection->prepare($sql_update);
                $stmt_update->bind_param("sssii", $board_name, $board_description, $project_tags, $active_board_id, $board_details['user_id']); 
                
                if ($stmt_update->execute()) {
                    $message = "Project settings updated successfully!";
                    log_and_notify($connection, $active_board_id, $user_id, 'settings_updated', "Project settings for \"{$board_name}\" were updated by " . $_SESSION['username'] . ".", null, null, get_board_associated_user_ids($connection, $active_board_id));
                    $sql_details_refresh = "SELECT * FROM Planner_Boards WHERE board_id = ? AND is_deleted = 0";
                    $stmt_details_refresh = $connection->prepare($sql_details_refresh);
                    $stmt_details_refresh->bind_param("i", $active_board_id);
                    $stmt_details_refresh->execute();
                    $board_details = $stmt_details_refresh->get_result()->fetch_assoc();
                    $stmt_details_refresh->close();
                } else { $error = "Error updating project settings: " . $connection->error; }
                $stmt_update->close();
            } else { $error = "Project name cannot be empty!"; }
        } else { $error = "You do not have permission to update general project settings."; }
    }
    
    // Remove collaborator
    if (isset($_POST['remove_collaborator'])) {
        $redirect_to_hash = "#collaborators";
        if ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin') {
            $collaboration_id_to_remove = (int)$_POST['collaboration_id'];
            
            $sql_get_target_collab = "SELECT c.user_id as target_user_id, c.permission_level as target_permission_level, u.username as target_username, u.email as target_email
                                      FROM Planner_Collaborators c
                                      JOIN Planner_Users u ON c.user_id = u.user_id
                                      WHERE c.collaboration_id = ? AND c.board_id = ?";
            $stmt_get_target = $connection->prepare($sql_get_target_collab);
            $stmt_get_target->bind_param("ii", $collaboration_id_to_remove, $active_board_id);
            $stmt_get_target->execute();
            $target_collab_details = $stmt_get_target->get_result()->fetch_assoc();
            $stmt_get_target->close();

            $can_remove = false;
            if ($target_collab_details) {
                if ($current_user_permission_on_active_board == 'owner') {
                    if ($target_collab_details['target_user_id'] != $board_details['user_id']) { 
                        $can_remove = true;
                    } else { $error = "The project owner cannot be removed as a collaborator."; }
                } elseif ($current_user_permission_on_active_board == 'admin') {
                    if ($target_collab_details['target_user_id'] == $board_details['user_id']) {
                        $error = "Admin collaborators cannot remove the project owner.";
                    } elseif ($target_collab_details['target_permission_level'] == 'admin') {
                        $error = "Admin collaborators cannot remove other admin collaborators.";
                    } elseif ($target_collab_details['target_user_id'] == $user_id) {
                         $error = "You cannot remove yourself as a collaborator. Ask the project owner.";
                    } else { 
                        $can_remove = true;
                    }
                }
            } else { $error = "Collaborator not found for removal."; }

            if ($can_remove) {
                $sql_remove = "DELETE FROM Planner_Collaborators WHERE collaboration_id = ? AND board_id = ?"; 
                $stmt_remove = $connection->prepare($sql_remove); 
                $stmt_remove->bind_param("ii", $collaboration_id_to_remove, $active_board_id);
                if ($stmt_remove->execute()) {
                    $message = "Collaborator " . htmlspecialchars($target_collab_details['target_username']) . " removed successfully!"; 
                    log_and_notify($connection, $active_board_id, $user_id, 'collaborator_removed', "Collaborator {$target_collab_details['target_username']} ({$target_collab_details['target_email']}) was removed by " . $_SESSION['username'] . ".", $target_collab_details['target_user_id'], 'user', get_board_associated_user_ids($connection, $active_board_id));
                } else { $error = "Error removing collaborator: " . $connection->error; }
                $stmt_remove->close();
            } elseif (!$error) { $error = "You do not have permission to remove this collaborator."; }
        } else { $error = "You do not have permission to manage collaborators."; }
    }

    // Update collaborator permission
    if (isset($_POST['update_collaborator_permission'])) {
        $redirect_to_hash = "#collaborators";
        if ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin') {
            $collaboration_id_to_update = (int)$_POST['collaboration_id'];
            $new_permission_level = $_POST['new_permission_level'];
            $valid_permissions = ['view', 'edit', 'admin'];

            if (in_array($new_permission_level, $valid_permissions)) {
                $sql_get_target_collab_perm = "SELECT c.user_id as target_user_id, c.permission_level as current_target_permission, u.username as target_username, u.email as target_email
                                           FROM Planner_Collaborators c
                                           JOIN Planner_Users u ON c.user_id = u.user_id
                                           WHERE c.collaboration_id = ? AND c.board_id = ?";
                $stmt_get_target_perm = $connection->prepare($sql_get_target_collab_perm);
                $stmt_get_target_perm->bind_param("ii", $collaboration_id_to_update, $active_board_id);
                $stmt_get_target_perm->execute();
                $target_collab_details_perm = $stmt_get_target_perm->get_result()->fetch_assoc();
                $stmt_get_target_perm->close();

                $can_update_permission = false;
                if ($target_collab_details_perm) {
                    if ($current_user_permission_on_active_board == 'owner') {
                        if ($target_collab_details_perm['target_user_id'] != $board_details['user_id']) {
                            $can_update_permission = true;
                        } else { $error = "The project owner's permission cannot be changed this way."; }
                    } elseif ($current_user_permission_on_active_board == 'admin') {
                        if ($target_collab_details_perm['target_user_id'] == $board_details['user_id']) {
                            $error = "Admin collaborators cannot change the project owner's permission.";
                        } elseif ($target_collab_details_perm['current_target_permission'] == 'admin' || $new_permission_level == 'admin') {
                            $error = "Admin collaborators cannot manage other admin permissions or promote to admin.";
                        } elseif ($target_collab_details_perm['target_user_id'] == $user_id && $new_permission_level != $target_collab_details_perm['current_target_permission']) {
                            $error = "You cannot change your own permission level.";
                        } elseif (in_array($new_permission_level, ['view', 'edit'])) {
                            $can_update_permission = true;
                        }
                    }
                } else { $error = "Collaborator not found for permission update."; }

                if ($can_update_permission) {
                    $sql_update_permission = "UPDATE Planner_Collaborators SET permission_level = ? WHERE collaboration_id = ? AND board_id = ?";
                    $stmt_update_permission_db = $connection->prepare($sql_update_permission);
                    $stmt_update_permission_db->bind_param("sii", $new_permission_level, $collaboration_id_to_update, $active_board_id);
                    if ($stmt_update_permission_db->execute()) {
                        if ($stmt_update_permission_db->affected_rows > 0) {
                            $message = "Collaborator " . htmlspecialchars($target_collab_details_perm['target_username']) . "'s permission updated to {$new_permission_level}!";
                             log_and_notify($connection, $active_board_id, $user_id, 'collaborator_permission_changed', "Permission for collaborator {$target_collab_details_perm['target_username']} ({$target_collab_details_perm['target_email']}) changed to {$new_permission_level} by " . $_SESSION['username'] . ".", $target_collab_details_perm['target_user_id'], 'user', get_board_associated_user_ids($connection, $active_board_id));
                        } else { $error = "Permission not changed (it might be the same as before or collaborator not found)."; }
                    } else { $error = "Error updating collaborator permission: " . $connection->error; }
                    $stmt_update_permission_db->close();
                } elseif (!$error) { $error = "You do not have permission to change this collaborator's permission level or the selected level is invalid for your role."; }
            } else { $error = "Invalid permission level selected."; }
        } else { $error = "You do not have permission to manage collaborators."; }
    }

    // Update Notification Settings
    if (isset($_POST['update_notification_settings_submit'])) {
        $redirect_to_hash = "#notifications";
        $settings_to_update = [];
        $event_notification_fields = [
            'notify_task_created', 'notify_task_assignment', 'notify_task_status_changed',
            'notify_task_updated', 'notify_task_deleted', 'notify_new_comment',
            'notify_deadline_reminder', 'notify_collaborator_added', 'notify_new_chat_message',
            'notify_column_changes', 'notify_project_management',
        ];
        foreach ($event_notification_fields as $field) {
            $settings_to_update[$field] = isset($_POST[$field]) ? 1 : 0;
        }
        $settings_to_update['channel_app'] = 1; 
        $settings_to_update['channel_email'] = 0;

        $sql_upsert_fields_array = array_keys($settings_to_update);
        $sql_upsert_fields_string = "`" . implode("`, `", $sql_upsert_fields_array) . "`";
        $sql_upsert_placeholders = implode(", ", array_fill(0, count($settings_to_update), "?"));
        $sql_update_assignments = [];
        foreach ($sql_upsert_fields_array as $field) { $sql_update_assignments[] = "`$field` = VALUES(`$field`)"; }
        $sql_update_set = implode(", ", $sql_update_assignments);
        $sql_upsert = "INSERT INTO Planner_NotificationSettings (`user_id`, `board_id`, $sql_upsert_fields_string) 
                       VALUES (?, ?, $sql_upsert_placeholders) 
                       ON DUPLICATE KEY UPDATE $sql_update_set, `updated_at` = CURRENT_TIMESTAMP";
        $stmt_upsert = $connection->prepare($sql_upsert);
        if($stmt_upsert) {
            $types = "ii" . str_repeat("i", count($settings_to_update));
            $values_for_bind = array_merge([$user_id, $active_board_id], array_values($settings_to_update));
            $stmt_upsert->bind_param($types, ...$values_for_bind);
            if ($stmt_upsert->execute()) {
                $message = "Your notification settings for this board have been updated!";
            } else { $error = "Error updating notification settings: " . $stmt_upsert->error; }
            $stmt_upsert->close();
        } else { $error = "Error preparing notification settings update: " . $connection->error; }
    }
    
    // --- DANGER ZONE ACTIONS (Archive, Unarchive) ---
    $danger_zone_actions_non_delete = ['archive_project', 'unarchive_project'];
    foreach($danger_zone_actions_non_delete as $action_key) {
        if (isset($_POST[$action_key])) {
            $redirect_to_hash = "#advanced";
            if ($current_user_permission_on_active_board == 'owner') {
                $board_name_to_log = $board_details['board_name']; 
                
                if ($action_key === 'archive_project') {
                    $sql_danger = "UPDATE Planner_Boards SET is_archived = 1, updated_at = CURRENT_TIMESTAMP WHERE board_id = ? AND user_id = ?";
                    $stmt_danger = $connection->prepare($sql_danger); 
                    if ($stmt_danger) {
                        $stmt_danger->bind_param("ii", $active_board_id, $user_id);
                        if ($stmt_danger->execute()) { 
                            $message = "Project archived successfully!"; 
                            log_and_notify($connection, $active_board_id, $user_id, 'project_archived', "Project \"{$board_name_to_log}\" was archived by " . $_SESSION['username'] . ".", null, null, get_board_associated_user_ids($connection, $active_board_id));
                            $board_details['is_archived'] = 1; 
                        } else { $error = "Error archiving project: " . $stmt_danger->error; } 
                        $stmt_danger->close();
                    } else { $error = "Error preparing archive statement: " . $connection->error; }

                } elseif ($action_key === 'unarchive_project') {
                    $sql_danger = "UPDATE Planner_Boards SET is_archived = 0, updated_at = CURRENT_TIMESTAMP WHERE board_id = ? AND user_id = ?";
                    $stmt_danger = $connection->prepare($sql_danger); 
                     if ($stmt_danger) {
                        $stmt_danger->bind_param("ii", $active_board_id, $user_id);
                        if ($stmt_danger->execute()) { 
                            $message = "Project unarchived successfully!"; 
                            log_and_notify($connection, $active_board_id, $user_id, 'project_unarchived', "Project \"{$board_name_to_log}\" was unarchived by " . $_SESSION['username'] . ".", null, null, get_board_associated_user_ids($connection, $active_board_id));
                            $board_details['is_archived'] = 0; 
                        } else { $error = "Error unarchiving project: " . $stmt_danger->error; } 
                        $stmt_danger->close();
                    } else { $error = "Error preparing unarchive statement: " . $connection->error; }
                }
            } else { $error = "Only the project owner can perform this action."; }
            break; 
        }
    }

    // --- Update Activity Log Role Visibility (JSON method) ---
    if (isset($_POST['action_update_log_visibility'])) { 
        $redirect_to_hash = "#advanced"; 
        if ($board_details && $current_user_permission_on_active_board == 'owner' && !($board_details['is_archived'] ?? 0)) {
            
            $submitted_roles = $_POST['log_access_roles'] ?? [];
            
            $new_log_permissions = [
                'admin' => isset($submitted_roles['admin']) ? true : false,
                'edit'  => isset($submitted_roles['edit'])  ? true : false,
                'view'  => isset($submitted_roles['view'])  ? true : false,
            ];
            $json_permissions = json_encode($new_log_permissions);

            $sql_update_visibility = "UPDATE Planner_Boards 
                                      SET activity_log_permissions = ?, updated_at = CURRENT_TIMESTAMP 
                                      WHERE board_id = ? AND user_id = ?";
            $stmt_update_visibility = $connection->prepare($sql_update_visibility);
            if ($stmt_update_visibility) {
                $stmt_update_visibility->bind_param("sii", 
                    $json_permissions, 
                    $active_board_id, 
                    $board_details['user_id'] 
                );
                if ($stmt_update_visibility->execute()) {
                    if ($stmt_update_visibility->affected_rows >= 0) { 
                        $message = "Activity log visibility settings updated.";
                        $board_details['activity_log_permissions'] = $json_permissions; 
                        $log_access_admin_setting = $new_log_permissions['admin'];
                        $log_access_edit_setting  = $new_log_permissions['edit'];
                        $log_access_view_setting  = $new_log_permissions['view'];
                        
                        $log_desc_parts = [];
                        if ($new_log_permissions['admin']) $log_desc_parts[] = "Admin:Yes"; else $log_desc_parts[] = "Admin:No";
                        if ($new_log_permissions['edit']) $log_desc_parts[] = "Edit:Yes"; else $log_desc_parts[] = "Edit:No";
                        if ($new_log_permissions['view']) $log_desc_parts[] = "View:Yes"; else $log_desc_parts[] = "View:No";

                        log_and_notify($connection, $active_board_id, $user_id, 'settings_updated', 
                            "Activity log visibility settings updated by " . $_SESSION['username'] . 
                            " (" . implode(", ", $log_desc_parts) . ")", 
                            null, null, get_board_associated_user_ids($connection, $active_board_id));
                    } else {
                        $error = "Settings may not have been saved (no change detected or error).";
                    }
                } else {
                    $error = "Error updating activity log visibility: " . $stmt_update_visibility->error;
                }
                $stmt_update_visibility->close();
            } else {
                $error = "Error preparing visibility update: " . $connection->error;
            }
        } else {
            if (!$board_details) {
                $error = "Cannot update settings: Board details not found.";
            } elseif ($current_user_permission_on_active_board != 'owner') {
                $error = "You do not have permission to change this setting.";
            } elseif ($board_details['is_archived'] ?? 0) {
                $error = "Cannot change settings for an archived project.";
            }
        }
    }

    // --- HANDLE DELETE PROJECT (from Modal) ---
    if (isset($_POST['delete_project']) && isset($_POST['board_id_to_delete'])) {
        $redirect_to_hash = ""; 
        $board_id_to_actually_delete = (int)$_POST['board_id_to_delete'];
        $confirmation_text_typed = $_POST['confirm_project_name'] ?? '';

        if ($board_id_to_actually_delete === $active_board_id && $current_user_permission_on_active_board == 'owner') {
            if (!($board_details['is_archived'] ?? 0)) { 
                if ($confirmation_text_typed === $board_details['board_name']) {
                    $board_name_to_log = $board_details['board_name'];
                    $sql_delete = "UPDATE Planner_Boards SET is_deleted = 1, updated_at = CURRENT_TIMESTAMP WHERE board_id = ? AND user_id = ?";
                    $stmt_delete = $connection->prepare($sql_delete);
                    if ($stmt_delete) {
                        $stmt_delete->bind_param("ii", $board_id_to_actually_delete, $user_id); 
                        if ($stmt_delete->execute()) {
                            log_and_notify($connection, $board_id_to_actually_delete, $user_id, 'project_deleted', "Project \"{$board_name_to_log}\" was deleted by " . $_SESSION['username'] . ".", null, null, []);
                            $_SESSION['settings_message'] = "Project '" . htmlspecialchars($board_name_to_log) . "' deleted successfully!";
                            header("Location: project_settings.php"); 
                            exit();
                        } else { 
                            $error = "Error deleting project: " . $stmt_delete->error; 
                        }
                        $stmt_delete->close();
                    } else { $error = "Error preparing delete statement: " . $connection->error; }
                } else {
                    $error = "Project name confirmation did not match. Deletion cancelled.";
                    $redirect_to_hash = "#advanced"; 
                }
            } else {
                $error = "Project must be unarchived before deletion.";
                $redirect_to_hash = "#advanced"; 
            }
        } else {
            if ($board_id_to_actually_delete !== $active_board_id) {
                 $error = "Board ID mismatch for deletion. Please try again.";
            } else { 
                 $error = "Only the project owner can delete the project.";
            }
            $redirect_to_hash = "#advanced";
        }
    }

    // --- HANDLE LEAVE PROJECT ---
    if (isset($_POST['leave_project_submit'])) {
        if ($current_user_permission_on_active_board && $current_user_permission_on_active_board !== 'owner') {
            if (!($board_details['is_archived'] ?? 0)) { 
                $stmt_leaver_username = $connection->prepare("SELECT username FROM Planner_Users WHERE user_id = ?");
                $leaver_username = "User ID " . $user_id; 
                if($stmt_leaver_username) {
                    $stmt_leaver_username->bind_param("i", $user_id);
                    $stmt_leaver_username->execute();
                    $res_leaver = $stmt_leaver_username->get_result();
                    if($row_leaver = $res_leaver->fetch_assoc()){
                        $leaver_username = $row_leaver['username'];
                    }
                    $stmt_leaver_username->close();
                }

                $sql_leave = "DELETE FROM Planner_Collaborators WHERE board_id = ? AND user_id = ?";
                $stmt_leave = $connection->prepare($sql_leave);
                if ($stmt_leave) {
                    $stmt_leave->bind_param("ii", $active_board_id, $user_id);
                    if ($stmt_leave->execute()) {
                        if ($stmt_leave->affected_rows > 0) {
                            $message = "You have successfully left the project '" . htmlspecialchars($board_details['board_name']) . "'.";
                            $activity_description = htmlspecialchars($leaver_username) . " left the project \"" . htmlspecialchars($board_details['board_name']) . "\".";
                            $recipients = [$board_details['user_id']]; 
                            log_and_notify( $connection, $active_board_id, $user_id, 'collaborator_left', $activity_description, $user_id, 'user', $recipients );
                            $_SESSION['settings_message'] = $message;
                            header("Location: project_settings.php"); 
                            exit();
                        } else {
                            $error = "Could not leave the project. You might not have been a collaborator or an error occurred.";
                        }
                    } else {
                        $error = "Error leaving project: " . $stmt_leave->error;
                    }
                    $stmt_leave->close();
                } else {
                    $error = "Error preparing to leave project: " . $connection->error;
                }
            } else {
                $error = "Cannot leave an archived project.";
            }
        } else {
            $error = "Only collaborators can leave a project. Owners must transfer ownership or delete the project.";
        }
        $redirect_to_hash = ($current_user_permission_on_active_board && $current_user_permission_on_active_board !== 'owner') ? "#general" : ""; 
    }

    $_SESSION['settings_message'] = $message;
    $_SESSION['settings_error'] = $error;
    header("Location: project_settings.php?board_id=" . $active_board_id . $redirect_to_hash);
    exit();

} // End of POST processing
?>

<!-- HTML part is now IDENTICAL to your original "old functional version" -->
<!-- This ensures that white mode styling relies purely on Tailwind's base styles -->
<!-- and your dark-theme.css will override them when html.dark-mode is active. -->

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Sidebar with projects list -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div>
            <h2 class="text-xl font-bold mb-2">My Projects</h2>
            <?php if (empty($owned_boards)): ?>
                <p class="text-gray-500 text-sm mb-4">No projects created by you. <a href="kanban.php?action=create_board" class="text-blue-600 hover:underline">Create one?</a></p>
            <?php else: ?>
                <div class="space-y-2 mb-4">
                    <?php foreach ($owned_boards as $board_item): ?>
                        <a href="project_settings.php?board_id=<?= $board_item['board_id'] ?>" 
                           class="block p-3 rounded-lg hover:bg-gray-100 <?= ($board_item['board_id'] == $active_board_id) ? 'bg-gray-100 border-l-4 border-[#e63946]' : '' ?> <?= ($board_item['is_archived'] ?? 0) ? 'opacity-60' : '' ?>">
                            <?= htmlspecialchars($board_item['board_name']) ?>
                            <?php if ($board_item['is_archived'] ?? 0): ?><span class="text-xs text-gray-500 ml-1">(Archived)</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <hr class="my-4">
        <div>
            <h2 class="text-xl font-bold mb-2">Projects Shared With Me</h2>
            <?php if (empty($shared_boards)): ?>
                <p class="text-gray-500 text-sm">No projects currently shared with you.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($shared_boards as $board_item): ?>
                        <a href="project_settings.php?board_id=<?= $board_item['board_id'] ?>" 
                           class="block p-3 rounded-lg hover:bg-gray-100 <?= ($board_item['board_id'] == $active_board_id) ? 'bg-gray-100 border-l-4 border-[#e63946]' : '' ?> <?= ($board_item['is_archived'] ?? 0) ? 'opacity-60' : '' ?>">
                            <?= htmlspecialchars($board_item['board_name']) ?>
                            <span class="text-xs text-gray-500 ml-1">(<?= ucfirst(htmlspecialchars($board_item['permission_level'])) ?>)</span>
                            <?php if ($board_item['is_archived'] ?? 0): ?><span class="text-xs text-gray-500 ml-1">(Archived)</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="mt-6"><a href="kanban.php" class="text-blue-600 hover:underline flex items-center gap-2"><i class="fas fa-arrow-left"></i> Back to Kanban</a></div>
    </div>
    
    <!-- Main settings content -->
    <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-3">
        <?php if (empty($active_board_id) && (empty($owned_boards) && empty($shared_boards))): ?>
            <div class="text-center py-8">
                <i class="fas fa-folder-open text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">No Projects Found</h3>
                <p class="text-gray-500 mt-2">You are not part of any projects yet. Go to the <a href="kanban.php" class="text-blue-600 hover:underline">Kanban view</a> to create or join one.</p>
            </div>
        <?php elseif (empty($active_board_id) && (!empty($owned_boards) || !empty($shared_boards))): ?>
             <div class="text-center py-8">
                <i class="fas fa-mouse-pointer text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">Select a project</h3>
                <p class="text-gray-500 mt-2">Choose a project from the sidebar to view or manage its settings.</p>
            </div>
        <?php elseif ($active_board_id > 0 && (empty($board_details) || empty($current_user_permission_on_active_board)) ): ?>
             <div class="text-center py-8">
                <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600">Project Access Error</h3>
                <p class="text-gray-500 mt-2"><?= htmlspecialchars($error ?: "The selected project could not be found or you do not have permission to access its settings.") ?></p>
            </div>
        <?php elseif ($board_details && $current_user_permission_on_active_board): ?>
            <?php if ($message): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="mb-6 border-b border-gray-200">
                <ul class="flex flex-wrap -mb-px" id="settings-tabs-nav">
                    <li class="mr-2"><a href="#general" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('general', this); return false;">General Settings</a></li>
                    
                    <?php if ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin'): ?>
                        <li class="mr-2"><a href="#collaborators" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('collaborators', this); return false;">Collaborators</a></li>
                    <?php endif; ?>
                    
                    <li class="mr-2"><a href="#notifications" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('notifications', this); return false;">Notifications</a></li>
                    
                    <?php
                    $show_activity_log_tab_link = false;
                    if ($current_user_permission_on_active_board == 'owner') {
                        $show_activity_log_tab_link = true;
                    } elseif ($board_details) { 
                        $role_for_tab_check = $current_user_permission_on_active_board;
                        if ($role_for_tab_check == 'admin' && $log_access_admin_setting) {
                            $show_activity_log_tab_link = true;
                        } elseif ($role_for_tab_check == 'edit' && $log_access_edit_setting) {
                            $show_activity_log_tab_link = true;
                        } elseif ($role_for_tab_check == 'view' && $log_access_view_setting) {
                            $show_activity_log_tab_link = true;
                        }
                    }
                    ?>
                    <?php if ($show_activity_log_tab_link): ?>
                         <li class="mr-2"><a href="#activity" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('activity', this); return false;">Activity Log</a></li>
                    <?php endif; ?>

                    <?php if ($current_user_permission_on_active_board == 'owner'): ?>
                        <li class="mr-2"><a href="#advanced" class="inline-block py-2 px-4 text-gray-600 hover:text-[#e63946] font-medium" onclick="showTab('advanced', this); return false;">Advanced Settings</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- General Tab -->
            <div id="general-tab" class="settings-tab">
                <h2 class="text-xl font-bold mb-4">Project Settings for "<?= htmlspecialchars($board_details['board_name']) ?>"</h2>
                <?php if ($board_details['is_archived']): ?><div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700"><p><i class="fas fa-archive mr-2"></i>This project is currently archived. Some functionalities might be limited.</p></div><?php endif; ?>
                
                <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>">
                    <?php $can_edit_general = ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin'); ?>
                    <div class="mb-4"><label for="board_name" class="block text-gray-700 font-medium mb-2">Project Name</label><input type="text" id="board_name" name="board_name" value="<?= htmlspecialchars($board_details['board_name'] ?? '') ?>" required <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'disabled' : '' ?> class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946] <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'bg-gray-100 cursor-not-allowed' : '' ?>"></div>
                    <div class="mb-4"><label for="board_description" class="block text-gray-700 font-medium mb-2">Description</label><textarea id="board_description" name="board_description" rows="4" <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'disabled' : '' ?> class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946] <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'bg-gray-100 cursor-not-allowed' : '' ?>"><?= htmlspecialchars($board_details['board_description'] ?? '') ?></textarea></div>
                    <div class="mb-4"><label for="project_tags" class="block text-gray-700 font-medium mb-2">Tags (comma-separated)</label><input type="text" id="project_tags" name="project_tags" value="<?= htmlspecialchars($board_details['tags'] ?? '') ?>" placeholder="e.g., work, personal, urgent" <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'disabled' : '' ?> class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946] <?= (!$can_edit_general || ($board_details['is_archived'] ?? 0)) ? 'bg-gray-100 cursor-not-allowed' : '' ?>"></div>
                    <?php if ($can_edit_general && !($board_details['is_archived'] ?? 0)): ?>
                    <div class="flex justify-end"><button type="submit" name="update_project" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">Save Changes</button></div>
                    <?php endif; ?>
                </form>

                <?php if ($current_user_permission_on_active_board && $current_user_permission_on_active_board !== 'owner' && !($board_details['is_archived'] ?? 0) ): ?>
                    <hr class="my-8 border-gray-300">
                    <div class="mt-6 p-4 border border-red-300 rounded-lg bg-red-50">
                        <h3 class="text-lg font-semibold mb-2 text-red-700">Leave Project</h3> 
                        <p class="text-sm text-gray-600 mb-3">If you leave this project, you will lose access to its content and will need to be invited again to rejoin.</p>
                        <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" 
                              onsubmit="return confirm('Are you sure you want to leave the project \"<?= htmlspecialchars($board_details['board_name']) ?>\"? You will lose access.');">
                            <input type="hidden" name="action" value="leave_project">
                            <button type="submit" name="leave_project_submit" class="bg-red-500 text-white px-6 py-2 rounded-lg hover:bg-red-600 transition-colors">
                                <i class="fas fa-sign-out-alt mr-2"></i>Leave Project
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Collaborators Tab -->
            <?php if ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin'): ?>
            <div id="collaborators-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Manage Collaborators</h2>
                <?php if ($board_details['is_archived']): ?> <div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700"><p><i class="fas fa-info-circle mr-2"></i>Collaborator management is disabled for archived projects.</p></div> <?php endif; ?>
                
                <div class="mb-8 p-4 border rounded-lg bg-gray-50 <?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <h3 class="text-lg font-semibold mb-3">Invite New Collaborator</h3>
                     <button onclick="openInvitationModal()" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 w-full sm:w-auto justify-center"><i class="fas fa-user-plus"></i> Send Invitation</button>
                </div>
                
                <div class="<?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <h3 class="text-lg font-semibold mb-3">Current Collaborators</h3>
                    <?php if (empty($collaborators)): ?>
                        <p class="text-gray-500">No active collaborators on this project yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border">
                                <thead><tr class="bg-gray-100"><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Username</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Email</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Permission</th><th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($collaborators as $collab): ?>
                                        <?php
                                        $is_this_collaborator_the_board_owner = ($collab['collaborator_user_id'] == $board_details['user_id']);
                                        $can_logged_in_user_manage_this_collaborator = false;
                                        if ($current_user_permission_on_active_board == 'owner' && !$is_this_collaborator_the_board_owner) {
                                            $can_logged_in_user_manage_this_collaborator = true;
                                        } elseif ($current_user_permission_on_active_board == 'admin') {
                                            if (!$is_this_collaborator_the_board_owner && 
                                                $collab['permission_level'] != 'admin' && 
                                                $collab['collaborator_user_id'] != $user_id 
                                            ) {
                                                $can_logged_in_user_manage_this_collaborator = true;
                                            }
                                        }
                                        ?>
                                        <tr class="border-t hover:bg-gray-50">
                                            <td class="py-3 px-4"><?= htmlspecialchars($collab['username']) ?> <?= $is_this_collaborator_the_board_owner ? '<span class="text-xs text-green-600 font-semibold">(Owner)</span>' : '' ?></td>
                                            <td class="py-3 px-4"><?= htmlspecialchars($collab['email']) ?></td>
                                            <td class="py-3 px-4">
                                                <?php if ($is_this_collaborator_the_board_owner): ?>
                                                    <span class="px-2 py-1 text-sm text-gray-700 font-medium">Admin (Owner)</span>
                                                <?php elseif ($can_logged_in_user_manage_this_collaborator): ?>
                                                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" class="flex items-center gap-2">
                                                        <input type="hidden" name="collaboration_id" value="<?= $collab['collaboration_id'] ?>">
                                                        <select name="new_permission_level" class="px-2 py-1 border rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-[#e63946] w-auto">
                                                            <option value="view" <?= ($collab['permission_level'] == 'view') ? 'selected' : '' ?>>View</option>
                                                            <option value="edit" <?= ($collab['permission_level'] == 'edit') ? 'selected' : '' ?>>Edit</option>
                                                            <?php if ($current_user_permission_on_active_board == 'owner'): ?>
                                                            <option value="admin" <?= ($collab['permission_level'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                                                            <?php endif; ?>
                                                        </select>
                                                        <button type="submit" name="update_collaborator_permission" class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">Save</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 text-sm text-gray-700"><?= ucfirst(htmlspecialchars($collab['permission_level'])) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 text-right">
                                                <?php if ($can_logged_in_user_manage_this_collaborator && !$is_this_collaborator_the_board_owner): ?>
                                                <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" 
                                                      onsubmit="return confirm('Are you sure you want to remove <?= htmlspecialchars($collab['username']) ?> from this project?');" style="display: inline;">
                                                    <input type="hidden" name="collaboration_id" value="<?= $collab['collaboration_id'] ?>">
                                                    <button type="submit" name="remove_collaborator" class="text-red-600 hover:underline text-sm">Remove</button>
                                                </form>
                                                <?php else: echo '-'; endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                $pending_invitations = [];
                if ($active_board_id > 0 && $board_details && ($current_user_permission_on_active_board == 'owner' || $current_user_permission_on_active_board == 'admin') ) {
                    $sql_pending = "SELECT i.invitation_id, i.permission_level, i.custom_message, i.created_at, u.username as invited_username, u.email as invited_email
                                    FROM Planner_Invitations i
                                    JOIN Planner_Users u ON i.invited_user_id = u.user_id
                                    WHERE i.board_id = ? AND i.status = 'pending'";
                    $stmt_pending = $connection->prepare($sql_pending);
                    $stmt_pending->bind_param("i", $active_board_id);
                    $stmt_pending->execute();
                    $result_pending = $stmt_pending->get_result();
                    while ($row = $result_pending->fetch_assoc()) { $pending_invitations[] = $row; }
                    $stmt_pending->close();
                }
                ?>
                <?php if (!empty($pending_invitations)): ?>
                <div class="mt-8 <?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <h3 class="text-lg font-semibold mb-3">Pending Invitations</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border">
                            <thead><tr class="bg-gray-100"><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Username</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Email</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Permission</th><th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Sent</th><th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($pending_invitations as $invite): ?>
                                    <tr class="border-t hover:bg-gray-50">
                                        <td class="py-3 px-4"><?= htmlspecialchars($invite['invited_username']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($invite['invited_email']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars(ucfirst($invite['permission_level'])) ?></td>
                                        <td class="py-3 px-4 text-xs text-gray-500"><?= date('M d, Y', strtotime($invite['created_at'])) ?></td>
                                        <td class="py-3 px-4 text-right"><button onclick="cancelInvitation(<?= $invite['invitation_id'] ?>)" class="text-red-600 hover:underline text-sm">Cancel</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <div id="invitationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-auto">
                        <div class="flex justify-between items-center border-b px-6 py-4"><h3 class="text-xl font-bold">Invite Collaborator</h3><button onclick="closeInvitationModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button></div>
                        <form id="sendInvitationForm" method="post" action="contents/send_invitation.php" class="p-6">
                            <input type="hidden" name="board_id" value="<?= $active_board_id ?>">
                            <input type="hidden" name="board_name" value="<?= htmlspecialchars($board_details['board_name'] ?? '') ?>">
                            <div class="mb-4"><label for="invitation_email" class="block text-gray-700 font-medium mb-2">Email Address</label><input type="email" id="invitation_email" name="email" required placeholder="user@example.com" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"></div>
                            <div class="mb-4">
                                <label for="invitation_permission_level" class="block text-gray-700 font-medium mb-2">Permission Level</label>
                                <select id="invitation_permission_level" name="permission_level" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]">
                                    <option value="view">View</option>
                                    <option value="edit">Edit</option>
                                    <?php if ($current_user_permission_on_active_board == 'owner'): ?>
                                    <option value="admin">Admin</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="mb-4"><label for="custom_message" class="block text-gray-700 font-medium mb-2">Personal Message (Optional)</label><textarea id="custom_message" name="custom_message" rows="3" placeholder="Add a personal message..." class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#e63946]"></textarea></div>
                            <div class="mt-6 flex justify-end"><button type="button" onclick="closeInvitationModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg mr-3">Cancel</button><button type="submit" name="send_invitation_via_modal" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">Send Invitation</button></div>
                            <div id="invitationStatus" class="mt-4"></div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Notifications Tab -->
            <div id="notifications-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">My Notification Settings for "<?= htmlspecialchars($board_details['board_name']) ?>"</h2>
                <?php if ($notification_settings['source'] === 'global'): ?> <div class="mb-4 p-3 bg-blue-100 border-l-4 border-blue-500 text-blue-700"><p><i class="fas fa-info-circle mr-2"></i>Using your <strong>global default</strong> notification preferences. Changes saved here will be specific to "<?= htmlspecialchars($board_details['board_name']) ?>".</p></div>
                <?php elseif ($notification_settings['source'] === 'default' || $notification_settings['source'] === 'default_no_board'): ?> <div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700"><p><i class="fas fa-info-circle mr-2"></i>Using <strong>system default</strong> notification preferences. Changes saved here will be specific to "<?= htmlspecialchars($board_details['board_name']) ?>".</p></div>
                <?php endif; ?>
                <?php if ($board_details['is_archived']): ?> <div class="mb-4 p-3 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700"><p><i class="fas fa-info-circle mr-2"></i>Notification settings are disabled for archived projects.</p></div> <?php endif; ?>

                <div class="<?= ($board_details['is_archived'] ?? 0) ? 'opacity-50 pointer-events-none' : '' ?>">
                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>#notifications">
                        <h3 class="font-semibold mb-3 text-lg">Notify me when:</h3>
                        <p class="text-sm text-gray-600 mb-4">Select which activities on this board should trigger a notification for you.</p>
                        <div class="space-y-4">
                            <?php $event_notifications_map = [ 
                                'notify_task_created' => 'A new task is created on this board',
                                'notify_task_assignment' => 'A task is assigned to me or I am @mentioned in a task',
                                'notify_task_status_changed' => 'Status of my task or a task I follow changes',
                                'notify_task_updated' => 'Details of my task or a task I follow are updated',
                                'notify_new_comment' => "New comments on tasks I'm involved with or @mentioned in",
                                'notify_collaborator_added' => 'A new collaborator joins this board',
                                'notify_task_deleted' => 'A task is deleted from this board',
                                'notify_new_chat_message' => 'New chat messages related to this board (if applicable)',
                                'notify_column_changes' => 'Board structure changes (columns added/edited/deleted)',
                                'notify_project_management' => 'Project management updates (e.g., invitations, board settings changes)',
                            ];
                            foreach ($event_notifications_map as $key => $label): ?>
                            <div class="flex items-center">
                                <input type="checkbox" id="<?= $key ?>" name="<?= $key ?>" class="mr-3 h-5 w-5 text-[#e63946] focus:ring-[#e63946] border-gray-300 rounded" <?= (isset($notification_settings[$key]) && $notification_settings[$key] == 1) ? 'checked' : '' ?>>
                                <label for="<?= $key ?>" class="text-gray-700"><?= htmlspecialchars($label) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="flex justify-end mt-8">
                            <button type="submit" name="update_notification_settings_submit" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700">Save Notification Settings</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Advanced Tab (OWNER ONLY) -->
            <?php if ($current_user_permission_on_active_board == 'owner'): ?>
            <div id="advanced-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Advanced Settings</h2>
                
                <div class="mt-6">
                    <h4 class="text-md font-semibold text-gray-700 mb-1">Activity Log Visibility for Collaborator Roles</h4>
                    <p class="text-sm text-gray-600 mb-3">Control which collaborator roles can view this project's activity log. Owners always have access.</p>
                    <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>">
                        <input type="hidden" name="action_update_log_visibility" value="1">
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="log_access_roles[admin]" value="1"
                                       class="form-checkbox h-5 w-5 text-[#e63946] rounded focus:ring-[#e63946] border-gray-300"
                                       <?= $log_access_admin_setting ? 'checked' : '' ?>
                                       <?= ($board_details['is_archived'] ?? 0) ? 'disabled' : '' ?>>
                                <span class="ml-2 text-gray-700">Allow 'Admin' collaborators to view activity log.</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="log_access_roles[edit]" value="1"
                                       class="form-checkbox h-5 w-5 text-[#e63946] rounded focus:ring-[#e63946] border-gray-300"
                                       <?= $log_access_edit_setting ? 'checked' : '' ?>
                                       <?= ($board_details['is_archived'] ?? 0) ? 'disabled' : '' ?>>
                                <span class="ml-2 text-gray-700">Allow 'Edit' collaborators to view activity log.</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="log_access_roles[view]" value="1"
                                       class="form-checkbox h-5 w-5 text-[#e63946] rounded focus:ring-[#e63946] border-gray-300"
                                       <?= $log_access_view_setting ? 'checked' : '' ?>
                                       <?= ($board_details['is_archived'] ?? 0) ? 'disabled' : '' ?>>
                                <span class="ml-2 text-gray-700">Allow 'View' collaborators to view activity log.</span>
                            </label>
                        </div>
                        <?php if (!($board_details['is_archived'] ?? 0)): ?>
                        <div class="mt-4">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">Save Log Visibility</button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                
                <hr class="my-8 border-gray-300">

                <div class="p-4 border border-red-300 rounded-lg bg-red-50">
                   <h3 class="text-lg font-semibold mb-3 text-red-700">Danger Zone</h3>
                    <p class="text-gray-700 mb-4">These actions may be irreversible. Please proceed with caution.</p>
                    <div class="flex flex-wrap gap-3">
                        <form method="post" action="project_settings.php?board_id=<?= $active_board_id ?>" onsubmit="return confirm('Are you sure you want to <?= ($board_details['is_archived'] ?? 0) ? 'unarchive' : 'archive' ?> this project?');" class="inline-block">
                            <?php if ($board_details['is_archived'] ?? 0): ?> <button type="submit" name="unarchive_project" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center gap-2"><i class="fas fa-undo"></i> Unarchive Project</button>
                            <?php else: ?> <button type="submit" name="archive_project" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg flex items-center gap-2"><i class="fas fa-archive"></i> Archive Project</button> <?php endif; ?>
                        </form>
                        <button type="button" 
                                onclick="openDeleteProjectModal('<?= htmlspecialchars(addslashes($board_details['board_name']), ENT_QUOTES) ?>', <?= $active_board_id ?>)" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center gap-2" 
                                <?= ($board_details['is_archived'] ?? 0) ? 'disabled title="Unarchive project first to delete"' : '' ?>>
                            <i class="fas fa-trash-alt"></i> Delete Project
                        </button>
                    </div>
                    <?php if ($board_details['is_archived'] ?? 0): ?><p class="text-sm text-yellow-700 mt-2">Note: To delete this project, you must unarchive it first.</p><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Delete Project Confirmation Modal -->
            <div id="deleteProjectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-auto">
                    <div class="flex justify-between items-center border-b px-6 py-4">
                        <h3 class="text-xl font-bold text-red-700">Delete Project</h3>
                        <button onclick="closeDeleteProjectModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
                    </div>
                    <form id="deleteProjectForm" method="post" action="project_settings.php"> 
                        <div class="p-6 space-y-4">
                            <p class="text-sm text-gray-700">
                                This action is <strong class="font-semibold">irreversible</strong> and will permanently delete the project
                                "<strong id="deleteProjectNameConfirm" class="font-semibold"></strong>", including all its columns, tasks, and associated data.
                            </p>
                            <p class="text-sm text-gray-700">
                                To confirm deletion, please type the project name 
                                "<strong id="deleteProjectNameType" class="font-semibold text-red-600"></strong>" in the box below.
                            </p>
                            <div>
                                <label for="confirmProjectNameInput" class="sr-only">Confirm Project Name</label>
                                <input type="text" id="confirmProjectNameInput" name="confirm_project_name"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                                    placeholder="Type project name here">
                                <p id="deleteErrorText" class="text-xs text-red-500 mt-1 hidden"></p>
                            </div>
                            <input type="hidden" name="board_id_to_delete" id="boardIdToDelete" value="">
                            <input type="hidden" name="delete_project" value="1">
                        </div>
                        <div class="px-6 py-4 bg-gray-50 border-t flex justify-end space-x-3">
                            <button type="button" onclick="closeDeleteProjectModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">
                                Cancel
                            </button>
                            <button type="submit" id="confirmDeleteProjectButton"
                                    class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled> 
                                Delete Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Activity Log Tab -->
            <?php
            $can_view_project_activity_log = false;
            if ($current_user_permission_on_active_board == 'owner') {
                $can_view_project_activity_log = true;
            } else if ($board_details) { 
                $role_tab = $current_user_permission_on_active_board;
                if ($role_tab == 'admin' && $log_access_admin_setting) {
                    $can_view_project_activity_log = true;
                } elseif ($role_tab == 'edit' && $log_access_edit_setting) {
                    $can_view_project_activity_log = true;
                } elseif ($role_tab == 'view' && $log_access_view_setting) {
                    $can_view_project_activity_log = true;
                }
            }
            ?>
            <?php if ($can_view_project_activity_log): ?>
            <div id="activity-tab" class="settings-tab hidden">
                <h2 class="text-xl font-bold mb-4">Project Activity Log</h2>
                 <?php
                $activities_project = []; 
                if ($active_board_id > 0) {
                    $sql_activities_fetch_project = "SELECT a.*, u.username 
                                       FROM Planner_ActivityLog a 
                                       JOIN Planner_Users u ON a.user_id = u.user_id 
                                       WHERE a.board_id = ? 
                                       ORDER BY a.created_at DESC LIMIT 50";
                    $stmt_activities_fetch_project = $connection->prepare($sql_activities_fetch_project);
                    if ($stmt_activities_fetch_project) {
                        $stmt_activities_fetch_project->bind_param("i", $active_board_id);
                        $stmt_activities_fetch_project->execute();
                        $result_activities_fetch_project = $stmt_activities_fetch_project->get_result();
                        while ($activity_row_project = $result_activities_fetch_project->fetch_assoc()) {
                            $activities_project[] = $activity_row_project;
                        }
                        $stmt_activities_fetch_project->close();
                    } else {
                        if (!$error) $error = "Error preparing project activity log query.";
                    }
                }
                ?>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <?php if (empty($activities_project)): ?>
                        <div class="p-8 text-center">
                            <i class="far fa-clock text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">No activity recorded for this project yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($activities_project as $activity_item): ?>
                                <?php
                                $icon_class = 'fas fa-info-circle text-blue-500'; 
                                $activity_type_key = strtolower($activity_item['activity_type']);
                                $icon_map = [ 
                                    'task_created' => 'fas fa-plus-circle text-green-500', 'task_updated' => 'fas fa-edit text-blue-500', 
                                    'task_deleted' => 'fas fa-trash-alt text-red-500', 'task_moved' => 'fas fa-arrows-alt text-indigo-500', 
                                    'task_completed' => 'fas fa-check-circle text-green-600', 'task_reopened'  => 'fas fa-undo text-yellow-500', 
                                    'comment_added'  => 'fas fa-comment text-gray-500', 'collaborator_added' => 'fas fa-user-plus text-purple-500', 
                                    'collaborator_removed' => 'fas fa-user-minus text-orange-500', 
                                    'collaborator_left' => 'fas fa-sign-out-alt text-orange-600',
                                    'collaborator_permission_changed' => 'fas fa-user-shield text-teal-500', 
                                    'settings_updated' => 'fas fa-cog text-gray-600', 'board_created' => 'fas fa-chalkboard text-pink-500', 
                                    'project_archived' => 'fas fa-archive text-yellow-600', 'project_unarchived' => 'fas fa-undo text-green-600', 
                                    'project_deleted' => 'fas fa-trash-alt text-red-700',
                                    'invitation_sent' => 'fas fa-paper-plane text-blue-500',
                                    'invitation_accepted' => 'fas fa-user-check text-green-500',
                                    'invitation_declined' => 'fas fa-user-times text-red-500',
                                    'invitation_cancelled' => 'fas fa-ban text-orange-500'
                                ];
                                if (array_key_exists($activity_type_key, $icon_map)) { 
                                    $icon_class = $icon_map[$activity_type_key]; 
                                }
                                $timestamp_from_db = $activity_item['created_at']; 
                                try {
                                    $activity_date = new DateTime($timestamp_from_db);
                                    $formatted_date = $activity_date->format('M d, Y H:i');
                                } catch (Exception $e) {
                                    error_log("Error parsing date for activity log (Project Settings Tab): " . $e->getMessage() . " - Timestamp: " . $timestamp_from_db);
                                    $formatted_date = "Invalid date";
                                }
                                ?>
                                <div class="p-4 hover:bg-gray-50 flex items-start" data-activity-type="<?= htmlspecialchars($activity_item['activity_type']) ?>" data-activity-category="<?= explode('_', $activity_item['activity_type'])[0] ?>">
                                    <div class="mr-4 mt-1"><i class="<?= $icon_class ?> text-lg"></i></div>
                                    <div class="flex-grow">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($activity_item['username']) ?></span>
                                                <span class="text-gray-600 ml-1"><?= htmlspecialchars($activity_item['activity_description']) ?></span>
                                            </div>
                                            <div class="text-xs text-gray-500 whitespace-nowrap ml-2"><?= $formatted_date ?></div>
                                        </div>
                                        <?php if ($activity_item['related_entity_type'] == 'task' && $activity_item['related_entity_id']): ?>
                                            <div class="mt-1"><a href="kanban.php?board_id=<?= $active_board_id ?>&task_id=<?= $activity_item['related_entity_id'] ?>" class="text-blue-600 hover:underline text-sm">View Task</a></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                 <?php if (isset($activities_project) && count($activities_project) >= 50): ?>
                    <div class="mt-6 text-center"><a href="activity_log_all.php?board_id=<?= $active_board_id ?>" class="text-blue-600 hover:underline">View All Activities for this Project</a></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<script src="js/project_settings.js" defer></script>