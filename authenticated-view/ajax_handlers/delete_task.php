<?php
// ajax_handlers/delete_task.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Include database connection
require_once '../../admin/database/connection.php';

$user_id = $_SESSION['user_id'];

// Validate input
if (!isset($_POST['task_id'])) {
    echo json_encode(['success' => false, 'message' => 'Task ID is required']);
    exit();
}

$task_id = intval($_POST['task_id']);

// First, check if this task belongs to one of the user's boards
$check_sql = "SELECT t.task_id 
              FROM Planotajs_Tasks t
              JOIN Planotajs_Boards b ON t.board_id = b.board_id
              WHERE t.task_id = ? AND b.user_id = ? AND t.is_deleted = 0 AND b.is_deleted = 0";

$check_stmt = $connection->prepare($check_sql);
$check_stmt->bind_param("ii", $task_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Task not found or not authorized']);
    exit();
}
$check_stmt->close();

// Soft delete the task
$delete_sql = "UPDATE Planotajs_Tasks SET is_deleted = 1 WHERE task_id = ?";
$delete_stmt = $connection->prepare($delete_sql);
$delete_stmt->bind_param("i", $task_id);

if ($delete_stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting task: ' . $connection->error]);
}
$delete_stmt->close();
?>