<?php
// authenticated-view/ajax_handlers/save_task.php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in. Please refresh and try again.']);
    exit();
}
require_once '../../admin/database/connection.php';
require_once '../core/functions.php'; 

$actor_user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['board_id'], $_POST['task_name'], $_POST['column_id'])) {
        $response['message'] = 'Missing required fields (board_id, task_name, column_id).';
        echo json_encode($response);
        exit();
    }

    $board_id = intval($_POST['board_id']);
    $task_name = trim($_POST['task_name']);
    $column_id = intval($_POST['column_id']);
    $task_description = isset($_POST['task_description']) ? trim($_POST['task_description']) : '';
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $priority = isset($_POST['priority']) && in_array($_POST['priority'], ['low', 'medium', 'high']) ? $_POST['priority'] : 'medium';
    $is_completed = isset($_POST['is_completed']) ? (int)$_POST['is_completed'] : 0; // New
    $assigned_to_user_id = isset($_POST['assigned_to_user_id']) && !empty($_POST['assigned_to_user_id']) ? (int)$_POST['assigned_to_user_id'] : null; // New

    // Permission Check (Owner, Admin, or Edit can save/edit tasks)
    $permission_level = 'read';
    $board_owner_check_sql = "SELECT user_id, is_archived FROM Planner_Boards WHERE board_id = ? AND is_deleted = 0";
    $stmt_board_check = $connection->prepare($board_owner_check_sql);
    $stmt_board_check->bind_param("i", $board_id);
    $stmt_board_check->execute();
    $board_res = $stmt_board_check->get_result();
    if($board_data = $board_res->fetch_assoc()){
        if($board_data['is_archived'] == 1){
            echo json_encode(['success' => false, 'message' => 'This project is archived. Tasks cannot be modified.']);
            $stmt_board_check->close(); $connection->close(); exit();
        }
        if($board_data['user_id'] == $actor_user_id) $permission_level = 'owner';
    } else {
        echo json_encode(['success' => false, 'message' => 'Board not found.']);
        $stmt_board_check->close(); $connection->close(); exit();
    }
    $stmt_board_check->close();

    if($permission_level !== 'owner'){
        $collab_check_sql = "SELECT permission_level FROM Planner_Collaborators WHERE board_id = ? AND user_id = ?";
        $stmt_collab_check = $connection->prepare($collab_check_sql);
        $stmt_collab_check->bind_param("ii", $board_id, $actor_user_id);
        $stmt_collab_check->execute();
        $collab_res = $stmt_collab_check->get_result();
        if($collab_data = $collab_res->fetch_assoc()){
            $permission_level = $collab_data['permission_level'];
        }
        $stmt_collab_check->close();
    }
    
    if (!in_array($permission_level, ['owner', 'admin', 'edit'])) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to save tasks on this project.']);
        $connection->close(); exit();
    }

    $task_id = isset($_POST['task_id']) && !empty($_POST['task_id']) ? intval($_POST['task_id']) : null;
    $operation_successful = false;
    $response_data = [];
    $activity_type = '';
    $saved_task_id = null; // Will hold new or existing task_id
    $task_order_for_response = 0; // For new tasks

    $connection->begin_transaction();
    try {
        $original_task_data = null;
        if ($task_id) { 
            $stmt_orig = $connection->prepare("SELECT task_name, is_completed, assigned_to_user_id, priority, due_date, task_description, column_id, task_order FROM Planner_Tasks WHERE task_id = ? AND board_id = ?");
            if(!$stmt_orig) throw new Exception("Prepare original task fetch failed: " . $connection->error);
            $stmt_orig->bind_param("ii", $task_id, $board_id);
            $stmt_orig->execute();
            $original_task_data = $stmt_orig->get_result()->fetch_assoc();
            $stmt_orig->close();
            if(!$original_task_data) throw new Exception("Original task not found for update.");
        }

        if ($task_id) { // Editing existing task
            $activity_type = 'task_updated';
            $update_sql = "UPDATE Planner_Tasks SET
                          task_name = ?, task_description = ?, column_id = ?, 
                          due_date = ?, priority = ?, is_completed = ?, assigned_to_user_id = ?,
                          updated_at = NOW()
                          WHERE task_id = ? AND board_id = ? AND is_deleted = 0";
            $update_stmt = $connection->prepare($update_sql);
            if (!$update_stmt) throw new Exception("Prepare update failed: " . $connection->error);
            $update_stmt->bind_param("ssisssiii", $task_name, $task_description, $column_id, $due_date, $priority, $is_completed, $assigned_to_user_id, $task_id, $board_id);

            if ($update_stmt->execute()) {
                $operation_successful = ($update_stmt->affected_rows > 0 || 
                                        ($original_task_data && ($original_task_data['is_completed'] != $is_completed || $original_task_data['assigned_to_user_id'] != $assigned_to_user_id))
                                        ); // Consider successful if status/assignee changed even if other text fields didn't
                $saved_task_id = $task_id;
                $task_order_for_response = $original_task_data['task_order']; // Keep original order on simple update
                $response_data = ['success' => true, 'task_id' => $task_id, 'column_id' => $column_id, 'task_order' => $task_order_for_response]; 
            } else { throw new Exception("Error updating task: " . $update_stmt->error); }
            $update_stmt->close();
        } else { // Creating new task
            $activity_type = 'task_created';
            $order_sql = "SELECT MAX(task_order) as max_order FROM Planner_Tasks WHERE board_id = ? AND column_id = ? AND is_deleted = 0";
            $order_stmt = $connection->prepare($order_sql);
            if (!$order_stmt) throw new Exception("Prepare order failed: " . $connection->error);
            $order_stmt->bind_param("ii", $board_id, $column_id);
            $order_stmt->execute();
            $order_result = $order_stmt->get_result();
            $order_row = $order_result->fetch_assoc();
            $task_order = ($order_row && $order_row['max_order'] !== null) ? (int)$order_row['max_order'] + 1 : 0;
            $task_order_for_response = $task_order;
            $order_stmt->close();

            $insert_sql = "INSERT INTO Planner_Tasks
                          (board_id, task_name, task_description, column_id, task_order, due_date, priority, is_completed, assigned_to_user_id, created_by_user_id)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $connection->prepare($insert_sql);
            if (!$insert_stmt) throw new Exception("Prepare insert failed: " . $connection->error);
            $insert_stmt->bind_param("ississsiii", $board_id, $task_name, $task_description, $column_id, $task_order, $due_date, $priority, $is_completed, $assigned_to_user_id, $actor_user_id);

            if ($insert_stmt->execute()) {
                $saved_task_id = $insert_stmt->insert_id;
                $operation_successful = true;
                $response_data = ['success' => true, 'task_id' => $saved_task_id, 'column_id' => $column_id, 'task_order' => $task_order];
            } else { throw new Exception("Error creating task: " . $insert_stmt->error); }
            $insert_stmt->close();
        }

        if ($operation_successful && $saved_task_id) {
            update_board_last_activity_timestamp($connection, $board_id);

            $board_actor_info = get_board_and_actor_info($connection, $board_id, $actor_user_id);
            $log_description_parts = [];
            $notified_assignee = null;

            if ($activity_type === 'task_created') {
                $log_description = htmlspecialchars($board_actor_info['actor_username']) . " created task \"" . htmlspecialchars($task_name) . "\"";
                if ($assigned_to_user_id) {
                    $assignee_info_stmt = $connection->prepare("SELECT username FROM Planner_Users WHERE user_id = ?");
                    $assignee_info_stmt->bind_param("i", $assigned_to_user_id); $assignee_info_stmt->execute();
                    $assignee_name = $assignee_info_stmt->get_result()->fetch_assoc()['username'] ?? "User ID ".$assigned_to_user_id;
                    $assignee_info_stmt->close();
                    $log_description .= " and assigned it to " . htmlspecialchars($assignee_name);
                    if ($assigned_to_user_id != $actor_user_id) $notified_assignee = $assigned_to_user_id;
                }
            } else { // task_updated
                $log_description = htmlspecialchars($board_actor_info['actor_username']) . " updated task \"" . htmlspecialchars($task_name) . "\"";
                if ($original_task_data['task_name'] != $task_name) $log_description_parts[] = "renamed";
                if ($original_task_data['column_id'] != $column_id) $log_description_parts[] = "moved"; // More specific logic needed if column name is desired
                if ($original_task_data['is_completed'] != $is_completed) $log_description_parts[] = $is_completed ? "marked complete" : "marked incomplete";
                if ($original_task_data['assigned_to_user_id'] != $assigned_to_user_id) {
                    if ($assigned_to_user_id) {
                        $assignee_info_stmt = $connection->prepare("SELECT username FROM Planner_Users WHERE user_id = ?");
                        $assignee_info_stmt->bind_param("i", $assigned_to_user_id); $assignee_info_stmt->execute();
                        $assignee_name = $assignee_info_stmt->get_result()->fetch_assoc()['username'] ?? "User ID ".$assigned_to_user_id;
                        $assignee_info_stmt->close();
                        $log_description_parts[] = "assigned to " . htmlspecialchars($assignee_name);
                        if ($assigned_to_user_id != $actor_user_id) $notified_assignee = $assigned_to_user_id;
                    } else {
                        $log_description_parts[] = "unassigned";
                    }
                }
                // Add due_date, priority, description changes if needed
                if (!empty($log_description_parts)) {
                    $log_description .= " (" . implode(", ", $log_description_parts) . ")";
                } else if ($operation_successful) { // If affected_rows > 0 but no specific change logged above
                     $log_description .= " (details updated)";
                } else { // No actual change, but form was submitted
                    $response_data['message'] = 'No changes detected for the task.';
                    // $operation_successful = false; // To prevent logging if no actual change
                }
            }
            $log_description .= " on board \"" . htmlspecialchars($board_actor_info['board_name']) . "\".";
            
            if ($operation_successful) { // Only log if there was a change or creation
                $recipients = get_board_associated_user_ids($connection, $board_id);
                $link_to_task = "kanban.php?board_id=" . $board_id . "#task-" . $saved_task_id;
                log_and_notify($connection, $board_id, $actor_user_id, $activity_type, $log_description, $saved_task_id, 'task', $recipients, $link_to_task);

                // Specific notification to newly assigned user (if not the actor)
                if ($notified_assignee) {
                    $assign_notif_desc = htmlspecialchars($board_actor_info['actor_username']) . " assigned you the task \"" . htmlspecialchars($task_name) . "\" on board \"" . htmlspecialchars($board_actor_info['board_name']) . "\".";
                    log_and_notify($connection, $board_id, $actor_user_id, 'task_assignment', $assign_notif_desc, $saved_task_id, 'task', [$notified_assignee], $link_to_task, 'task_assignment');
                }
            }
            $connection->commit();
            echo json_encode($response_data);

        } else { // if $operation_successful is false but no exception was thrown (e.g. update affected 0 rows and no status change)
            $connection->rollback(); // Rollback if no operation was deemed successful
            $response_data['success'] = false; // Ensure success is false
            if(empty($response_data['message'])) $response_data['message'] = 'No changes were made to the task.';
            echo json_encode($response_data);
        }

    } catch (Exception $e) {
        $connection->rollback();
        $response['message'] = "Error: " . $e->getMessage();
        error_log("Save Task Exception: " . $e->getMessage() . " - Board: $board_id, Task: $task_id, User: $actor_user_id. SQL: " . ($stmt->sqlstate ?? 'N/A'));
        echo json_encode($response);
    }

} else {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
}

if ($connection) {
    $connection->close();
}
?>