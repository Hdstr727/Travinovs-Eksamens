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
    // 1. Fetch invitation details AND board owner ID
    $fetch_invite_sql = "SELECT i.board_id, i.inviter_user_id, i.invited_user_id, i.permission_level, 
                                b.user_id as board_owner_id, b.board_name 
                         FROM Planotajs_Invitations i
                         JOIN Planotajs_Boards b ON i.board_id = b.board_id
                         WHERE i.invitation_id = ? AND i.status = 'pending'";
    $stmt_fetch = $connection->prepare($fetch_invite_sql);
    if (!$stmt_fetch) throw new Exception("DB Error (fi_prep): " . $connection->error);
    $stmt_fetch->bind_param("i", $invitation_id);
    if (!$stmt_fetch->execute()) throw new Exception("DB Error (fi_exec): " . $stmt_fetch->error);
    $invite_result = $stmt_fetch->get_result();
    
    if (!($invite_data = $invite_result->fetch_assoc())) {
        throw new Exception("Invitation not found, already actioned, or expired.");
    }
    $stmt_fetch->close();

    if ($invite_data['invited_user_id'] != $current_user_id) {
        throw new Exception("This invitation is not for you.");
    }

    $board_id = (int)$invite_data['board_id'];
    $permission_level_from_invite = $invite_data['permission_level']; // Renamed for clarity
    $inviter_user_id = (int)$invite_data['inviter_user_id'];
    $board_owner_id = (int)$invite_data['board_owner_id'];
    $board_name_from_invite = $invite_data['board_name']; // Get board name for logging

    // *** CRITICAL PREVENTION STEP ***
    if ($current_user_id == $board_owner_id) {
        // User accepting is already the owner. Silently acknowledge.
        $update_invite_sql_silent = "UPDATE Planotajs_Invitations SET status = 'accepted_owner', updated_at = CURRENT_TIMESTAMP WHERE invitation_id = ?";
        $stmt_update_silent = $connection->prepare($update_invite_sql_silent);
        if ($stmt_update_silent) {
            $stmt_update_silent->bind_param("i", $invitation_id);
            $stmt_update_silent->execute();
            $stmt_update_silent->close();
        }
        // Mark notification as read for the owner
        $mark_notif_read_sql_owner = "UPDATE Planotajs_Notifications SET is_read = 1 WHERE user_id = ? AND type = 'invitation' AND related_entity_type = 'invitation' AND related_entity_id = ?";
        $stmt_mark_read_owner = $connection->prepare($mark_notif_read_sql_owner);
        if ($stmt_mark_read_owner) {
            $stmt_mark_read_owner->bind_param("ii", $current_user_id, $invitation_id);
            $stmt_mark_read_owner->execute();
            $stmt_mark_read_owner->close();
        }

        $connection->commit();
        echo json_encode(['success' => true, 'message' => 'You are already the owner of this project. Invitation acknowledged.']);
        exit();
    }

    // 2. Add to Planotajs_Collaborators (or update if they were previously a collaborator with a different role from a past invite)
    $add_collab_sql = "INSERT INTO Planotajs_Collaborators (board_id, user_id, permission_level) 
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE permission_level = VALUES(permission_level), updated_at = CURRENT_TIMESTAMP";
    $stmt_add_collab = $connection->prepare($add_collab_sql);
    if (!$stmt_add_collab) throw new Exception("DB Error (ac_prep): " . $connection->error);
    $stmt_add_collab->bind_param("iis", $board_id, $current_user_id, $permission_level_from_invite);
    if (!$stmt_add_collab->execute()) {
        // Check for specific errors like foreign key constraint if user_id or board_id is invalid
        // (though unlikely if invitation was created correctly)
        throw new Exception("Failed to add/update collaborator record: " . $stmt_add_collab->error);
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

    // 5. Log activity and Notify relevant parties (Inviter, Board Owner if different from Inviter)
    $acceptor_info = get_board_and_actor_info($connection, $board_id, $current_user_id); // Gets acceptor's username
    $activity_description = htmlspecialchars($acceptor_info['actor_username']) . " accepted the invitation to join project \"" . htmlspecialchars($board_name_from_invite) . "\".";
    
    $recipients_for_log_notify_accept = [$inviter_user_id];
    if ($board_owner_id != $inviter_user_id && $board_owner_id != $current_user_id) { // Also notify owner if they weren't the inviter and not the one accepting
        $recipients_for_log_notify_accept[] = $board_owner_id;
    }
    
    log_and_notify(
        $connection,
        $board_id,
        $current_user_id,       // Actor: the one who accepted
        'invitation_accepted',
        $activity_description,
        $invitation_id,         // related_entity_id
        'invitation',           // related_entity_type
        array_unique($recipients_for_log_notify_accept) // Notify inviter and distinct owner
    );
    
    $connection->commit();
    echo json_encode(['success' => true, 'message' => 'Invitation accepted! You are now a collaborator.']);

} catch (Exception $e) {
    $connection->rollback();
    error_log("Accept invitation error (invitation_id: $invitation_id, user: $current_user_id): " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
}

if ($connection) { // Check if connection was successfully established
    $connection->close();
}
?>