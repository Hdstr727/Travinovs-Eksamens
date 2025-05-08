<?php
// File: ajax_handlers/get_messages.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
if (!isset($_GET['board_id']) || !is_numeric($_GET['board_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid board ID'
    ]);
    exit();
}

$board_id = intval($_GET['board_id']);
$user_id = $_SESSION['user_id'];
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

// Check if user has access to this board
$access_sql = "SELECT b.board_id 
              FROM Planotajs_Boards b
              LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id 
              WHERE b.board_id = ? 
              AND (b.user_id = ? OR c.user_id = ?)
              AND b.is_deleted = 0";

$access_stmt = $connection->prepare($access_sql);
$access_stmt->bind_param("iii", $board_id, $user_id, $user_id);
$access_stmt->execute();
$access_result = $access_stmt->get_result();

if ($access_result->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied'
    ]);
    exit();
}
$access_stmt->close();

// Get messages for the board
// If last_id is provided, only get newer messages
$message_sql = "SELECT m.message_id, m.user_id, u.username, u.full_name, m.message_text, m.created_at
               FROM Planotajs_ChatMessages m
               JOIN Planotajs_Users u ON m.user_id = u.user_id
               WHERE m.board_id = ? ";

if ($last_id > 0) {
    $message_sql .= "AND m.message_id > ? ";
}

$message_sql .= "ORDER BY m.created_at ASC LIMIT 100";

if ($last_id > 0) {
    $message_stmt = $connection->prepare($message_sql);
    $message_stmt->bind_param("ii", $board_id, $last_id);
} else {
    $message_stmt = $connection->prepare($message_sql);
    $message_stmt->bind_param("i", $board_id);
}

$message_stmt->execute();
$message_result = $message_stmt->get_result();
$messages = [];

while ($message = $message_result->fetch_assoc()) {
    // Use full name if available, otherwise username
    $display_name = !empty($message['full_name']) ? $message['full_name'] : $message['username'];
    
    $messages[] = [
        'message_id' => $message['message_id'],
        'user_id' => $message['user_id'],
        'username' => $display_name,
        'message_text' => $message['message_text'],
        'created_at' => $message['created_at'],
        'is_own' => ($message['user_id'] == $user_id)
    ];
}

$message_stmt->close();

echo json_encode([
    'success' => true,
    'messages' => $messages
]);
exit();
?>