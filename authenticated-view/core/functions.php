<?php
// File: authenticated-view/core/functions.php

error_reporting(E_ALL);
ini_set('display_errors', 1); 

if (!function_exists('update_board_last_activity_timestamp')) {
    function update_board_last_activity_timestamp(mysqli $connection, int $board_id) {
        $sql = "UPDATE Planotajs_Boards SET updated_at = CURRENT_TIMESTAMP WHERE board_id = ?";
        $stmt = $connection->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $board_id);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for update_board_last_activity_timestamp: " . $connection->error);
        }
    }
}

function log_and_notify(
    mysqli $connection,
    int $board_id,
    int $actor_user_id,
    string $activity_type,
    string $activity_description,
    ?int $related_entity_id = null,
    ?string $related_entity_type = null,
    array $potential_recipient_user_ids = [], 
    ?string $link = null,
    ?string $notification_type_override = null // New optional parameter
) {
    error_log("--- log_and_notify CALLED ---");
    error_log("log_and_notify: board_id = $board_id, actor_user_id = $actor_user_id, activity_type = '$activity_type'");
    // ... (other logs)

    $stmt_activity = $connection->prepare("INSERT INTO Planotajs_ActivityLog (board_id, user_id, activity_type, activity_description, related_entity_id, related_entity_type) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_activity) { /* ... error log ... */ return; }
    $stmt_activity->bind_param("iissis", $board_id, $actor_user_id, $activity_type, $activity_description, $related_entity_id, $related_entity_type);
    if (!$stmt_activity->execute()) { /* ... error log ... */ $stmt_activity->close(); return; }
    $activity_id = $stmt_activity->insert_id;
    $stmt_activity->close();
    if (!$activity_id) { /* ... error log ... */ return; }
    error_log("log_and_notify: Activity logged successfully. ID: $activity_id");

    $users_to_notify_final_ids = [];
    foreach ($potential_recipient_user_ids as $recipient_id) {
        if ($recipient_id != $actor_user_id) {
            $users_to_notify_final_ids[] = $recipient_id;
        }
    }
    $users_to_notify_final_ids = array_unique($users_to_notify_final_ids);


    if (empty($users_to_notify_final_ids)) {
        error_log("log_and_notify: No recipients to notify after filtering actor for activity type '$activity_type'.");
        // Still proceed if it's a direct notification type like 'invitation' handled by send_invitation.php
        // but for general board activity, if no recipients, we can stop here for notifications.
        // However, the function is also used for direct notifications where $potential_recipient_user_ids might be a single user.
        // The main loop below will handle it.
    }

    $setting_field_map = [
        'new_chat_message'      => 'notify_new_chat_message',
        'task_created'          => 'notify_task_created',
        'task_updated'          => 'notify_task_updated',
        'task_deleted'          => 'notify_task_deleted',
        'column_created'        => 'notify_column_created',
        'column_updated'        => 'notify_column_updated',
        'column_deleted'        => 'notify_column_deleted',
        'task_assigned'         => 'notify_task_assignment',
        'task_status_changed'   => 'notify_task_status',
        'new_comment'           => 'notify_comments',
        'deadline_reminder'     => 'notify_deadline',
        'collaborator_added'    => 'notify_collaborator', // When a user *becomes* a collaborator (after accepting invite)
        'invitation_sent'       => 'notify_project_management', // Example: A setting for owners to see invites sent
        'invitation_accepted'   => 'notify_project_management', // Example: A setting for owners to see invites accepted
        'invitation_declined'   => 'notify_project_management',
        'invitation_cancelled'  => 'notify_project_management',
        // Add other activity types that should trigger general board notifications
    ];

    // Determine the notification type for Planotajs_Notifications.type column
    // If an override is provided (like 'invitation' from send_invitation.php), use that.
    // Otherwise, derive from activity_type or use a default like 'activity'.
    $notification_db_type = $notification_type_override ?? $activity_type; // Use override or activity_type itself

    if (empty($potential_recipient_user_ids)) { // If specifically told no one to notify via this general mechanism
        error_log("log_and_notify: Explicitly no potential recipients provided for general notification. Logging only for '$activity_type'.");
        error_log("--- log_and_notify FINISHED (explicitly no recipients for general notification) ---");
        return; // Only log was done.
    }


    // This loop is for general board notifications based on user settings
    foreach ($users_to_notify_final_ids as $user_id_to_notify) {
        if (!isset($setting_field_map[$activity_type])) {
            error_log("log_and_notify: Activity type '$activity_type' NOT IN setting_field_map. Skipping general notification for user $user_id_to_notify.");
            continue; // Skip to next user if this activity type doesn't have a general notification setting
        }
        $notification_setting_field = $setting_field_map[$activity_type];
        error_log("log_and_notify: For user $user_id_to_notify, checking setting '$notification_setting_field' for activity '$activity_type'");

        $settings_sql = "SELECT `{$notification_setting_field}`, `channel_app` FROM `Planotajs_NotificationSettings` WHERE `user_id` = ? AND (`board_id` = ? OR `board_id` IS NULL) ORDER BY (`board_id` IS NULL) ASC LIMIT 1";
        $stmt_settings = $connection->prepare($settings_sql);
        if (!$stmt_settings) { /* ... error log ... */ continue; }
        $stmt_settings->bind_param("ii", $user_id_to_notify, $board_id);
        if (!$stmt_settings->execute()) { /* ... error log ... */ $stmt_settings->close(); continue; }
        $settings = $stmt_settings->get_result()->fetch_assoc();
        $stmt_settings->close();

        $should_notify_app = false;
        if ($settings) {
            if (isset($settings[$notification_setting_field]) && $settings[$notification_setting_field] == 1 && isset($settings['channel_app']) && $settings['channel_app'] == 1) {
                $should_notify_app = true;
            }
        } else { // Default behavior if no settings found
            if ($notification_setting_field !== 'notify_project_management') { // Don't default notify for admin-like things
                 $should_notify_app = true; // Default to notify for most things
            }
        }

        if ($should_notify_app) {
            $stmt_notify = $connection->prepare("INSERT INTO Planotajs_Notifications (user_id, activity_id, board_id, message, link, type, related_entity_id, related_entity_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt_notify) { /* ... error log ... */ } 
            else {
                // Use the derived $notification_db_type
                $stmt_notify->bind_param("iiisssis", $user_id_to_notify, $activity_id, $board_id, $activity_description, $link, $notification_db_type, $related_entity_id, $related_entity_type);
                if (!$stmt_notify->execute()) { /* ... error log ... */ }
                $stmt_notify->close();
            }
        }
    }
    error_log("--- log_and_notify FINISHED ---");
}

function get_board_and_actor_info(mysqli $connection, int $board_id, int $actor_user_id): array {
    $info = ['board_name' => 'A board', 'actor_username' => 'Someone'];
    $sql = "SELECT b.board_name, u.username as actor_username FROM Planotajs_Boards b, Planotajs_Users u WHERE b.board_id = ? AND u.user_id = ?";
    $stmt = $connection->prepare($sql);
    if ($stmt) { $stmt->bind_param("ii", $board_id, $actor_user_id); $stmt->execute(); $result = $stmt->get_result();
        if ($data = $result->fetch_assoc()) { $info['board_name'] = $data['board_name']; $info['actor_username'] = $data['actor_username']; }
        $stmt->close();
    } return $info;
}

function get_board_associated_user_ids(mysqli $connection, int $board_id): array {
    $user_ids = [];
    $sql = "(SELECT user_id FROM Planotajs_Boards WHERE board_id = ?) UNION (SELECT user_id FROM Planotajs_Collaborators WHERE board_id = ?)";
    $stmt = $connection->prepare($sql);
    if ($stmt) { $stmt->bind_param("ii", $board_id, $board_id); $stmt->execute(); $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) { $user_ids[] = $row['user_id']; }
        $stmt->close();
    } return array_unique($user_ids);
}
?>