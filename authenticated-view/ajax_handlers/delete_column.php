<?php
// ajax_handlers/delete_column.php
session_start();
require_once '../../admin/database/connection.php';
require_once '../core/functions.php'; // For log_and_notify and helpers

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
$actor_user_id = $_SESSION['user_id'];

if (!isset($_POST['column_id'], $_POST['board_id'])) {
    echo json_encode(['success' => false, 'message' => 'Column ID and Board ID are required.']);
    exit();
}

$column_id = intval($_POST['column_id']);
$board_id = intval($_POST['board_id']);

// Fetch column name BEFORE deleting for notification message
$column_name_for_log = "a column";
$stmt_col_name = $connection->prepare("SELECT column_name FROM Planner_Columns WHERE column_id = ? AND board_id = ?");
if ($stmt_col_name) {
    $stmt_col_name->bind_param("ii", $column_id, $board_id);
    $stmt_col_name->execute();
    $res_col_name = $stmt_col_name->get_result();
    if ($col_data = $res_col_name->fetch_assoc()) {
        $column_name_for_log = $col_data['column_name'];
    }
    $stmt_col_name->close();
}


$perm_check_sql = "SELECT pc.column_id FROM Planner_Columns pc
                   JOIN Planner_Boards b ON pc.board_id = b.board_id
                   LEFT JOIN Planner_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE pc.column_id = ? AND pc.board_id = ?
                   AND (b.user_id = ? OR c.permission_level = 'admin') 
                   AND pc.is_deleted = 0"; // Only owner or board admin can delete columns
$perm_stmt = $connection->prepare($perm_check_sql);
$perm_stmt->bind_param("iiii", $actor_user_id, $column_id, $board_id, $actor_user_id);
$perm_stmt->execute();
$perm_result = $perm_stmt->get_result(); 

if ($perm_result->num_rows === 0) {
    $perm_stmt->close(); 
    echo json_encode(['success' => false, 'message' => 'Column not found or not authorized.']);
    exit();
}
$perm_stmt->close(); 


$connection->begin_transaction();
$operation_successful = false; 
try {
    $delete_tasks_sql = "UPDATE Planner_Tasks SET is_deleted = 1 WHERE column_id = ? AND board_id = ?";
    $delete_tasks_stmt = $connection->prepare($delete_tasks_sql);
    $delete_tasks_stmt->bind_param("ii", $column_id, $board_id);
    $delete_tasks_stmt->execute(); 
    $delete_tasks_stmt->close();

    $delete_column_sql = "UPDATE Planner_Columns SET is_deleted = 1 WHERE column_id = ? AND board_id = ?";
    $delete_column_stmt = $connection->prepare($delete_column_sql);
    $delete_column_stmt->bind_param("ii", $column_id, $board_id);
    $delete_column_stmt->execute(); 
    $delete_column_stmt->close();

    $connection->commit();
    $operation_successful = true; 

} catch (Exception $e) {
    $connection->rollback();
    error_log("Error deleting column: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting column.']);
    $connection->close();
    exit(); 
}

if ($operation_successful) {
    update_board_last_activity_timestamp($connection, $board_id);

    // --- NOTIFICATION LOGIC ---
    $board_actor_info = get_board_and_actor_info($connection, $board_id, $actor_user_id);
    $activity_description = htmlspecialchars($board_actor_info['actor_username']) . " deleted column \"" . htmlspecialchars($column_name_for_log) . "\" from board \"" . htmlspecialchars($board_actor_info['board_name']) . "\".";
    $recipients = get_board_associated_user_ids($connection, $board_id);
    $link_to_board = "kanban.php?board_id=" . $board_id;

    log_and_notify(
        $connection,
        $board_id,
        $actor_user_id,
        'column_deleted',
        $activity_description,
        $column_id, // related_entity_id (the deleted column's ID)
        'column',   // related_entity_type
        $recipients,
        $link_to_board
    );
    // --- END NOTIFICATION LOGIC ---

    echo json_encode(['success' => true]);
}

$connection->close();
?>