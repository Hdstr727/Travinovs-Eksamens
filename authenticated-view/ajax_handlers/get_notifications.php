<?php
//ajax_handlers/get_notifications.php
<?php
session_start();
require_once '../../admin/database/connection.php'; // Adjust path as needed

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Get last 10 notifications

$sql = "SELECT notification_id, message, link, is_read, DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as formatted_created_at
        FROM Planotajs_Notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("ii", $user_id, $limit);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Get unread count separately (or could combine queries)
$unread_count_sql = "SELECT COUNT(*) as count FROM Planotajs_Notifications WHERE user_id = ? AND is_read = 0";
$stmt_count = $connection->prepare($unread_count_sql);
$stmt_count->bind_param("i", $user_id);
$stmt_count->execute();
$unread_count = $stmt_count->get_result()->fetch_assoc()['count'];
$stmt_count->close();
$connection->close();

echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count]);
?>