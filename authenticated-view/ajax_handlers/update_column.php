<?php
// ajax_handlers/update_column.php
session_start();
require_once '../../admin/database/connection.php';
require_once '../core/functions.php'; // For log_and_notify and helpers

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
$actor_user_id = $_SESSION['user_id'];

if (!isset($_POST['column_id'], $_POST['column_name'], $_POST['board_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields for updating column.']);
    exit();
}

$column_id = intval($_POST['column_id']);
$new_column_name = trim($_POST['column_name']);
$board_id = intval($_POST['board_id']);

if (empty($new_column_name)) {
    echo json_encode(['success' => false, 'message' => 'Column name cannot be empty.']);
    exit();
}

// Fetch old column name for logging
$old_column_name = "a column";
$stmt_old_name = $connection->prepare("SELECT column_name FROM Planner_Columns WHERE column_id = ? AND board_id = ?");
if($stmt_old_name){
    $stmt_old_name->bind_param("ii", $column_id, $board_id);
    $stmt_old_name->execute();
    $res_old_name = $stmt_old_name->get_result();
    if($old_data = $res_old_name->fetch_assoc()){
        $old_column_name = $old_data['column_name'];
    }
    $stmt_old_name->close();
}


$perm_check_sql = "SELECT pc.column_id FROM Planner_Columns pc
                   JOIN Planner_Boards b ON pc.board_id = b.board_id
                   LEFT JOIN Planner_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE pc.column_id = ? AND pc.board_id = ? AND (b.user_id = ? OR c.permission_level IN ('edit', 'admin'))
                   AND pc.is_deleted = 0";
$perm_stmt = $connection->prepare($perm_check_sql);
$perm_stmt->bind_param("iiii", $actor_user_id, $column_id, $board_id, $actor_user_id);
$perm_stmt->execute();
$perm_result = $perm_stmt->get_result(); 

if ($perm_result->num_rows === 0) {
    $perm_stmt->close(); 
    echo json_encode(['success' => false, 'message' => 'Column not found or not authorized for update.']);
    exit();
}
$perm_stmt->close(); 

$sql = "UPDATE Planner_Columns SET column_name = ? WHERE column_id = ? AND board_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("sii", $new_column_name, $column_id, $board_id);

if ($stmt->execute()) {
    update_board_last_activity_timestamp($connection, $board_id);

    // --- NOTIFICATION LOGIC ---
    if ($old_column_name !== $new_column_name) { // Only notify if name actually changed
        $board_actor_info = get_board_and_actor_info($connection, $board_id, $actor_user_id);
        $activity_description = htmlspecialchars($board_actor_info['actor_username']) . " renamed column \"" . htmlspecialchars($old_column_name) . "\" to \"" . htmlspecialchars($new_column_name) . "\" on board \"" . htmlspecialchars($board_actor_info['board_name']) . "\".";
        $recipients = get_board_associated_user_ids($connection, $board_id);
        // Fetch column identifier for link
        $col_identifier = "unknown-col";
        $stmt_col_ident = $connection->prepare("SELECT column_identifier FROM Planner_Columns WHERE column_id = ?");
        if($stmt_col_ident){
            $stmt_col_ident->bind_param("i", $column_id);
            $stmt_col_ident->execute();
            $res_col_ident = $stmt_col_ident->get_result();
            if($col_data_ident = $res_col_ident->fetch_assoc()){
                $col_identifier = $col_data_ident['column_identifier'];
            }
            $stmt_col_ident->close();
        }
        $link_to_board = "kanban.php?board_id=" . $board_id . "#column-" . $col_identifier;

        log_and_notify(
            $connection,
            $board_id,
            $actor_user_id,
            'column_updated',
            $activity_description,
            $column_id, 
            'column',   
            $recipients,
            $link_to_board
        );
    }
    // --- END NOTIFICATION LOGIC ---

    echo json_encode(['success' => true]);
} else {
    error_log("Error updating column: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Error updating column.']);
}
$stmt->close();
$connection->close();
?>