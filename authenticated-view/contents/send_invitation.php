<?php
// File: authenticated-view/contents/send_invitation.php
session_start();

header('Content-Type: application/json'); // Ensure JSON response

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated. Please log in.']);
    exit();
}

// Include database connection - Path relative to this file's location
require_once '../../admin/database/connection.php';
// Include core functions - Path relative to this file's location
require_once '../core/functions.php'; 

$inviter_user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'An unexpected error occurred. Please try again.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $board_id = isset($_POST['board_id']) ? (int)$_POST['board_id'] : 0;
    $invited_email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $permission_level = isset($_POST['permission_level']) ? $_POST['permission_level'] : 'view';
    $custom_message_from_form = isset($_POST['custom_message']) ? trim($_POST['custom_message']) : '';

    if (empty($invited_email) || !filter_var($invited_email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address.';
        echo json_encode($response);
        exit();
    }
    if ($board_id <= 0) {
        $response['message'] = 'Invalid project ID.';
        echo json_encode($response);
        exit();
    }
    if (!in_array($permission_level, ['view', 'edit', 'admin'])) {
        $response['message'] = 'Invalid permission level selected.';
        echo json_encode($response);
        exit();
    }

    $connection->begin_transaction();

    try {
        $board_check_sql = "SELECT board_name, user_id FROM Planotajs_Boards WHERE board_id = ? AND is_deleted = 0";
        $stmt_board_check = $connection->prepare($board_check_sql);
        if (!$stmt_board_check) throw new Exception("DB Error (bc_prep): " . $connection->error);
        $stmt_board_check->bind_param("i", $board_id);
        if (!$stmt_board_check->execute()) throw new Exception("DB Error (bc_exec): " . $stmt_board_check->error);
        $board_result = $stmt_board_check->get_result();
        if ($board_row = $board_result->fetch_assoc()) {
            $board_name = $board_row['board_name'];
            $board_owner_id = $board_row['user_id'];
            if ($board_owner_id != $inviter_user_id) {
                throw new Exception('You do not have permission to invite users to this project.');
            }
        } else {
            throw new Exception('Project not found or has been deleted.');
        }
        $stmt_board_check->close();

        $find_user_sql = "SELECT user_id, username FROM Planotajs_Users WHERE email = ?";
        $stmt_find_user = $connection->prepare($find_user_sql);
        if (!$stmt_find_user) throw new Exception("DB Error (fu_prep): " . $connection->error);
        $stmt_find_user->bind_param("s", $invited_email);
        if (!$stmt_find_user->execute()) throw new Exception("DB Error (fu_exec): " . $stmt_find_user->error);
        $invited_user_result = $stmt_find_user->get_result();
        if (!($invited_user_row = $invited_user_result->fetch_assoc())) {
            throw new Exception('User with email ' . htmlspecialchars($invited_email) . ' not found. Please ask them to register first.');
        }
        $invited_user_id = $invited_user_row['user_id'];
        $invited_username = $invited_user_row['username'];
        $stmt_find_user->close();

        if ($invited_user_id == $inviter_user_id) {
            throw new Exception('You cannot invite yourself to a project.');
        }

        $check_collab_sql = "SELECT collaboration_id FROM Planotajs_Collaborators WHERE board_id = ? AND user_id = ?";
        $stmt_check_collab = $connection->prepare($check_collab_sql);
        if (!$stmt_check_collab) throw new Exception("DB Error (cc_prep): " . $connection->error);
        $stmt_check_collab->bind_param("ii", $board_id, $invited_user_id);
        if (!$stmt_check_collab->execute()) throw new Exception("DB Error (cc_exec): " . $stmt_check_collab->error);
        if ($stmt_check_collab->get_result()->num_rows > 0) {
            throw new Exception(htmlspecialchars($invited_username) . ' is already a collaborator on this project.');
        }
        $stmt_check_collab->close();

        $check_pending_sql = "SELECT invitation_id FROM Planotajs_Invitations WHERE board_id = ? AND invited_user_id = ? AND status = 'pending'";
        $stmt_check_pending = $connection->prepare($check_pending_sql);
        if (!$stmt_check_pending) throw new Exception("DB Error (cp_prep): " . $connection->error);
        $stmt_check_pending->bind_param("ii", $board_id, $invited_user_id);
        if (!$stmt_check_pending->execute()) throw new Exception("DB Error (cp_exec): " . $stmt_check_pending->error);
        if ($stmt_check_pending->get_result()->num_rows > 0) {
            throw new Exception('An invitation is already pending for ' . htmlspecialchars($invited_username) . ' for this project.');
        }
        $stmt_check_pending->close();

        $token = bin2hex(random_bytes(16)); 
        $insert_invite_sql = "INSERT INTO Planotajs_Invitations (board_id, inviter_user_id, invited_user_id, permission_level, status, token, custom_message) VALUES (?, ?, ?, ?, 'pending', ?, ?)";
        $stmt_insert_invite = $connection->prepare($insert_invite_sql);
        if (!$stmt_insert_invite) throw new Exception("DB Error (ii_prep): " . $connection->error);
        $stmt_insert_invite->bind_param("iiisss", $board_id, $inviter_user_id, $invited_user_id, $permission_level, $token, $custom_message_from_form);
        if (!$stmt_insert_invite->execute()) {
            throw new Exception('Failed to create invitation record: ' . $stmt_insert_invite->error);
        }
        $invitation_id = $stmt_insert_invite->insert_id;
        $stmt_insert_invite->close();
            
        $inviter_info = get_board_and_actor_info($connection, $board_id, $inviter_user_id);
        $activity_description_for_log = htmlspecialchars($inviter_info['actor_username']) . " sent an invitation to " . htmlspecialchars($invited_username) . " (" . htmlspecialchars($invited_email) . ") for project \"" . htmlspecialchars($board_name) . "\" with " . htmlspecialchars($permission_level) . " permission.";
        if (function_exists('log_activity')) { 
             log_activity($connection, $board_id, $inviter_user_id, 'invitation_sent', $activity_description_for_log, $invitation_id, 'invitation');
        }

        $notification_message_for_invitee = htmlspecialchars($inviter_info['actor_username']) . " has invited you to collaborate on the project \"" . htmlspecialchars($board_name) . "\"";
        if(!empty($custom_message_from_form)){
            $notification_message_for_invitee .= " with the message: \"" . htmlspecialchars(mb_strimwidth($custom_message_from_form, 0, 30, "...")) . "\"";
        }
        $notification_message_for_invitee .= ". You will have " . htmlspecialchars($permission_level) . " access.";
        
        $notification_link_for_invitee = "index.php#notifications"; 
        $notification_type_for_invitee = 'invitation';
        $related_entity_type_for_notification = 'invitation'; 

        $stmt_notify_invitee = $connection->prepare(
            "INSERT INTO Planotajs_Notifications (user_id, board_id, message, link, type, related_entity_id, related_entity_type) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt_notify_invitee) throw new Exception("DB Error (ni_prep): " . $connection->error);
        
        $stmt_notify_invitee->bind_param("iisssis", $invited_user_id, $board_id, $notification_message_for_invitee, $notification_link_for_invitee, $notification_type_for_invitee, $invitation_id, $related_entity_type_for_notification);
        if (!$stmt_notify_invitee->execute()) {
            throw new Exception("Failed to insert invitation notification: " . $stmt_notify_invitee->error);
        }
        $stmt_notify_invitee->close();

        $connection->commit();
        $response['success'] = true;
        $response['message'] = "Invitation sent to " . htmlspecialchars($invited_username) . ". They will receive a notification.";

    } catch (Exception $e) {
        $connection->rollback();
        $response['message'] = $e->getMessage();
        error_log("Send invitation error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    }

} else {
    $response['message'] = 'Invalid request method.';
}

$connection->close();
echo json_encode($response);
exit();