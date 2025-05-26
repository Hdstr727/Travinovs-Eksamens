<?php
// ajax_handlers/delete_column.php
session_start();
require_once '../../admin/database/connection.php';
require_once 'utils/update_board_activity.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
$user_id = $_SESSION['user_id'];

if (!isset($_POST['column_id'], $_POST['board_id'])) {
    echo json_encode(['success' => false, 'message' => 'Column ID and Board ID are required.']);
    exit();
}

$column_id = intval($_POST['column_id']);
$board_id = intval($_POST['board_id']);

// ... (permission check - $perm_stmt->close() is inside the if block, should be outside if it proceeds)
$perm_check_sql = "SELECT pc.column_id FROM Planotajs_Columns pc
                   JOIN Planotajs_Boards b ON pc.board_id = b.board_id
                   LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE pc.column_id = ? AND pc.board_id = ?
                   AND (b.user_id = ? OR c.permission_level = 'admin')
                   AND pc.is_deleted = 0";
$perm_stmt = $connection->prepare($perm_check_sql);
$perm_stmt->bind_param("iiii", $user_id, $column_id, $board_id, $user_id);
$perm_stmt->execute();
$perm_result = $perm_stmt->get_result(); // Get result before checking num_rows

if ($perm_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Column not found or not authorized.']);
    $perm_stmt->close(); // Close here if exiting
    exit();
}
$perm_stmt->close(); // Close here if proceeding


$connection->begin_transaction();
$operation_successful = false; // Flag
try {
    // 1. Soft delete tasks in this column
    $delete_tasks_sql = "UPDATE Planotajs_Tasks SET is_deleted = 1 WHERE column_id = ? AND board_id = ?";
    $delete_tasks_stmt = $connection->prepare($delete_tasks_sql);
    $delete_tasks_stmt->bind_param("ii", $column_id, $board_id);
    $delete_tasks_stmt->execute(); // Assume success or handle error
    $delete_tasks_stmt->close();

    // 2. Soft delete the column itself
    $delete_column_sql = "UPDATE Planotajs_Columns SET is_deleted = 1 WHERE column_id = ? AND board_id = ?";
    $delete_column_stmt = $connection->prepare($delete_column_sql);
    $delete_column_stmt->bind_param("ii", $column_id, $board_id);
    $delete_column_stmt->execute(); // Assume success or handle error
    $delete_column_stmt->close();

    $connection->commit();
    $operation_successful = true; // Set flag on successful commit

} catch (Exception $e) {
    $connection->rollback();
    echo json_encode(['success' => false, 'message' => 'Error deleting column: ' . $e->getMessage()]);
    $connection->close();
    exit(); // Exit after sending error
}

if ($operation_successful) {
    // Update board activity timestamp
    update_board_last_activity_timestamp($connection, $board_id);
    echo json_encode(['success' => true]);
}
// No else needed, as failure cases exit above

$connection->close();
?>