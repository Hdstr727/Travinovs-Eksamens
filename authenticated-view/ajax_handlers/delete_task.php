<?php
// ajax_handlers/delete_task.php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
require_once '../../admin/database/connection.php';
require_once '../core/functions.php'; // For log_and_notify and helpers

$actor_user_id = $_SESSION['user_id'];

if (!isset($_POST['task_id'], $_POST['board_id'])) {
    echo json_encode(['success' => false, 'message' => 'Task ID and Board ID are required.']);
    exit();
}

$task_id = intval($_POST['task_id']);
$board_id = intval($_POST['board_id']);

// Fetch task name BEFORE deleting for notification message
$task_name_for_log = "a task";
$stmt_task_name = $connection->prepare("SELECT task_name FROM Planotajs_Tasks WHERE task_id = ? AND board_id = ?");
if ($stmt_task_name) {
    $stmt_task_name->bind_param("ii", $task_id, $board_id);
    $stmt_task_name->execute();
    $res_task_name = $stmt_task_name->get_result();
    if ($task_data = $res_task_name->fetch_assoc()) {
        $task_name_for_log = $task_data['task_name'];
    }
    $stmt_task_name->close();
}

$check_sql = "SELECT t.task_id
              FROM Planotajs_Tasks t
              JOIN Planotajs_Boards b ON t.board_id = b.board_id
              LEFT JOIN Planotajs_Collaborators collab ON b.board_id = collab.board_id AND collab.user_id = ?
              WHERE t.task_id = ? AND t.board_id = ? AND t.is_deleted = 0
              AND (b.user_id = ? OR collab.permission_level IN ('edit', 'admin'))";
$check_stmt = $connection->prepare($check_sql);
$check_stmt->bind_param("iiii", $actor_user_id, $task_id, $board_id, $actor_user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $check_stmt->close(); 
    echo json_encode(['success' => false, 'message' => 'Task not found or not authorized for this board.']);
    exit();
}
$check_stmt->close(); 

$delete_sql = "UPDATE Planotajs_Tasks SET is_deleted = 1 WHERE task_id = ? AND board_id = ?";
$delete_stmt = $connection->prepare($delete_sql);
$delete_stmt->bind_param("ii", $task_id, $board_id);

if ($delete_stmt->execute()) {
    update_board_last_activity_timestamp($connection, $board_id);

    // --- NOTIFICATION LOGIC ---
    $board_actor_info = get_board_and_actor_info($connection, $board_id, $actor_user_id);
    $activity_description = htmlspecialchars($board_actor_info['actor_username']) . " deleted task \"" . htmlspecialchars($task_name_for_log) . "\" from board \"" . htmlspecialchars($board_actor_info['board_name']) . "\".";
    $recipients = get_board_associated_user_ids($connection, $board_id);
    $link_to_board = "kanban.php?board_id=" . $board_id; // Link to the board, task is gone

    log_and_notify(
        $connection,
        $board_id,
        $actor_user_id,
        'task_deleted',
        $activity_description,
        $task_id, 
        'task',   
        $recipients,
        $link_to_board 
    );
    // --- END NOTIFICATION LOGIC ---

    echo json_encode(['success' => true]);
} else {
    error_log("Error deleting task: " . $delete_stmt->error);
    echo json_encode(['success' => false, 'message' => 'Error deleting task.']);
}
$delete_stmt->close();
$connection->close();
?>