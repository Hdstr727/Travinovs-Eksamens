<?php
// ajax_handlers/delete_task.php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
require_once '../../admin/database/connection.php';
require_once 'utils/update_board_activity.php';
$user_id = $_SESSION['user_id'];

if (!isset($_POST['task_id'], $_POST['board_id'])) {
    echo json_encode(['success' => false, 'message' => 'Task ID and Board ID are required.']);
    exit();
}

$task_id = intval($_POST['task_id']);
$board_id = intval($_POST['board_id']);

// ... (permission check - $check_stmt->close() is inside the if block, should be outside if it proceeds)
$check_sql = "SELECT t.task_id
              FROM Planotajs_Tasks t
              JOIN Planotajs_Boards b ON t.board_id = b.board_id
              LEFT JOIN Planotajs_Collaborators collab ON b.board_id = collab.board_id AND collab.user_id = ?
              WHERE t.task_id = ? AND t.board_id = ? AND t.is_deleted = 0
              AND (b.user_id = ? OR collab.permission_level IN ('edit', 'admin'))";
$check_stmt = $connection->prepare($check_sql);
$check_stmt->bind_param("iiii", $user_id, $task_id, $board_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Task not found or not authorized for this board.']);
    $check_stmt->close(); // Close here if exiting
    exit();
}
$check_stmt->close(); // Close here if proceeding

// Soft delete the task
$delete_sql = "UPDATE Planotajs_Tasks SET is_deleted = 1 WHERE task_id = ? AND board_id = ?";
$delete_stmt = $connection->prepare($delete_sql);
$delete_stmt->bind_param("ii", $task_id, $board_id);

if ($delete_stmt->execute()) {
    // Update board activity timestamp
    update_board_last_activity_timestamp($connection, $board_id);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting task: ' . $delete_stmt->error]);
}
$delete_stmt->close();
$connection->close();
?>