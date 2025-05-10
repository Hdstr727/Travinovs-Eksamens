<?php
// File: authenticated-view/core/functions.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

function log_and_notify(
    mysqli $connection,
    int $board_id,
    int $actor_user_id,
    string $activity_type,
    string $activity_description,
    ?int $related_entity_id = null,
    ?string $related_entity_type = null,
    array $potential_recipient_user_ids = [],
    ?string $link = null
) {
    // --- Start of logging for this function call ---
    error_log("--- log_and_notify CALLED ---");
    error_log("log_and_notify: board_id = $board_id, actor_user_id = $actor_user_id, activity_type = '$activity_type'");
    error_log("log_and_notify: potential_recipients_count = " . count($potential_recipient_user_ids));
    // --- End of logging ---

    // 1. Log the activity
    $stmt_activity = $connection->prepare("INSERT INTO Planotajs_ActivityLog (board_id, user_id, activity_type, activity_description, related_entity_id, related_entity_type) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_activity) {
        error_log("log_and_notify: Prepare ActivityLog FAILED: " . $connection->error);
        return;
    }
    // Corrected bind_param assuming related_entity_id is INT and related_entity_type is VARCHAR
    // Both can be NULL, so we need to handle that if your DB columns allow NULL.
    // For now, assuming they are always provided if not null, or you pass actual null values.
    // A more robust way would check is_null and pass NULL, but let's stick to the current structure
    // If related_entity_id and related_entity_type can be null, your table must allow it, and
    // you would pass PHP null to bind_param, but the type string 'i' or 's' still applies to the column type.
    // For simplicity, let's go with iissis (board_id INT, user_id INT, activity_type STR, description STR, related_entity_id INT, related_entity_type STR)
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

    $users_to_notify_final = [];
    foreach ($potential_recipient_user_ids as $recipient_id) {
        if ($recipient_id != $actor_user_id) {
            $users_to_notify_final[$recipient_id] = true;
        }
    }

    $setting_field_map = [
        'task_assigned' => 'notify_task_assignment',
        'task_status_changed' => 'notify_task_status',
        'new_comment' => 'notify_comments',
        'new_chat_message' => 'notify_new_chat_message',
        'deadline_reminder' => 'notify_deadline',
        'collaborator_added' => 'notify_collaborator',
        'task_deleted' => 'notify_entity_deleted',
        'comment_deleted' => 'notify_entity_deleted',
    ];

    if (!isset($setting_field_map[$activity_type])) {
        error_log("log_and_notify: FATAL - Activity type '$activity_type' NOT FOUND in setting_field_map.");
        return;
    }
    $notification_setting_field = $setting_field_map[$activity_type];
    error_log("log_and_notify: Mapped activity_type '$activity_type' to notification_setting_field '$notification_setting_field'.");

    foreach (array_keys($users_to_notify_final) as $user_id_to_notify) {
        error_log("log_and_notify: Processing recipient user_id: $user_id_to_notify");

        $settings_sql = "SELECT `{$notification_setting_field}`, `channel_app`, `channel_email`
                         FROM `Planotajs_NotificationSettings`
                         WHERE `user_id` = ? AND (`board_id` = ? OR `board_id` IS NULL)
                         ORDER BY (`board_id` IS NULL) ASC, `board_id` DESC
                         LIMIT 1";
        error_log("log_and_notify: SQL to fetch settings for user $user_id_to_notify: $settings_sql");

        $stmt_settings = $connection->prepare($settings_sql);
        if (!$stmt_settings) {
            error_log("log_and_notify: CRITICAL ERROR - \$connection->prepare() FAILED for settings_sql.");
            error_log("log_and_notify: MySQL Error: " . $connection->error);
            error_log("log_and_notify: Failing SQL was: " . $settings_sql);
            continue;
        }

        $stmt_settings->bind_param("ii", $user_id_to_notify, $board_id);
        if (!$stmt_settings->execute()) {
            error_log("log_and_notify: \$stmt_settings->execute() FAILED: " . $stmt_settings->error);
            $stmt_settings->close();
            continue;
        }

        $settings_result = $stmt_settings->get_result();
        $settings = $settings_result->fetch_assoc();
        $stmt_settings->close();

        $should_notify_app = false;
        // $should_notify_email = false; // Placeholder

        // ********************************************************************
        // THIS IS THE MODIFIED SECTION FOR OPTION 3 (DEFAULT BEHAVIOR)
        // ********************************************************************
        if ($settings) {
            // Settings ARE found for this user/board combination
            error_log("log_and_notify: Settings found for user $user_id_to_notify: " . json_encode($settings));
            // Check if the specific notification type is enabled
            if (isset($settings[$notification_setting_field]) && $settings[$notification_setting_field] == 1) {
                // Check if the app channel is enabled
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
            $default_should_notify_for_this_type = true; // e.g., by default, user wants this type of notification
            $default_channel_app_enabled = true;      // e.g., by default, app channel is on

            if ($default_should_notify_for_this_type && $default_channel_app_enabled) {
                $should_notify_app = true;
                error_log("log_and_notify: APPLYING DEFAULT: APP NOTIFICATION WILL BE SENT for user $user_id_to_notify.");
            } else {
                error_log("log_and_notify: APPLYING DEFAULT: No app notification for user $user_id_to_notify (default is off or type is off).");
            }
        }
        // ********************************************************************
        // END OF MODIFIED SECTION
        // ********************************************************************


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

// You might have other functions in this file...
?>