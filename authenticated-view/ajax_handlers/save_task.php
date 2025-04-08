<?php
// ajax_handlers/save_task.php
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
if (!isset($_POST['board_id']) || !isset($_POST['task_name']) || !isset($_POST['task_status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$board_id = intval($_POST['board_id']);
$task_name = trim($_POST['task_name']);
$task_description = isset($_POST['task_description']) ? trim($_POST['task_description']) : '';
$task_status = $_POST['task_status'];
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
$is_system = isset($_POST['is_system']) ? 1 : 0;

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

// Get the current max order for this status
$order_sql = "SELECT MAX(task_order) as max_order FROM Planotajs_Tasks 
              WHERE board_id = ? AND task_status = ? AND is_deleted = 0";
$order_stmt = $connection->prepare($order_sql);
$order_stmt->bind_param("is", $board_id, $task_status);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order_row = $order_result->fetch_assoc();
$task_order = ($order_row['max_order'] !== null) ? $order_row['max_order'] + 1 : 0;
$order_stmt->close();

// Check if this is a new task or update
if (isset($_POST['task_id']) && !empty($_POST['task_id'])) {
    // Update existing task
    $task_id = intval($_POST['task_id']);
    
    $update_sql = "UPDATE Planotajs_Tasks SET 
                  task_name = ?, 
                  task_description = ?, 
                  task_status = ?,
                  due_date = ?,
                  priority = ?
                  WHERE task_id = ? AND board_id = ? AND is_deleted = 0";
    
    $update_stmt = $connection->prepare($update_sql);
    $update_stmt->bind_param("sssssii", $task_name, $task_description, $task_status, $due_date, $priority, $task_id, $board_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'task_id' => $task_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating task: ' . $connection->error]);
    }
    $update_stmt->close();
} else {
    // Create new task
    $insert_sql = "INSERT INTO Planotajs_Tasks 
                  (board_id, task_name, task_description, task_status, task_order, due_date, priority) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $insert_stmt = $connection->prepare($insert_sql);
    $insert_stmt->bind_param("isssiss", $board_id, $task_name, $task_description, $task_status, $task_order, $due_date, $priority);
    
    if ($insert_stmt->execute()) {
        $task_id = $insert_stmt->insert_id;
        echo json_encode(['success' => true, 'task_id' => $task_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating task: ' . $connection->error]);
    }
    $insert_stmt->close();
}
?>