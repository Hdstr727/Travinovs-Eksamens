<?php
// ajax_handlers/save_task.php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
require_once '../../admin/database/connection.php';
$user_id = $_SESSION['user_id'];

// Validate inputs: board_id, task_name, column_id are essential
if (!isset($_POST['board_id'], $_POST['task_name'], $_POST['column_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields (board_id, task_name, column_id).']);
    exit();
}

$board_id = intval($_POST['board_id']);
$task_name = trim($_POST['task_name']);
$column_id = intval($_POST['column_id']); // Now receiving column_id
$task_description = isset($_POST['task_description']) ? trim($_POST['task_description']) : '';
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
// is_system is not used in this flow anymore for column creation

// Verify board belongs to user (or user has write access) AND column belongs to board
$perm_check_sql = "SELECT b.board_id FROM Planotajs_Boards b
                   JOIN Planotajs_Columns pc ON b.board_id = pc.board_id
                   LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE b.board_id = ? AND pc.column_id = ? AND pc.is_deleted = 0
                   AND (b.user_id = ? OR c.permission_level IN ('edit', 'admin'))";
$perm_stmt = $connection->prepare($perm_check_sql);
$perm_stmt->bind_param("iiii", $user_id, $board_id, $column_id, $user_id);
$perm_stmt->execute();
if ($perm_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Board/Column not found or not authorized.']);
    exit();
}
$perm_stmt->close();


$task_id = isset($_POST['task_id']) && !empty($_POST['task_id']) ? intval($_POST['task_id']) : null;

if ($task_id) {
    // Update existing task
    // When updating, we might change column, so task_order needs recalculation if column changes.
    // For simplicity, this example doesn't re-calculate order on column change, assumes it's handled by drag-drop.
    // If task_order is not sent, it means it's not being reordered, just content update.
    $update_sql = "UPDATE Planotajs_Tasks SET
                  task_name = ?,
                  task_description = ?,
                  column_id = ?, -- Using column_id
                  due_date = ?,
                  priority = ?
                  -- task_order = ? (only if you are also updating order here)
                  WHERE task_id = ? AND board_id = ? AND is_deleted = 0";
    $update_stmt = $connection->prepare($update_sql);
    // Add task_order to bind_param if you update it here: "ssisssii"
    $update_stmt->bind_param("ssisssi", $task_name, $task_description, $column_id, $due_date, $priority, $task_id, $board_id);

    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'task_id' => $task_id, 'column_id' => $column_id]); // Return column_id
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating task: ' . $update_stmt->error]);
    }
    $update_stmt->close();
} else {
    // Create new task
    // Get the current max order for this column_id
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
        echo json_encode(['success' => true, 'task_id' => $new_task_id, 'column_id' => $column_id, 'task_order' => $task_order]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating task: ' . $insert_stmt->error]);
    }
    $insert_stmt->close();
}
$connection->close();
?>