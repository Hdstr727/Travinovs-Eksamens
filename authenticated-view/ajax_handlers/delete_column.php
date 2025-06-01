<?php
// ajax_handlers/delete_column.php
session_start();
require_once '../../admin/database/connection.php';
require_once '../core/functions.php'; // For log_and_notify and helpers
require_once __DIR__ . '/utils/update_board_activity.php'; // Make sure this is included

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

// --- START: Permission Check (Owner or Admin can delete columns) ---
$permission_level = 'read'; // Default
$board_info_sql = "SELECT user_id, is_archived FROM Planner_Boards WHERE board_id = ? AND is_deleted = 0";
$stmt_board_info = $connection->prepare($board_info_sql);

if (!$stmt_board_info) {
    error_log("Delete Column: Prepare board_info_sql failed: " . $connection->error);
    echo json_encode(['success' => false, 'message' => 'Database error (b_info).']);
    if ($connection) $connection->close();
    exit();
}
$stmt_board_info->bind_param("i", $board_id);
$stmt_board_info->execute();
$board_info_res = $stmt_board_info->get_result();

if ($board_data_perm = $board_info_res->fetch_assoc()) {
    if ($board_data_perm['is_archived'] == 1) {
        echo json_encode(['success' => false, 'message' => 'This project is archived. Columns cannot be deleted.']);
        $stmt_board_info->close(); if ($connection) $connection->close(); exit();
    }
    if ($board_data_perm['user_id'] == $actor_user_id) {
        $permission_level = 'owner';
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Board not found.']);
    $stmt_board_info->close(); if ($connection) $connection->close(); exit();
}
$stmt_board_info->close();

if ($permission_level !== 'owner') {
    $collab_check_sql = "SELECT permission_level FROM Planner_Collaborators WHERE board_id = ? AND user_id = ?";
    $stmt_collab_check = $connection->prepare($collab_check_sql);
    if (!$stmt_collab_check) {
        error_log("Delete Column: Prepare collab_check_sql failed: " . $connection->error);
        echo json_encode(['success' => false, 'message' => 'Database error (c_check).']);
        if ($connection) $connection->close();
        exit();
    }
    $stmt_collab_check->bind_param("ii", $board_id, $actor_user_id);
    $stmt_collab_check->execute();
    $collab_res = $stmt_collab_check->get_result();
    if ($collab_data_perm = $collab_res->fetch_assoc()) {
        $permission_level = $collab_data_perm['permission_level'];
    }
    $stmt_collab_check->close();
}

// Now check if the determined permission_level allows deletion
// For deleting columns, typically only 'owner' or 'admin' should be allowed.
if (!in_array($permission_level, ['owner', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete columns on this project.']);
    if ($connection) $connection->close(); exit();
}
// --- END: Permission Check ---


// Fetch column name BEFORE deleting for notification message
$column_name_for_log = "a column";
// Ensure we only fetch name if column is not already deleted
$stmt_col_name = $connection->prepare("SELECT column_name FROM Planner_Columns WHERE column_id = ? AND board_id = ? AND is_deleted = 0");
if ($stmt_col_name) {
    $stmt_col_name->bind_param("ii", $column_id, $board_id);
    $stmt_col_name->execute();
    $res_col_name = $stmt_col_name->get_result();
    if ($col_data = $res_col_name->fetch_assoc()) {
        $column_name_for_log = $col_data['column_name'];
    } else {
        // If column name not found here (e.g., already soft-deleted by another process, or ID is wrong),
        // the delete operation below will likely affect 0 rows for the column itself.
        // The permission check above should ideally prevent operating on non-existent/unauthorized columns.
        error_log("Delete Column: Column name not found for column_id $column_id on board_id $board_id for logging (might be already deleted or invalid).");
    }
    $stmt_col_name->close();
} else {
    error_log("Delete Column: Prepare stmt_col_name failed: " . $connection->error);
}


$connection->begin_transaction();
$operation_successful = false;
try {
    // Soft delete tasks in the column
    $delete_tasks_sql = "UPDATE Planner_Tasks SET is_deleted = 1, updated_at = NOW() WHERE column_id = ? AND board_id = ?";
    $delete_tasks_stmt = $connection->prepare($delete_tasks_sql);
    if (!$delete_tasks_stmt) throw new Exception("Prepare delete_tasks_sql failed: " . $connection->error);
    $delete_tasks_stmt->bind_param("ii", $column_id, $board_id);
    $delete_tasks_stmt->execute();
    // We don't strictly need to check affected_rows for tasks, as the column might be empty.
    $delete_tasks_stmt->close();

    // Soft delete the column itself
    $delete_column_sql = "UPDATE Planner_Columns SET is_deleted = 1, updated_at = NOW() WHERE column_id = ? AND board_id = ? AND is_deleted = 0"; // Add is_deleted = 0
    $delete_column_stmt = $connection->prepare($delete_column_sql);
    if (!$delete_column_stmt) throw new Exception("Prepare delete_column_sql failed: " . $connection->error);
    $delete_column_stmt->bind_param("ii", $column_id, $board_id);
    $delete_column_stmt->execute();

    if ($delete_column_stmt->affected_rows > 0) {
        $operation_successful = true;
    } else {
        // If no rows affected, the column might have already been deleted or the column_id was invalid for this board.
        // The permission check should have caught most invalid scenarios.
        // If $column_name_for_log was "a column", it's a stronger indicator the column didn't exist initially.
        throw new Exception("Column not found for deletion or already deleted (affected_rows = 0).");
    }
    $delete_column_stmt->close();

    // Update board activity timestamp if operation was successful
    if (!update_board_last_activity_timestamp($connection, $board_id)) {
        error_log("Failed to update board activity timestamp for board_id: $board_id after column deletion.");
        // Decide if this should cause a rollback. For now, it doesn't if the main ops succeeded.
    }

    $connection->commit();

} catch (Exception $e) {
    $connection->rollback();
    error_log("Error deleting column (column_id: $column_id, board_id: $board_id): " . $e->getMessage());
    // Provide a more user-friendly message if it's the "affected_rows = 0" case
    if (strpos($e->getMessage(), "affected_rows = 0") !== false) {
        echo json_encode(['success' => false, 'message' => 'Column could not be deleted. It might have been already removed or does not exist.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error processing column deletion.']);
    }
    if ($connection) $connection->close();
    exit();
}

if ($operation_successful) {
    // --- NOTIFICATION LOGIC ---
    $board_actor_info = get_board_and_actor_info($connection, $board_id, $actor_user_id);
    $activity_description = htmlspecialchars($board_actor_info['actor_username']) . " deleted column \"" . htmlspecialchars($column_name_for_log) . "\" (and its tasks) from board \"" . htmlspecialchars($board_actor_info['board_name']) . "\".";
    $recipients = get_board_associated_user_ids($connection, $board_id);
    $link_to_board = "kanban.php?board_id=" . $board_id;

    log_and_notify(
        $connection,
        $board_id,
        $actor_user_id,
        'column_deleted',
        $activity_description,
        $column_id,
        'column',
        $recipients,
        $link_to_board
    );
    // --- END NOTIFICATION LOGIC ---

    echo json_encode(['success' => true]);
} else {
    // This case should be rare if exceptions are handled and operation_successful is set correctly.
    // It might be reached if the commit fails for some reason after operations were thought to be successful.
    echo json_encode(['success' => false, 'message' => 'Column deletion process completed but status unclear. Please verify.']);
}

if ($connection) $connection->close();
?>