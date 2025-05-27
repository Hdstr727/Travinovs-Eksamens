<?php
// ajax_handlers/decline_invitation.php
session_start();
header('Content-Type: application/json');
require_once '../../admin/database/connection.php';
require_once '../core/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

$current_user_id = $_SESSION['user_id']; // Actor
$invitation_id = isset($_POST['invitation_id']) ? (int)$_POST['invitation_id'] : 0;

if ($invitation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid invitation ID.']);
    exit();
}

$connection->begin_transaction();
try {
    $fetch_invite_sql = "SELECT board_id, inviter_user_id, invited_user_id 
                         FROM Planotajs_Invitations 
                         WHERE invitation_id = ? AND status = 'pending'";
    $stmt_fetch = $connection->prepare($fetch_invite_sql);
    if (!$stmt_fetch) throw new Exception("DB Error (fi_prep_dec): " . $connection->error);
    $stmt_fetch->bind_param("i", $invitation_id);
    if (!$stmt_fetch->execute()) throw new Exception("DB Error (fi_exec_dec): " . $stmt_fetch->error);
    $invite_result = $stmt_fetch->get_result();
    
    if (!($invite_data = $invite_result->fetch_assoc())) {
        throw new Exception("Invitation not found, already actioned, or expired.");
    }
    $stmt_fetch->close();

    if ($invite_data['invited_user_id'] != $current_user_id) {
        throw new Exception("This invitation is not for you.");
    }

    $board_id = $invite_data['board_id'];
    $inviter_user_id = $invite_data['inviter_user_id'];

    $update_invite_sql = "UPDATE Planotajs_Invitations SET status = 'declined', updated_at = CURRENT_TIMESTAMP WHERE invitation_id = ?";
    $stmt_update_invite = $connection->prepare($update_invite_sql);
    if (!$stmt_update_invite) throw new Exception("DB Error (ui_prep_dec): " . $connection->error);
    $stmt_update_invite->bind_param("i", $invitation_id);
    if (!$stmt_update_invite->execute()) {
        throw new Exception("Failed to update invitation status: " . $stmt_update_invite->error);
    }
    $stmt_update_invite->close();

    // Mark the original invitation notification as read
    $mark_notif_read_sql = "UPDATE Planotajs_Notifications SET is_read = 1 WHERE user_id = ? AND type = 'invitation' AND related_entity_type = 'invitation' AND related_entity_id = ?";
    $stmt_mark_read = $connection->prepare($mark_notif_read_sql);
    if ($stmt_mark_read) {
        $stmt_mark_read->bind_param("ii", $current_user_id, $invitation_id);
        if(!$stmt_mark_read->execute()) error_log("Failed to mark declined inv notif as read: " . $stmt_mark_read->error);
        $stmt_mark_read->close();
    } else { error_log("Failed to prepare mark declined inv notif as read: " . $connection->error); }


    $board_actor_info = get_board_and_actor_info($connection, $board_id, $current_user_id);
    $activity_description = htmlspecialchars($board_actor_info['actor_username']) . " declined the invitation to join project \"" . htmlspecialchars($board_actor_info['board_name']) . "\".";
    
    log_and_notify(
        $connection, $board_id, $current_user_id, 'invitation_declined', 
        $activity_description, $invitation_id, 'invitation', []
    );

    // Optionally notify the inviter
    $inviter_notification_desc = htmlspecialchars($board_actor_info['actor_username']) . " declined your invitation to join project \"" . htmlspecialchars($board_actor_info['board_name']) . "\".";
    // SQL has 4 placeholders: user_id, board_id, message, related_entity_id
    // 'info' and 'invitation_response' are hardcoded in the SQL.
    $stmt_notify_inviter = $connection->prepare("INSERT INTO Planotajs_Notifications (user_id, board_id, message, type, related_entity_id, related_entity_type) VALUES (?, ?, ?, 'info', ?, 'invitation_response')");
    if($stmt_notify_inviter){
        // Corrected bind_param: i (user_id), i (board_id), s (message), i (related_entity_id)
        $stmt_notify_inviter->bind_param("iisi", $inviter_user_id, $board_id, $inviter_notification_desc, $invitation_id);
        if(!$stmt_notify_inviter->execute()) error_log("Failed to notify inviter (decline): " . $stmt_notify_inviter->error);
        $stmt_notify_inviter->close();
    } else { error_log("Failed to prepare notify inviter (decline): " . $connection->error); }
    
    $connection->commit();
    echo json_encode(['success' => true, 'message' => 'Invitation declined.']);

} catch (Exception $e) {
    $connection->rollback();
    error_log("Decline invitation error (invitation_id: $invitation_id): " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
}
$connection->close();
?>