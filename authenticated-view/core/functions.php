<?php
// File: authenticated-view/core/functions.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!function_exists('update_board_last_activity_timestamp')) {
    function update_board_last_activity_timestamp(mysqli $connection, int $board_id) {
        $sql = "UPDATE Planner_Boards SET updated_at = CURRENT_TIMESTAMP WHERE board_id = ?";
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

if (!function_exists('get_user_notification_preferences')) {
    function get_user_notification_preferences(mysqli $connection, int $user_id, ?int $board_id, string $activity_type_key): array {
        // Application-level defaults (match your DB table defaults)
        $default_prefs = [
            'event_enabled' => 1, // Default for most events, can be overridden by specific key checks
            'channel_app'   => 1,
            'channel_email' => 0,
        ];

        // Some events might be off by default
        if (in_array($activity_type_key, ['notify_column_changes', 'notify_task_deleted'])) {
            $default_prefs['event_enabled'] = 0;
        }

        $prefs_to_use = $default_prefs;
        $source = 'application_default';

        // 1. Try board-specific settings if board_id is provided
        if ($board_id !== null) {
            $sql_board = "SELECT `{$activity_type_key}`, `channel_app`, `channel_email` 
                          FROM `Planner_NotificationSettings` 
                          WHERE `user_id` = ? AND `board_id` = ?";
            $stmt_board = $connection->prepare($sql_board);
            if ($stmt_board) {
                $stmt_board->bind_param("ii", $user_id, $board_id);
                $stmt_board->execute();
                $result_board = $stmt_board->get_result();
                if ($row_board = $result_board->fetch_assoc()) {
                    $prefs_to_use = [
                        'event_enabled' => (int)$row_board[$activity_type_key],
                        'channel_app'   => (int)$row_board['channel_app'],
                        'channel_email' => (int)$row_board['channel_email'],
                    ];
                    $source = 'board_specific';
                    $stmt_board->close();
                    // Return early if board-specific setting found
                    return [
                        'should_notify_app' => ($prefs_to_use['event_enabled'] == 1 && $prefs_to_use['channel_app'] == 1),
                        'should_notify_email' => ($prefs_to_use['event_enabled'] == 1 && $prefs_to_use['channel_email'] == 1),
                        'source' => $source
                    ];
                }
                $stmt_board->close();
            } else {
                error_log("Error preparing board-specific notification settings query: " . $connection->error);
            }
        }

        // 2. Try global user settings (board_id IS NULL)
        $sql_global = "SELECT `{$activity_type_key}`, `channel_app`, `channel_email` 
                       FROM `Planner_NotificationSettings` 
                       WHERE `user_id` = ? AND `board_id` IS NULL";
        $stmt_global = $connection->prepare($sql_global);
        if ($stmt_global) {
            $stmt_global->bind_param("i", $user_id);
            $stmt_global->execute();
            $result_global = $stmt_global->get_result();
            if ($row_global = $result_global->fetch_assoc()) {
                $prefs_to_use = [
                    'event_enabled' => (int)$row_global[$activity_type_key],
                    'channel_app'   => (int)$row_global['channel_app'],
                    'channel_email' => (int)$row_global['channel_email'],
                ];
                $source = 'user_global';
            }
            $stmt_global->close();
        } else {
            error_log("Error preparing global notification settings query: " . $connection->error);
        }
        
        // Final decision based on the hierarchy
        return [
            'should_notify_app' => ($prefs_to_use['event_enabled'] == 1 && $prefs_to_use['channel_app'] == 1),
            'should_notify_email' => ($prefs_to_use['event_enabled'] == 1 && $prefs_to_use['channel_email'] == 1), // Kept for completeness
            'source' => $source
        ];
    }
}


/**
 * Logs an activity and sends notifications to relevant users based on their preferences.
 */
function log_and_notify(
    mysqli $connection,
    int $board_id, // Can be 0 or null if not board specific, but typically it is.
    int $actor_user_id,
    string $activity_type,
    string $activity_description,
    ?int $related_entity_id = null,
    ?string $related_entity_type = null,
    array $potential_recipient_user_ids = [], // Users who *could* receive a notification
    ?string $link = null,
    ?string $notification_type_override = null // e.g., 'invitation' for Planner_Notifications.type
) {
    error_log("--- log_and_notify CALLED ---");
    error_log("Board: $board_id, Actor: $actor_user_id, Activity: $activity_type, Desc: $activity_description");

    // 1. Log the activity
    $stmt_activity = $connection->prepare("INSERT INTO Planner_ActivityLog (board_id, user_id, activity_type, activity_description, related_entity_id, related_entity_type) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_activity) {
        error_log("log_and_notify: Prepare failed for ActivityLog: " . $connection->error);
        return;
    }
    $stmt_activity->bind_param("iissis", $board_id, $actor_user_id, $activity_type, $activity_description, $related_entity_id, $related_entity_type);
    if (!$stmt_activity->execute()) {
        error_log("log_and_notify: Execute failed for ActivityLog: " . $stmt_activity->error);
        $stmt_activity->close();
        return;
    }
    $activity_id = $stmt_activity->insert_id; // Get ID of the logged activity
    $stmt_activity->close();
    if (!$activity_id) {
        error_log("log_and_notify: Failed to get insert_id for ActivityLog.");
        return;
    }
    error_log("Activity logged successfully. ID: $activity_id");

    // 2. Determine users to notify (filter out the actor)
    $users_to_notify_final_ids = [];
    foreach ($potential_recipient_user_ids as $recipient_id) {
        if ($recipient_id != $actor_user_id) { // Don't notify the person who performed the action
            $users_to_notify_final_ids[] = $recipient_id;
        }
    }
    $users_to_notify_final_ids = array_unique($users_to_notify_final_ids);

    if (empty($users_to_notify_final_ids)) {
        error_log("No recipients to notify after filtering actor for '$activity_type'.");
        error_log("--- log_and_notify FINISHED (no recipients) ---");
        return;
    }

    // Map activity_type (from code, e.g., 'task_created') to the DB column name for the setting
    // (e.g., 'notify_task_created')
    $setting_field_map = [
        'task_created'          => 'notify_task_created',
        'task_assigned'         => 'notify_task_assignment',       // When task is assigned TO a user
        'task_assignment'       => 'notify_task_assignment',       // Generic for assignment events
        'task_status_changed'   => 'notify_task_status_changed',
        'task_status'           => 'notify_task_status_changed',   // Alias
        'task_updated'          => 'notify_task_updated',
        'task_deleted'          => 'notify_task_deleted',
        'new_comment'           => 'notify_new_comment',
        'comment_added'         => 'notify_new_comment',           // Alias
        'collaborator_added'    => 'notify_collaborator_added',    // User X joined board Y
        'collaborator_left'     => 'notify_project_management',
        'invitation_sent'       => 'notify_project_management',    // For owner: an invite was sent for their board
        'invitation_accepted'   => 'notify_project_management',    // For owner: an invite was accepted for their board
        'invitation_declined'   => 'notify_project_management',    // For owner: an invite was declined
        'column_created'        => 'notify_column_changes',
        'column_updated'        => 'notify_column_changes',
        'column_deleted'        => 'notify_column_changes',
        'new_chat_message'      => 'notify_new_chat_message',
    ];

    $notification_setting_key = $setting_field_map[$activity_type] ?? null;

    if (!$notification_setting_key) {
        error_log("No notification setting key found in map for activity_type: '$activity_type'. Cannot check user preferences.");
        // For specific overrides like 'invitation' where we ALWAYS notify the recipient of the invite
        // this check might be bypassed if $notification_type_override is set.
        if (!$notification_type_override) {
            error_log("--- log_and_notify FINISHED (unknown activity type for preferences) ---");
            return;
        }
    }
    
    // Determine the 'type' for Planner_Notifications table.
    // 'invitation' notifications are special as they are direct.
    $notification_db_type = $notification_type_override ?? $activity_type;


    // 3. Iterate through potential recipients and check their preferences
    foreach ($users_to_notify_final_ids as $user_id_to_notify) {
        $should_send_app_notification = false;

        if ($notification_db_type === 'invitation') {
            $prefs = get_user_notification_preferences($connection, $user_id_to_notify, $board_id, 'notify_project_management'); // Assuming 'invitation' maps to a general project setting
            if ($prefs['channel_app']) { // We don't check event_enabled for direct invites, only the channel.
                 $should_send_app_notification = true;
                 error_log("User $user_id_to_notify: Direct '$notification_db_type' notification. Channel App: ENABLED (Source: {$prefs['source']}). Will send.");
            } else {
                 error_log("User $user_id_to_notify: Direct '$notification_db_type' notification. Channel App: DISABLED (Source: {$prefs['source']}). Will NOT send.");
            }

        } elseif ($notification_setting_key) {
            // For general activities, check full preferences
            $prefs = get_user_notification_preferences($connection, $user_id_to_notify, $board_id, $notification_setting_key);
            if ($prefs['should_notify_app']) {
                $should_send_app_notification = true;
                error_log("User $user_id_to_notify for '$activity_type' (key: $notification_setting_key): App Notification ENABLED (Source: {$prefs['source']}). Will send.");
            } else {
                error_log("User $user_id_to_notify for '$activity_type' (key: $notification_setting_key): App Notification DISABLED (Source: {$prefs['source']}). Will NOT send.");
            }
        } else {
            error_log("User $user_id_to_notify: Could not determine preferences for '$activity_type' as notification_setting_key is null and it's not an override type. Skipping.");
            continue;
        }


        if ($should_send_app_notification) {
            // Insert into Planner_Notifications
            $stmt_notify = $connection->prepare("INSERT INTO Planner_Notifications (user_id, activity_id, board_id, message, link, type, related_entity_id, related_entity_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt_notify) {
                error_log("log_and_notify: Prepare failed for Notifications table: " . $connection->error);
            } else {
                // For 'board_id' in notifications, use the $board_id from function params.
                // If activity is not board specific, $board_id might be null/0. Adjust table constraint if needed.
                $current_board_id_for_notif = ($board_id > 0) ? $board_id : null;

                $stmt_notify->bind_param("iiisssis", $user_id_to_notify, $activity_id, $current_board_id_for_notif, $activity_description, $link, $notification_db_type, $related_entity_id, $related_entity_type);
                if (!$stmt_notify->execute()) {
                    error_log("log_and_notify: Execute failed for Notifications table: " . $stmt_notify->error);
                } else {
                    error_log("In-app notification created for user $user_id_to_notify for activity '$activity_type'.");
                }
                $stmt_notify->close();
            }
        }

        // Add email notification logic here if you re-introduce it, checking $prefs['should_notify_email']
    }
    error_log("--- log_and_notify FINISHED ---");
}


function get_board_and_actor_info(mysqli $connection, int $board_id, int $actor_user_id): array {
    $info = ['board_name' => 'A board', 'actor_username' => 'Someone'];
    // Ensure Planner_Users table exists and has user_id, username
    // Ensure Planner_Boards table exists and has board_id, board_name
    $sql = "SELECT b.board_name, u.username as actor_username 
            FROM Planner_Boards b, Planner_Users u 
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
    } else {
        error_log("Error preparing statement in get_board_and_actor_info: " . $connection->error);
    }
    return $info;
}

function get_board_associated_user_ids(mysqli $connection, int $board_id): array {
    $user_ids = [];
    // Ensure Planner_Boards has user_id (owner)
    // Ensure Planner_Collaborators has user_id and board_id
    $sql = "(SELECT user_id FROM Planner_Boards WHERE board_id = ?) 
            UNION 
            (SELECT user_id FROM Planner_Collaborators WHERE board_id = ?)";
    $stmt = $connection->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $board_id, $board_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $user_ids[] = $row['user_id'];
        }
        $stmt->close();
    } else {
        error_log("Error preparing statement in get_board_associated_user_ids: " . $connection->error);
    }
    return array_unique($user_ids);
}

?>