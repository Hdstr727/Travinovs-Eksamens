<?php
session_start();
require_once '../../admin/database/connection.php'; // Adjust path

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
$board_id = intval($_POST['board_id']); // For security check

// Verify user is owner of the board this column belongs to
$perm_check_sql = "SELECT pc.column_id FROM Planotajs_Columns pc
                   JOIN Planotajs_Boards b ON pc.board_id = b.board_id
                   LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE pc.column_id = ? AND pc.board_id = ?
                   AND (b.user_id = ? OR c.permission_level = 'admin')
                   AND pc.is_deleted = 0";
$perm_stmt = $connection->prepare($perm_check_sql);
$perm_stmt->bind_param("iiii", $user_id, $column_id, $board_id, $user_id);
$perm_stmt->execute();
if ($perm_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Column not found or not authorized (only owner can delete columns).']);
    exit();
}
$perm_stmt->close();


$connection->begin_transaction();
try {
    // 1. Soft delete tasks in this column
    $delete_tasks_sql = "UPDATE Planotajs_Tasks SET is_deleted = 1 WHERE column_id = ? AND board_id = ?";
    $delete_tasks_stmt = $connection->prepare($delete_tasks_sql);
    $delete_tasks_stmt->bind_param("ii", $column_id, $board_id);
    $delete_tasks_stmt->execute();
    $delete_tasks_stmt->close();

    // 2. Soft delete the column itself
    $delete_column_sql = "UPDATE Planotajs_Columns SET is_deleted = 1 WHERE column_id = ? AND board_id = ?";
    $delete_column_stmt = $connection->prepare($delete_column_sql);
    $delete_column_stmt->bind_param("ii", $column_id, $board_id);
    $delete_column_stmt->execute();
    $delete_column_stmt->close();

    $connection->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $connection->rollback();
    echo json_encode(['success' => false, 'message' => 'Error deleting column: ' . $e->getMessage()]);
}
$connection->close();
?>