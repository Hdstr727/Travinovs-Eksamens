<?php
// ajax_handlers/delete_column_tasks.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Include database connection
require_once '../../admin/database/connection.php';

$user_id = $_SESSION['user_id'];

// Validate inputs
if (!isset($_POST['board_id']) || !isset($_POST['task_status'])) {
    echo json_encode(['success' => false, 'message' => 'Board ID and task status are required']);
    exit();
}

$board_id = intval($_POST['board_id']);
$task_status = $_POST['task_status'];

// Verify board belongs to user
$board_check_sql = "SELECT board_id FROM Planotajs_Boards WHERE board_id = ? AND user_id = ? AND is_deleted = 0";
$board_check_stmt = $connection->prepare($board_check_sql);
$board_check_stmt->bind_param("ii", $board_id, $user_id);
$board_check_stmt->execute();
$board_check_result = $board_check_stmt->get_result();

if ($board_check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Board not found or not authorized']);
    exit();
}
$board_check_stmt->close();

// Soft delete all tasks in this column/status
$delete_sql = "UPDATE Planotajs_Tasks SET is_deleted = 1 
               WHERE board_id = ? AND task_status = ? AND is_deleted = 0";
$delete_stmt = $connection->prepare($delete_sql);
$delete_stmt->bind_param("is", $board_id, $task_status);

if ($delete_stmt->execute()) {
    echo json_encode(['success' => true, 'affected_rows' => $delete_stmt->affected_rows]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting tasks: ' . $connection->error]);
}
$delete_stmt->close();
?>