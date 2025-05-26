<?php
// ajax_handlers/update_task_positions.php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
require_once '../../admin/database/connection.php';
$user_id = $_SESSION['user_id'];

// board_id is now sent for security check
if (!isset($_POST['tasks'], $_POST['board_id']) || empty($_POST['tasks'])) {
    echo json_encode(['success' => false, 'message' => 'No tasks to update or board_id missing.']);
    exit();
}

$tasks_data = json_decode($_POST['tasks'], true);
$board_id = intval($_POST['board_id']);

if (!is_array($tasks_data) || empty($tasks_data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid task data.']);
    exit();
}

// Verify user has permission on this board
$perm_check_sql = "SELECT b.board_id FROM Planotajs_Boards b
                   LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE b.board_id = ? AND (b.user_id = ? OR c.permission_level IN ('edit', 'admin'))";
$perm_stmt = $connection->prepare($perm_check_sql);
$perm_stmt->bind_param("iii", $user_id, $board_id, $user_id);
$perm_stmt->execute();
if ($perm_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Board not found or not authorized for task updates.']);
    exit();
}
$perm_stmt->close();


// Optional: Further validation to ensure all task_ids and column_ids belong to the specified board_id
// and are not deleted. This can be complex. For now, we trust the client sends valid data
// after the initial board permission check.

$connection->begin_transaction();
$success = true;

foreach ($tasks_data as $task) {
    if (!isset($task['task_id'], $task['task_order'], $task['column_id'])) {
        $success = false; // Invalid task structure
        break;
    }
    $task_id = intval($task['task_id']);
    $task_order = intval($task['task_order']);
    $column_id = intval($task['column_id']); // Now using column_id

    // Ensure the task actually belongs to the board (important if not fully validated above)
    // And the column_id is valid for this board
    $update_sql = "UPDATE Planotajs_Tasks
                  SET task_order = ?, column_id = ?
                  WHERE task_id = ? AND board_id = ? AND is_deleted = 0";
    // Check if column_id is valid for this board_id can be added to WHERE:
    // AND EXISTS (SELECT 1 FROM Planotajs_Columns pc WHERE pc.column_id = ? AND pc.board_id = ? AND pc.is_deleted = 0)

    $update_stmt = $connection->prepare($update_sql);
    $update_stmt->bind_param("iiii", $task_order, $column_id, $task_id, $board_id);

    if (!$update_stmt->execute()) {
        $success = false;
        error_log("Error updating task position: " . $update_stmt->error . " for task_id: " . $task_id);
        break;
    }
    $update_stmt->close();
}

if ($success) {
    $connection->commit();
    echo json_encode(['success' => true]);
} else {
    $connection->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating task positions. Some data might be invalid.']);
}
$connection->close();
?>