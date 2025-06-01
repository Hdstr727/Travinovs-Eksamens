<?php
// authenticated-view/ajax_handlers/check_board_update.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.', 'stop_polling' => true]);
    exit();
}

require_once '../../admin/database/connection.php'; // Adjusted path

$board_id = isset($_GET['board_id']) ? intval($_GET['board_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($board_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid board ID.', 'stop_polling' => true]);
    exit();
}

// Verify user has access to the board (important for security)
$access_check_sql = "SELECT b.updated_at, b.is_archived
                     FROM Planner_Boards b
                     LEFT JOIN Planner_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                     WHERE b.board_id = ? AND b.is_deleted = 0
                     AND (b.user_id = ? OR c.user_id = ?)";
$stmt = $connection->prepare($access_check_sql);
$stmt->bind_param("iiii", $user_id, $board_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'updated_at' => $row['updated_at'],
        'is_archived' => (int)$row['is_archived']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Board not found or access denied.', 'stop_polling' => true]);
}
$stmt->close();
$connection->close();
?>