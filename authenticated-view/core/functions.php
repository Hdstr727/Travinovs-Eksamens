<?php
// File: authenticated-view/core/functions.php

error_reporting(E_ALL);
ini_set('display_errors', 1); // Keep for development, disable for production

// Function to update board last activity (if you don't have it in this file already)
// This is from your ajax_handlers/utils/update_board_activity.php, let's assume it's here or included.
// If it's separate, ensure it's required where needed. For simplicity, I'll assume it's available.
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
    array $potential_recipient_user_ids = [], // Array of user IDs to potentially notify
    ?string $link = null
) {
    error_log("--- log_and_notify CALLED ---");
    error_log("log_and_notify: board_id = $board_id, actor_user_id = $actor_user_id, activity_type = '$activity_type'");
    error_log("log_and_notify: description = $activity_description");
    error_log("log_and_notify: potential_recipients_count = " . count($potential_recipient_user_ids));
    if ($link) error_log("log_and_notify: link = $link");


    // 1. Log the activity
    $stmt_activity = $connection->prepare("INSERT INTO Planotajs_ActivityLog (board_id, user_id, activity_type, activity_description, related_entity_id, related_entity_type) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_activity) {
        error_log("log_and_notify: Prepare ActivityLog FAILED: " . $connection->error);
        return;
    }
    // Ensure correct types for bind_param. Assuming related_entity_id is int, related_entity_type is string.
    // If they can be NULL, the table must allow it. PHP null will be bound correctly.
    $stmt_activity->bind_param("iissis", $board_id, $actor_user_id, $activity_type, $activity_description, $related_entity_id, $related_entity_type);

    if (!$stmt_activity->execute()) {
        error_log("log_and_notify: Execute ActivityLog FAILED: " . $stmt_activity->error);
        $stmt_activity->close();
        return;
    }
    $activity_id = $stmt_activity->insert_id;
    $stmt_activity->close();

    if (!$activity_id) {
        error_log("log_and_notify: Activity logging failed to return an ID.");
        return;
    }
    error_log("log_and_notify: Activity logged successfully. ID: $activity_id");

    // Filter out the actor from the recipients
    $users_to_notify_final = [];
    foreach ($potential_recipient_user_ids as $recipient_id) {
        if ($recipient_id != $actor_user_id) {
            $users_to_notify_final[$recipient_id] = true; // Use as keys to ensure uniqueness
        }
    }
    $users_to_notify_final_ids = array_keys($users_to_notify_final);

    if (empty($users_to_notify_final_ids)) {
        error_log("log_and_notify: No recipients to notify after filtering actor.");
        error_log("--- log_and_notify FINISHED (no recipients) ---");
        return;
    }

    // Map activity types to notification setting fields in Planotajs_NotificationSettings
    // ADD NEW ACTIVITY TYPES HERE AS NEEDED
    $setting_field_map = [
        'new_chat_message'      => 'notify_new_chat_message', // From your example
        'task_created'          => 'notify_task_created',     // New
        'task_updated'          => 'notify_task_updated',     // New
        'task_deleted'          => 'notify_task_deleted',     // New (was notify_entity_deleted)
        'column_created'        => 'notify_column_created',   // New
        'column_updated'        => 'notify_column_updated',   // New
        'column_deleted'        => 'notify_column_deleted',   // New (was notify_entity_deleted)
        // Keep existing ones if still relevant
        'task_assigned'         => 'notify_task_assignment',
        'task_status_changed'   => 'notify_task_status',
        'new_comment'           => 'notify_comments',
        'deadline_reminder'     => 'notify_deadline',
        'collaborator_added'    => 'notify_collaborator',
    ];

    if (!isset($setting_field_map[$activity_type])) {
        error_log("log_and_notify: FATAL - Activity type '$activity_type' NOT FOUND in setting_field_map. No notifications will be sent for this type.");
        error_log("--- log_and_notify FINISHED (unknown activity type for settings) ---");
        return;
    }
    $notification_setting_field = $setting_field_map[$activity_type];
    error_log("log_and_notify: Mapped activity_type '$activity_type' to notification_setting_field '$notification_setting_field'.");

    foreach ($users_to_notify_final_ids as $user_id_to_notify) {
        error_log("log_and_notify: Processing recipient user_id: $user_id_to_notify");

        // Fetch user's specific setting for this board, or their global default if no board-specific one exists.
        // The `ORDER BY (board_id IS NULL) ASC` makes sure that if a board-specific setting exists (board_id is NOT NULL), it comes first.
        $settings_sql = "SELECT `{$notification_setting_field}`, `channel_app`, `channel_email`
                         FROM `Planotajs_NotificationSettings`
                         WHERE `user_id` = ? AND (`board_id` = ? OR `board_id` IS NULL)
                         ORDER BY (`board_id` IS NULL) ASC 
                         LIMIT 1"; 
        // Note: `board_id` in ORDER BY `board_id` DESC was removed as `IS NULL` ASC already prioritizes non-NULL.

        $stmt_settings = $connection->prepare($settings_sql);
        if (!$stmt_settings) {
            error_log("log_and_notify: CRITICAL ERROR - \$connection->prepare() FAILED for settings_sql: " . $connection->error);
            continue; // Skip this recipient
        }

        $stmt_settings->bind_param("ii", $user_id_to_notify, $board_id);
        if (!$stmt_settings->execute()) {
            error_log("log_and_notify: \$stmt_settings->execute() FAILED: " . $stmt_settings->error);
            $stmt_settings->close();
            continue; // Skip this recipient
        }

        $settings_result = $stmt_settings->get_result();
        $settings = $settings_result->fetch_assoc();
        $stmt_settings->close();

        $should_notify_app = false;
        // $should_notify_email = false; // Placeholder for email notifications

        if ($settings) {
            error_log("log_and_notify: Settings found for user $user_id_to_notify: " . json_encode($settings));
            if (isset($settings[$notification_setting_field]) && $settings[$notification_setting_field] == 1) {
                if (isset($settings['channel_app']) && $settings['channel_app'] == 1) {
                    $should_notify_app = true;
                    error_log("log_and_notify: APP NOTIFICATION WILL BE SENT for user $user_id_to_notify based on explicit settings.");
                } else {
                    error_log("log_and_notify: App channel disabled or not set in explicit settings for user $user_id_to_notify for '$notification_setting_field'.");
                }
            } else {
                error_log("log_and_notify: Notification type '$notification_setting_field' disabled or not set in explicit settings for user $user_id_to_notify.");
            }
        } else {
            // NO settings found for this user/board combination - APPLY DEFAULT BEHAVIOR
            error_log("log_and_notify: No specific or global notification settings found for user $user_id_to_notify.");
            // Define your default behavior here:
            // For most actions, it's reasonable to assume users want to be notified by default.
            $default_should_notify_for_this_type = true; 
            $default_channel_app_enabled = true;      

            if ($default_should_notify_for_this_type && $default_channel_app_enabled) {
                $should_notify_app = true;
                error_log("log_and_notify: APPLYING DEFAULT: APP NOTIFICATION WILL BE SENT for user $user_id_to_notify.");
            } else {
                error_log("log_and_notify: APPLYING DEFAULT: No app notification for user $user_id_to_notify (default is off or type is off).");
            }
        }

        if ($should_notify_app) {
            $stmt_notify = $connection->prepare("INSERT INTO Planotajs_Notifications (user_id, activity_id, board_id, message, link) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_notify) {
                error_log("log_and_notify: Prepare Notifications Insert FAILED: " . $connection->error);
            } else {
                $stmt_notify->bind_param("iiiss", $user_id_to_notify, $activity_id, $board_id, $activity_description, $link);
                if (!$stmt_notify->execute()) {
                    error_log("log_and_notify: Execute Notifications Insert FAILED: " . $stmt_notify->error);
                } else {
                    error_log("log_and_notify: Notification record INSERTED successfully for user $user_id_to_notify.");
                }
                $stmt_notify->close();
            }
        }
    }
    error_log("--- log_and_notify FINISHED ---");
}

// Function to get board name and actor username (useful for notification messages)
function get_board_and_actor_info(mysqli $connection, int $board_id, int $actor_user_id): array {
    $info = ['board_name' => 'A board', 'actor_username' => 'Someone']; // Defaults
    $sql = "SELECT b.board_name, u.username as actor_username 
            FROM Planotajs_Boards b, Planotajs_Users u 
            WHERE b.board_id = ? AND u.user_id = ?";
    $stmt = $connection->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $board_id, $actor_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($data = $result->fetch_assoc()) {
            $info['board_name'] = $data['board_name'];
            $info['actor_username'] = $data['actor_username'];
        }
        $stmt->close();
    }
    return $info;
}

// Function to get all users associated with a board (owner + collaborators)
function get_board_associated_user_ids(mysqli $connection, int $board_id): array {
    $user_ids = [];
    $sql = "(SELECT user_id FROM Planotajs_Boards WHERE board_id = ?)
            UNION
            (SELECT user_id FROM Planotajs_Collaborators WHERE board_id = ?)";
    $stmt = $connection->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $board_id, $board_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $user_ids[] = $row['user_id'];
        }
        $stmt->close();
    }
    return array_unique($user_ids); // Ensure unique IDs
}

?>