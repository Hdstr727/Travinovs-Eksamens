<?php
session_start();
require_once '../../admin/database/connection.php'; // Adjust path

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
$user_id = $_SESSION['user_id'];

if (!isset($_POST['column_id'], $_POST['column_name'], $_POST['board_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

$column_id = intval($_POST['column_id']);
$column_name = trim($_POST['column_name']);
$board_id = intval($_POST['board_id']); // For security check

if (empty($column_name)) {
    echo json_encode(['success' => false, 'message' => 'Column name cannot be empty.']);
    exit();
}

// Verify user has permission on the board this column belongs to
$perm_check_sql = "SELECT pc.column_id FROM Planotajs_Columns pc
                   JOIN Planotajs_Boards b ON pc.board_id = b.board_id
                   LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE pc.column_id = ? AND pc.board_id = ? AND (b.user_id = ? OR c.permission_level IN ('edit', 'admin'))
                   AND pc.is_deleted = 0";
$perm_stmt = $connection->prepare($perm_check_sql);
$perm_stmt->bind_param("iiii", $user_id, $column_id, $board_id, $user_id);
$perm_stmt->execute();
if ($perm_stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Column not found or not authorized.']);
    exit();
}
$perm_stmt->close();

$sql = "UPDATE Planotajs_Columns SET column_name = ? WHERE column_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("si", $column_name, $column_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating column: ' . $stmt->error]);
}
$stmt->close();
$connection->close();
?>