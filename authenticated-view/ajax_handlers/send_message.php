<?php
// File: ajax_handlers/send_message.php

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit();
}

// Include database connection
require_once '../../admin/database/connection.php';
// Include our notification functions
require_once '../core/functions.php'; // <--- ADD THIS LINE (adjust path if needed)

// Validate request
if (!isset($_POST['board_id']) || !is_numeric($_POST['board_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid board ID'
    ]);
    exit();
}

if (!isset($_POST['message']) || empty(trim($_POST['message']))) {
    echo json_encode([
        'success' => false,
        'message' => 'Message cannot be empty'
    ]);
    exit();
}

$board_id = intval($_POST['board_id']);
$user_id = $_SESSION['user_id']; // This is the actor_user_id
$message_text = trim($_POST['message']);

// Check if message is too long
if (strlen($message_text) > 1000) { // Limiting message to 1000 characters
    echo json_encode([
        'success' => false,
        'message' => 'Message is too long (maximum 1000 characters)'
    ]);
    exit();
}

// Check if user has write access to this board (either as owner or collaborator with write permission)
// Your existing access check is fine, but ensure it doesn't exit prematurely if you want to send notifications
// For simplicity, I'll keep your access check as is. If it fails, no message is sent, thus no notification.
$access_sql = "SELECT b.user_id as board_owner_id, c.permission_level, pb.board_name, pu.username as actor_username
              FROM Planner_Boards b
              LEFT JOIN Planner_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
              JOIN Planner_Users pu ON pu.user_id = ?  -- Join to get actor's username
              JOIN Planner_Boards pb ON pb.board_id = ? -- Join to get board name
              WHERE b.board_id = ?
              AND (b.user_id = ? OR c.permission_level IN ('admin', 'edit')) -- editor might also send messages
              AND b.is_deleted = 0";

$access_stmt = $connection->prepare($access_sql);
// $user_id for collaborator check, $user_id for actor_username, $board_id for board_name, $board_id for main WHERE, $user_id for owner/permission check
$access_stmt->bind_param("iiiii", $user_id, $user_id, $board_id, $board_id, $user_id);
$access_stmt->execute();
$access_result = $access_stmt->get_result();

if ($access_result->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to send messages in this board'
    ]);
    exit();
}
$access_data = $access_result->fetch_assoc(); // Fetch actor's username and board name
$actor_username = $access_data['actor_username'];
$board_name = $access_data['board_name'];
$access_stmt->close();


// Insert message
$insert_sql = "INSERT INTO Planner_ChatMessages (board_id, user_id, message_text) VALUES (?, ?, ?)";
$insert_stmt = $connection->prepare($insert_sql);
$insert_stmt->bind_param("iis", $board_id, $user_id, $message_text);

if ($insert_stmt->execute()) {
    $message_id = $insert_stmt->insert_id;

    // ------------- START NOTIFICATION LOGIC -------------
    // 1. Identify potential recipients: all collaborators on this board + the board owner
    $recipients_sql = "
        (SELECT user_id FROM Planner_Collaborators WHERE board_id = ?)
        UNION
        (SELECT user_id FROM Planner_Boards WHERE board_id = ?)
    ";
    $stmt_recipients = $connection->prepare($recipients_sql);
    $stmt_recipients->bind_param("ii", $board_id, $board_id);
    $stmt_recipients->execute();
    $recipients_result = $stmt_recipients->get_result();

    $potential_recipient_user_ids = [];
    while ($row = $recipients_result->fetch_assoc()) {
        // Don't notify the person who sent the message
        if ($row['user_id'] != $user_id) {
            $potential_recipient_user_ids[] = $row['user_id'];
        }
    }
    $stmt_recipients->close();

    if (!empty($potential_recipient_user_ids)) {
        $notification_description = htmlspecialchars($actor_username) . " sent a new message in '" . htmlspecialchars($board_name) . "': \"" . htmlspecialchars(mb_strimwidth($message_text, 0, 50, "...")) . "\"";
        $notification_link = "chat.php?board_id=" . $board_id . "#msg-" . $message_id; // Link to the chat, potentially an anchor to the message

        log_and_notify(
            $connection,
            $board_id,
            $user_id,               // actor_user_id (who sent the message)
            'new_chat_message',         // activity_type (use 'new_comment' or create a 'new_chat_message' type)
            $notification_description,
            $message_id,            // related_entity_id (the chat message ID)
            'chat_message',         // related_entity_type
            $potential_recipient_user_ids,
            $notification_link
        );
    }
    // ------------- END NOTIFICATION LOGIC -------------

    echo json_encode([
        'success' => true,
        'message_id' => $message_id
    ]);
} else {
    error_log("Failed to insert chat message: " . $insert_stmt->error); // Log the actual DB error
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send message.' // Generic error to user
    ]);
}

$insert_stmt->close();
$connection->close(); // Close connection if it's not closed elsewhere
exit();
?>