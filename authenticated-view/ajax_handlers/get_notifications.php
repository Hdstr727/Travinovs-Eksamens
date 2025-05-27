<?php
//ajax_handlers/get_notifications.php
session_start();
require_once '../../admin/database/connection.php'; // Adjust path as needed

header('Content-Type: application/json'); // Good practice to set content type

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; 

$sql = "SELECT notification_id, message, link, is_read, 
               DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as formatted_created_at,
               type, 
               activity_id,       -- Keep if used for other notification types
               related_entity_id, -- <<< ADD THIS
               related_entity_type  -- <<< ADD THIS
        FROM Planotajs_Notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?";
$stmt = $connection->prepare($sql);

if(!$stmt) {
    error_log("Get notifications prepare error: " . $connection->error);
    echo json_encode(['success' => false, 'error' => 'Database error preparing notifications.']);
    exit();
}

$stmt->bind_param("ii", $user_id, $limit);

if(!$stmt->execute()){
    error_log("Get notifications execute error: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Database error executing notifications query.']);
    exit();
}

$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Get unread count
$unread_count = 0; // Default
$unread_count_sql = "SELECT COUNT(*) as count FROM Planotajs_Notifications WHERE user_id = ? AND is_read = 0";
$stmt_count = $connection->prepare($unread_count_sql);
if($stmt_count){
    $stmt_count->bind_param("i", $user_id);
    if($stmt_count->execute()){
        $unread_count_result = $stmt_count->get_result()->fetch_assoc();
        $unread_count = $unread_count_result ? (int)$unread_count_result['count'] : 0;
    } else {
        error_log("Get unread count execute error: " . $stmt_count->error);
    }
    $stmt_count->close();
} else {
    error_log("Get unread count prepare error: " . $connection->error);
}

$connection->close();

echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count]);
?>