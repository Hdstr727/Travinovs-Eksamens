<?php
// project_settings_content.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: core/login.php");
    exit();
}

require_once '../admin/database/connection.php';
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

<?php
// Set page title and content for the layout
$title = "Project settings - Planner+";
$content = 'contents/project_settings_content.php';

// Include the layout
include 'core/layout.php';
?>