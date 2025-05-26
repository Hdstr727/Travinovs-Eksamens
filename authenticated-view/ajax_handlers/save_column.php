<?php
//ajax-handlers/save-column.php
session_start();
require_once '../../admin/database/connection.php'; // Adjust path

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_POST['board_id'], $_POST['column_name'], $_POST['column_identifier'], $_POST['column_order'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields for column.']);
    exit();
}

$board_id = intval($_POST['board_id']);
$column_name = trim($_POST['column_name']);
$column_identifier = trim($_POST['column_identifier']);
$column_order = intval($_POST['column_order']);

// Verify user owns the board or has permission (admin or edit)
// Fixed: Changed 'owner' to 'admin' for collaborators since 'owner' is only for board creators
$perm_check_sql = "SELECT b.user_id, c.permission_level 
                   FROM Planotajs_Boards b
                   LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE b.board_id = ? AND b.is_deleted = 0
                   AND (b.user_id = ? OR c.permission_level IN ('admin', 'edit'))";

$perm_stmt = $connection->prepare($perm_check_sql);
$perm_stmt->bind_param("iii", $user_id, $board_id, $user_id);
$perm_stmt->execute();
$perm_result = $perm_stmt->get_result();

if ($perm_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Board not found or not authorized to add columns.']);
    $perm_stmt->close();
    exit();
}

$perm_stmt->close();

if (empty($column_name) || empty($column_identifier)) {
    echo json_encode(['success' => false, 'message' => 'Column name and identifier cannot be empty.']);
    exit();
}

// Check if identifier already exists for this board
$check_sql = "SELECT column_id FROM Planotajs_Columns WHERE board_id = ? AND column_identifier = ? AND is_deleted = 0";
$check_stmt = $connection->prepare($check_sql);
$check_stmt->bind_param("is", $board_id, $column_identifier);
$check_stmt->execute();

if ($check_stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'A column with this identifier already exists on this board.']);
    $check_stmt->close();
    exit();
}
$check_stmt->close();

// Insert the new column
$sql = "INSERT INTO Planotajs_Columns (board_id, column_name, column_identifier, column_order) VALUES (?, ?, ?, ?)";
$stmt = $connection->prepare($sql);
$stmt->bind_param("issi", $board_id, $column_name, $column_identifier, $column_order);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'column_id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating column: ' . $stmt->error]);
}

$stmt->close();
$connection->close();
?>