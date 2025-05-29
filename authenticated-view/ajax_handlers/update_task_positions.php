<?php
// ajax_handlers/update_task_positions.php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
require_once '../../admin/database/connection.php';
require_once 'utils/update_board_activity.php';
$user_id = $_SESSION['user_id'];

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

// ... (permission check - $perm_stmt->close() is inside the if block, should be outside if it proceeds)
$perm_check_sql = "SELECT b.board_id FROM Planner_Boards b
                   LEFT JOIN Planner_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE b.board_id = ? AND (b.user_id = ? OR c.permission_level IN ('edit', 'admin'))";
$perm_stmt = $connection->prepare($perm_check_sql);
$perm_stmt->bind_param("iii", $user_id, $board_id, $user_id);
$perm_stmt->execute();
$perm_result = $perm_stmt->get_result(); // Get result

if ($perm_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Board not found or not authorized for task updates.']);
    $perm_stmt->close(); // Close here
    exit();
}
$perm_stmt->close(); // Close here


$connection->begin_transaction();
$all_updates_successful = true; // Renamed for clarity

foreach ($tasks_data as $task) {
    // ... (task data validation) ...
    $update_sql = "UPDATE Planner_Tasks
                  SET task_order = ?, column_id = ?
                  WHERE task_id = ? AND board_id = ? AND is_deleted = 0";
    $update_stmt = $connection->prepare($update_sql);
    $update_stmt->bind_param("iiii", $task['task_order'], $task['column_id'], $task['task_id'], $board_id);

    if (!$update_stmt->execute()) {
        $all_updates_successful = false;
        error_log("Error updating task position: " . $update_stmt->error . " for task_id: " . $task['task_id']);
        $update_stmt->close(); // Close statement even on error
        break;
    }
    $update_stmt->close();
}

if ($all_updates_successful) {
    $connection->commit();
    // Update board activity timestamp
    update_board_last_activity_timestamp($connection, $board_id);
    echo json_encode(['success' => true]);
} else {
    $connection->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating task positions. Some data might be invalid.']);
}
$connection->close();
?>