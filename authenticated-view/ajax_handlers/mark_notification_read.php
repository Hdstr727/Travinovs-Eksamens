<?php
session_start();
require_once '../../admin/database/connection.php'; // Adjust path

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : null;
$mark_all = isset($_POST['mark_all']) ? $_POST['mark_all'] === 'true' : false;

if ($mark_all) {
    $sql = "UPDATE Planner_Notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $user_id);
} elseif ($notification_id) {
    $sql = "UPDATE Planner_Notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

$success = $stmt->execute();
$stmt->close();
$connection->close();

echo json_encode(['success' => $success]);
?>