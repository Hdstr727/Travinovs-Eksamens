<?php
// ajax_handlers/accept_invitation.php
session_start();
header('Content-Type: application/json');
require_once '../../admin/database/connection.php';
require_once '../core/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

$current_user_id = $_SESSION['user_id']; // This is the actor (the one accepting)
$invitation_id = isset($_POST['invitation_id']) ? (int)$_POST['invitation_id'] : 0;

if ($invitation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid invitation ID.']);
    exit();
}

$connection->begin_transaction();
try {
    // 1. Fetch invitation details and verify
    $fetch_invite_sql = "SELECT board_id, inviter_user_id, invited_user_id, permission_level 
                         FROM Planotajs_Invitations 
                         WHERE invitation_id = ? AND status = 'pending'"; // Only fetch if still pending
    $stmt_fetch = $connection->prepare($fetch_invite_sql);
    if (!$stmt_fetch) throw new Exception("DB Error (fi_prep): " . $connection->error);
    $stmt_fetch->bind_param("i", $invitation_id);
    if (!$stmt_fetch->execute()) throw new Exception("DB Error (fi_exec): " . $stmt_fetch->error);
    $invite_result = $stmt_fetch->get_result();
    
    if (!($invite_data = $invite_result->fetch_assoc())) {
        // This is where the "Invitation not found, already actioned, or expired." error originates
        // if the invitation is no longer pending (e.g., user double-clicked accept)
        throw new Exception("Invitation not found, already actioned, or expired.");
    }
    $stmt_fetch->close();

    if ($invite_data['invited_user_id'] != $current_user_id) {
        throw new Exception("This invitation is not for you.");
    }

    $board_id = $invite_data['board_id'];
    $permission_level = $invite_data['permission_level'];
    $inviter_user_id = $invite_data['inviter_user_id'];

    // 2. Add to Planotajs_Collaborators
    $add_collab_sql = "INSERT INTO Planotajs_Collaborators (board_id, user_id, permission_level) 
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE permission_level = VALUES(permission_level)";
    $stmt_add_collab = $connection->prepare($add_collab_sql);
    if (!$stmt_add_collab) throw new Exception("DB Error (ac_prep): " . $connection->error);
    $stmt_add_collab->bind_param("iis", $board_id, $current_user_id, $permission_level);
    if (!$stmt_add_collab->execute()) {
        throw new Exception("Failed to add collaborator: " . $stmt_add_collab->error);
    }
    $stmt_add_collab->close();

    // 3. Update invitation status to 'accepted'
    $update_invite_sql = "UPDATE Planotajs_Invitations SET status = 'accepted', updated_at = CURRENT_TIMESTAMP WHERE invitation_id = ?";
    $stmt_update_invite = $connection->prepare($update_invite_sql);
    if (!$stmt_update_invite) throw new Exception("DB Error (ui_prep): " . $connection->error);
    $stmt_update_invite->bind_param("i", $invitation_id);
    if (!$stmt_update_invite->execute()) {
        throw new Exception("Failed to update invitation status: " . $stmt_update_invite->error);
    }
    $stmt_update_invite->close();

    // 4. Mark the original invitation notification (sent to current_user_id) as read
    $mark_notif_read_sql = "UPDATE Planotajs_Notifications 
                            SET is_read = 1 
                            WHERE user_id = ? 
                              AND type = 'invitation' 
                              AND related_entity_type = 'invitation' 
                              AND related_entity_id = ?";
    $stmt_mark_read = $connection->prepare($mark_notif_read_sql);
    if ($stmt_mark_read) {
        $stmt_mark_read->bind_param("ii", $current_user_id, $invitation_id);
        if (!$stmt_mark_read->execute()) {
            error_log("Failed to mark invitation notification as read for user $current_user_id, invitation $invitation_id: " . $stmt_mark_read->error);
        }
        $stmt_mark_read->close();
    } else {
        error_log("Failed to prepare statement to mark invitation notification as read: " . $connection->error);
    }

    // 5. Log activity using log_and_notify
    $board_actor_info = get_board_and_actor_info($connection, $board_id, $current_user_id);
    $activity_description = htmlspecialchars($board_actor_info['actor_username']) . " accepted the invitation to join project \"" . htmlspecialchars($board_actor_info['board_name']) . "\".";
    
    log_and_notify(
        $connection,
        $board_id,
        $current_user_id,           // Actor
        'invitation_accepted',     // Activity Type
        $activity_description,
        $invitation_id,             // related_entity_id
        'invitation',               // related_entity_type
        []                          // No general recipients from this specific action's log
                                    // The 'invitation_accepted' type should be in $setting_field_map in functions.php
    );

    // 6. Notify the original inviter directly
    $inviter_notification_desc = htmlspecialchars($board_actor_info['actor_username']) . " accepted your invitation to join project \"" . htmlspecialchars($board_actor_info['board_name']) . "\".";
    $link_to_board_settings = "project_settings.php?board_id=" . $board_id . "#collaborators";
    $notification_type_for_inviter = 'info'; // Type for this specific notification
    $related_entity_type_for_inviter_notif = 'invitation_response'; // Custom type for this response

    $stmt_notify_inviter = $connection->prepare("INSERT INTO Planotajs_Notifications (user_id, board_id, message, link, type, related_entity_id, related_entity_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if($stmt_notify_inviter){
        // user_id (i), board_id (i), message (s), link (s), type (s), related_entity_id (i), related_entity_type (s)
        $stmt_notify_inviter->bind_param("iisssis", 
            $inviter_user_id, 
            $board_id, 
            $inviter_notification_desc, 
            $link_to_board_settings, 
            $notification_type_for_inviter, // Variable for type
            $invitation_id,                 // related_entity_id
            $related_entity_type_for_inviter_notif // Variable for related_entity_type
        ); 
        if (!$stmt_notify_inviter->execute()) {
            error_log("Failed to notify inviter about accepted invitation: " . $stmt_notify_inviter->error);
            // Don't throw exception here, main operation (accepting) succeeded.
        }
        $stmt_notify_inviter->close();
    } else {
        error_log("Failed to prepare statement to notify inviter: " . $connection->error);
    }
    
    $connection->commit();
    echo json_encode(['success' => true, 'message' => 'Invitation accepted! You are now a collaborator.']);

} catch (Exception $e) {
    $connection->rollback();
    // Log the detailed error including file and line for easier debugging
    error_log("Accept invitation error (invitation_id: $invitation_id): " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    // Send a more generic or specific error message to the client as appropriate
    echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
}
$connection->close();
?>