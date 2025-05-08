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
$user_id = $_SESSION['user_id'];
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
$access_sql = "SELECT b.user_id, c.permission_level 
              FROM Planotajs_Boards b
              LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
              WHERE b.board_id = ? 
              AND (b.user_id = ? OR c.permission_level IN ('write', 'admin'))
              AND b.is_deleted = 0";

$access_stmt = $connection->prepare($access_sql);
$access_stmt->bind_param("iii", $user_id, $board_id, $user_id);
$access_stmt->execute();
$access_result = $access_stmt->get_result();

if ($access_result->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to send messages in this board'
    ]);
    exit();
}
$access_stmt->close();

// Insert message
$insert_sql = "INSERT INTO Planotajs_ChatMessages (board_id, user_id, message_text) VALUES (?, ?, ?)";
$insert_stmt = $connection->prepare($insert_sql);
$insert_stmt->bind_param("iis", $board_id, $user_id, $message_text);

if ($insert_stmt->execute()) {
    $message_id = $insert_stmt->insert_id;
    
    echo json_encode([
        'success' => true,
        'message_id' => $message_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send message: ' . $insert_stmt->error
    ]);
}

$insert_stmt->close();
exit();
?>