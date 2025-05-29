<?php
// File: authenticated-view/ajax_handlers/cancel_invitation.php
session_start();

header('Content-Type: application/json'); // Ensure JSON response

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated. Please log in.']);
    exit();
}

// Path relative to this file's location (ajax_handlers/)
require_once '../../admin/database/connection.php'; 
require_once '../core/functions.php';      

$canceller_user_id = $_SESSION['user_id']; // The user attempting to cancel (should be the inviter/board owner)
$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invitation_id = isset($_POST['invitation_id']) ? (int)$_POST['invitation_id'] : 0;

    if ($invitation_id <= 0) {
        $response['message'] = 'Invalid invitation ID provided.';
        echo json_encode($response);
        exit();
    }

    $connection->begin_transaction();
    try {
        // 1. Fetch invitation details to verify ownership/permissions and get info for logging
        $fetch_invite_sql = "SELECT i.board_id, i.inviter_user_id, i.invited_user_id, i.status, 
                                    b.user_id as board_owner_id, 
                                    u_invited.username as invited_username,
                                    b.board_name
                             FROM Planner_Invitations i
                             JOIN Planner_Boards b ON i.board_id = b.board_id
                             JOIN Planner_Users u_invited ON i.invited_user_id = u_invited.user_id
                             WHERE i.invitation_id = ?";
        $stmt_fetch = $connection->prepare($fetch_invite_sql);
        if (!$stmt_fetch) throw new Exception("Failed to prepare invitation fetch: " . $connection->error);
        
        $stmt_fetch->bind_param("i", $invitation_id);
        $stmt_fetch->execute();
        $invite_result = $stmt_fetch->get_result();
        
        if (!($invite_data = $invite_result->fetch_assoc())) {
            throw new Exception("Invitation not found.");
        }
        $stmt_fetch->close();

        $board_id = $invite_data['board_id'];
        $inviter_id_from_db = $invite_data['inviter_user_id'];
        $board_owner_id_from_db = $invite_data['board_owner_id'];
        $invited_username = $invite_data['invited_username'];
        $board_name = $invite_data['board_name'];

        // 2. Permission Check: Only the original inviter or the board owner can cancel
        if ($canceller_user_id != $inviter_id_from_db && $canceller_user_id != $board_owner_id_from_db) {
            throw new Exception("You do not have permission to cancel this invitation.");
        }

        // 3. Check if the invitation is still 'pending'
        if ($invite_data['status'] !== 'pending') {
            throw new Exception("This invitation is no longer pending and cannot be cancelled (current status: " . $invite_data['status'] . ").");
        }

        // 4. Update invitation status to 'cancelled'
        $update_invite_sql = "UPDATE Planner_Invitations SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE invitation_id = ?";
        $stmt_update_invite = $connection->prepare($update_invite_sql);
        if (!$stmt_update_invite) throw new Exception("Failed to prepare invitation update: " . $connection->error);

        $stmt_update_invite->bind_param("i", $invitation_id);
        if (!$stmt_update_invite->execute()) {
            throw new Exception("Failed to cancel invitation: " . $stmt_update_invite->error);
        }
        $stmt_update_invite->close();

        // 5. Log this activity (optional, but good practice)
        $canceller_info = get_board_and_actor_info($connection, $board_id, $canceller_user_id);
        $activity_description = htmlspecialchars($canceller_info['actor_username']) . 
                                 " cancelled the invitation for " . htmlspecialchars($invited_username) . 
                                 " to join project \"" . htmlspecialchars($board_name) . "\".";
        
        if (function_exists('log_activity')) {
            log_activity($connection, $board_id, $canceller_user_id, 'invitation_cancelled', $activity_description, $invitation_id, 'invitation');
        }
        
        // 6. Optionally, delete the notification sent to the invited user about this pending invitation
        // This is good for cleanup so they don't see an old notification with now-defunct actions.
        $delete_notification_sql = "DELETE FROM Planner_Notifications 
                                    WHERE related_entity_id = ? AND related_entity_type = 'invitation' AND type = 'invitation' AND user_id = ?";
        $stmt_delete_notif = $connection->prepare($delete_notification_sql);
        if($stmt_delete_notif){
            $stmt_delete_notif->bind_param("ii", $invitation_id, $invite_data['invited_user_id']);
            $stmt_delete_notif->execute();
            $stmt_delete_notif->close();
        }


        $connection->commit();
        $response['success'] = true;
        $response['message'] = 'Invitation cancelled successfully.';

    } catch (Exception $e) {
        $connection->rollback();
        $response['message'] = $e->getMessage();
        error_log("Cancel invitation error (invitation_id: $invitation_id): " . $e->getMessage());
    }

} else {
    $response['message'] = 'Invalid request method.';
}

$connection->close();
echo json_encode($response);
exit();
?>