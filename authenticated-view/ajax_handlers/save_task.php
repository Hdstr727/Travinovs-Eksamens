<?php
// ajax_handlers/save_task.php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
require_once '../../admin/database/connection.php';
require_once 'utils/update_board_activity.php';
$user_id = $_SESSION['user_id'];

if (!isset($_POST['board_id'], $_POST['task_name'], $_POST['column_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields (board_id, task_name, column_id).']);
    exit();
}

$board_id = intval($_POST['board_id']);
$task_name = trim($_POST['task_name']);
$column_id = intval($_POST['column_id']);
$task_description = isset($_POST['task_description']) ? trim($_POST['task_description']) : '';
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';

// ... (permission check - $perm_stmt->close() is inside the if block, should be outside if it proceeds)
$perm_check_sql = "SELECT b.board_id FROM Planotajs_Boards b
                   JOIN Planotajs_Columns pc ON b.board_id = pc.board_id
                   LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE b.board_id = ? AND pc.column_id = ? AND pc.is_deleted = 0
                   AND (b.user_id = ? OR c.permission_level IN ('edit', 'admin'))";
$perm_stmt = $connection->prepare($perm_check_sql);
$perm_stmt->bind_param("iiii", $user_id, $board_id, $column_id, $user_id);
$perm_stmt->execute();
$perm_result = $perm_stmt->get_result(); // Get result

if ($perm_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Board/Column not found or not authorized.']);
    $perm_stmt->close(); // Close here
    exit();
}
$perm_stmt->close(); // Close here


$task_id = isset($_POST['task_id']) && !empty($_POST['task_id']) ? intval($_POST['task_id']) : null;
$operation_successful = false;
$response_data = [];

if ($task_id) {
    $update_sql = "UPDATE Planotajs_Tasks SET
                  task_name = ?, task_description = ?, column_id = ?, due_date = ?, priority = ?
                  WHERE task_id = ? AND board_id = ? AND is_deleted = 0";
    $update_stmt = $connection->prepare($update_sql);
    $update_stmt->bind_param("ssisssi", $task_name, $task_description, $column_id, $due_date, $priority, $task_id, $board_id);

    if ($update_stmt->execute()) {
        $operation_successful = true;
        $response_data = ['success' => true, 'task_id' => $task_id, 'column_id' => $column_id];
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating task: ' . $update_stmt->error]);
        $update_stmt->close(); $connection->close(); exit();
    }
    $update_stmt->close();
} else {
    $order_sql = "SELECT MAX(task_order) as max_order FROM Planotajs_Tasks
                  WHERE board_id = ? AND column_id = ? AND is_deleted = 0";
    $order_stmt = $connection->prepare($order_sql);
    $order_stmt->bind_param("ii", $board_id, $column_id);
    $order_stmt->execute();
    $order_result = $order_stmt->get_result();
    $order_row = $order_result->fetch_assoc();
    $task_order = ($order_row['max_order'] !== null) ? $order_row['max_order'] + 1 : 0;
    $order_stmt->close();

    $insert_sql = "INSERT INTO Planotajs_Tasks
                  (board_id, task_name, task_description, column_id, task_order, due_date, priority)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $connection->prepare($insert_sql);
    $insert_stmt->bind_param("ississs", $board_id, $task_name, $task_description, $column_id, $task_order, $due_date, $priority);

    if ($insert_stmt->execute()) {
        $new_task_id = $insert_stmt->insert_id;
        $operation_successful = true;
        $response_data = ['success' => true, 'task_id' => $new_task_id, 'column_id' => $column_id, 'task_order' => $task_order];
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating task: ' . $insert_stmt->error]);
        $insert_stmt->close(); $connection->close(); exit();
    }
    $insert_stmt->close();
}

if ($operation_successful) {
    update_board_last_activity_timestamp($connection, $board_id);
    echo json_encode($response_data);
}

$connection->close();
?>