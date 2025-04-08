<?php
// ajax_handlers/update_task_positions.php
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
if (!isset($_POST['tasks']) || empty($_POST['tasks'])) {
    echo json_encode(['success' => false, 'message' => 'No tasks to update']);
    exit();
}

$tasks = json_decode($_POST['tasks'], true);
if (!is_array($tasks) || empty($tasks)) {
    echo json_encode(['success' => false, 'message' => 'Invalid task data']);
    exit();
}

// First, verify all tasks belong to boards owned by this user
$task_ids = array_column($tasks, 'task_id');
$task_ids_str = implode(',', array_map('intval', $task_ids));

$check_sql = "SELECT t.task_id 
              FROM Planotajs_Tasks t
              JOIN Planotajs_Boards b ON t.board_id = b.board_id
              WHERE t.task_id IN ({$task_ids_str}) AND b.user_id = ? AND t.is_deleted = 0 AND b.is_deleted = 0";

$check_stmt = $connection->prepare($check_sql);
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

$valid_task_ids = [];
while ($row = $check_result->fetch_assoc()) {
    $valid_task_ids[] = $row['task_id'];
}
$check_stmt->close();

// Start transaction
$connection->begin_transaction();
$success = true;

// Update each task's position and status
foreach ($tasks as $task) {
    if (!in_array($task['task_id'], $valid_task_ids)) {
        continue; // Skip tasks that don't belong to this user
    }
    
    $update_sql = "UPDATE Planotajs_Tasks 
                  SET task_order = ?, task_status = ? 
                  WHERE task_id = ?";
                  
    $update_stmt = $connection->prepare($update_sql);
    $update_stmt->bind_param("isi", $task['task_order'], $task['task_status'], $task['task_id']);
    
    if (!$update_stmt->execute()) {
        $success = false;
        break;
    }
    $update_stmt->close();
}

// Commit or rollback based on success
if ($success) {
    $connection->commit();
    echo json_encode(['success' => true]);
} else {
    $connection->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating task positions']);
}
?>