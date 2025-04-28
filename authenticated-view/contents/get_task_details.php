<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Include database connection
require_once '../admin/database/connection.php';

if (!isset($_GET['task_id']) || !is_numeric($_GET['task_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid task ID']);
    exit();
}

$task_id = (int)$_GET['task_id'];

// Fixed SQL query to remove board_color
$sql = "SELECT t.*, b.board_name 
        FROM Planotajs_Tasks t 
        LEFT JOIN Planotajs_Boards b ON t.board_id = b.board_id 
        WHERE t.task_id = ? AND t.is_deleted = 0";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Task not found']);
    exit();
}

$task = $result->fetch_assoc();
$stmt->close();

// Return task details as JSON
header('Content-Type: application/json');
echo json_encode($task);
?>