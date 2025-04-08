<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if form data was submitted
if (!isset($_POST['board_name']) || !isset($_POST['board_template'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Include database connection
require_once '../admin/database/connection.php';

// Sanitize input
$board_name = trim($_POST['board_name']);
$board_type = trim($_POST['board_template']);
$user_id = $_SESSION['user_id'];

// Validate input
if (empty($board_name)) {
    echo json_encode(['success' => false, 'message' => 'Board name cannot be empty']);
    exit();
}

// Insert new board into database
$sql = "INSERT INTO Planotajs_Boards (user_id, board_name, board_type) VALUES (?, ?, ?)";
$stmt = $connection->prepare($sql);
$stmt->bind_param("iss", $user_id, $board_name, $board_type);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Board created successfully', 'board_id' => $connection->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating board: ' . $stmt->error]);
}

$stmt->close();
$connection->close();
?>