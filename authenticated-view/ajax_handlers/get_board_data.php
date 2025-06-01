<?php
// authenticated-view/ajax_handlers/get_board_data.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

require_once '../../admin/database/connection.php'; // Adjusted path

$user_id = $_SESSION['user_id'];
$board_id = isset($_GET['board_id']) ? intval($_GET['board_id']) : 0;

if ($board_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid board ID.']);
    exit();
}

// Initialize variables
$board_name_from_db = "Kanban Board";
$board_columns_data = [];
$board_tasks_data = [];
$board_collaborators_for_assignment = []; // Crucial array
$is_owner = false;
$permission_level = 'read';
$is_board_archived = 0;
$board_updated_at = null;
$board_owner_id = 0; // Initialize board_owner_id

// --- Start of Replicated Data Fetching Logic (from kanban_content.php) ---
$access_sql = "SELECT b.board_id, b.board_name, b.user_id as board_owner_id, b.is_archived, b.updated_at
               FROM Planner_Boards b
               LEFT JOIN Planner_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
               WHERE b.board_id = ? AND b.is_deleted = 0
               AND (b.user_id = ? OR c.user_id = ?)";
$access_stmt = $connection->prepare($access_sql);
if (!$access_stmt) {
    error_log("get_board_data.php - Prepare access_sql failed: " . $connection->error);
    echo json_encode(['success' => false, 'message' => 'Database error (access).']);
    exit();
}
$access_stmt->bind_param("iiii", $user_id, $board_id, $user_id, $user_id);
$access_stmt->execute();
$access_result = $access_stmt->get_result();

if ($board_row_data = $access_result->fetch_assoc()) {
    $board_name_from_db = htmlspecialchars($board_row_data['board_name']);
    $board_owner_id = (int)$board_row_data['board_owner_id']; // Set board_owner_id here
    $is_board_archived = (int)$board_row_data['is_archived'];
    $board_updated_at = $board_row_data['updated_at'];
    $is_owner = ($board_owner_id == $user_id);

    if ($is_owner) {
        $permission_level = 'owner';
    } else {
        $collab_sql = "SELECT permission_level FROM Planner_Collaborators WHERE board_id = ? AND user_id = ?";
        $collab_stmt = $connection->prepare($collab_sql);
        if ($collab_stmt) {
            $collab_stmt->bind_param("ii", $board_id, $user_id);
            $collab_stmt->execute();
            $collab_result = $collab_stmt->get_result();
            if ($collab_row = $collab_result->fetch_assoc()) {
                $permission_level = $collab_row['permission_level'];
            }
            $collab_stmt->close();
        } else {
            error_log("get_board_data.php - Prepare collab_sql failed: " . $connection->error);
        }
    }

    // *** THIS IS THE CRUCIAL PART FOR THE DROPDOWN ***
    // Fetch Board Collaborators (and Owner) for Task Assignment
    if ($board_owner_id > 0) { // Ensure board_owner_id is valid before proceeding
        $owner_sql_fetch = "SELECT user_id, username FROM Planner_Users WHERE user_id = ? AND is_deleted = 0"; // Renamed variable to avoid conflict
        $owner_stmt_fetch = $connection->prepare($owner_sql_fetch);
        if ($owner_stmt_fetch) {
            $owner_stmt_fetch->bind_param("i", $board_owner_id);
            $owner_stmt_fetch->execute();
            $owner_res_fetch = $owner_stmt_fetch->get_result();
            if($owner_data_fetch = $owner_res_fetch->fetch_assoc()){
                $board_collaborators_for_assignment[] = [
                    'user_id' => $owner_data_fetch['user_id'],
                    'username' => htmlspecialchars($owner_data_fetch['username']) . " (Owner)"
                ];
            }
            $owner_stmt_fetch->close();
        } else {
            error_log("get_board_data.php - Prepare owner_sql_fetch failed: " . $connection->error);
        }

        $collabs_sql_fetch = "SELECT u.user_id, u.username
                            FROM Planner_Collaborators c
                            JOIN Planner_Users u ON c.user_id = u.user_id
                            WHERE c.board_id = ? AND c.user_id != ? AND u.is_deleted = 0";
        $collabs_stmt_fetch = $connection->prepare($collabs_sql_fetch);
        if ($collabs_stmt_fetch) {
            $collabs_stmt_fetch->bind_param("ii", $board_id, $board_owner_id);
            $collabs_stmt_fetch->execute();
            $collabs_res_fetch = $collabs_stmt_fetch->get_result();
            while($collab_data_fetch = $collabs_res_fetch->fetch_assoc()){
                if ($collab_data_fetch['user_id'] != $board_owner_id) {
                    $board_collaborators_for_assignment[] = [
                        'user_id' => $collab_data_fetch['user_id'],
                        'username' => htmlspecialchars($collab_data_fetch['username'])
                    ];
                }
            }
            $collabs_stmt_fetch->close();
        } else {
            error_log("get_board_data.php - Prepare collabs_sql_fetch failed: " . $connection->error);
        }
        $board_collaborators_for_assignment = array_values(array_unique($board_collaborators_for_assignment, SORT_REGULAR));
    }
    // *** END OF COLLABORATOR FETCHING ***


    // Fetch Columns
    $columns_sql = "SELECT column_id, column_name, column_identifier, column_order FROM Planner_Columns WHERE board_id = ? AND is_deleted = 0 ORDER BY column_order ASC";
    $columns_stmt = $connection->prepare($columns_sql);
    if ($columns_stmt) {
        $columns_stmt->bind_param("i", $board_id);
        $columns_stmt->execute();
        $columns_result_set = $columns_stmt->get_result();
        // Default columns logic (simplified, ensure it matches kanban_content.php if needed for edge cases)
        if ($columns_result_set->num_rows == 0 && ($permission_level === 'owner' || $permission_level === 'admin') && !$is_board_archived) {
             // You might want to replicate the default column creation here if it's critical
             // that get_board_data.php can also create them. For now, assuming they exist.
        } else {
            while ($column_row = $columns_result_set->fetch_assoc()) {
                $board_columns_data[] = [
                    'column_id' => $column_row['column_id'],
                    'column_name' => htmlspecialchars($column_row['column_name']),
                    'column_identifier' => htmlspecialchars($column_row['column_identifier']),
                    'column_order' => $column_row['column_order']
                ];
            }
        }
        $columns_stmt->close();
    } else {
        error_log("get_board_data.php - Prepare columns_sql failed: " . $connection->error);
    }


    // Fetch Tasks
    if (!empty($board_columns_data)) {
        $tasks_sql = "SELECT t.task_id, t.task_name, t.task_description, t.column_id, pc.column_identifier, t.task_order, t.due_date, t.is_completed, t.priority, t.assigned_to_user_id, u_assigned.username as assigned_username
                      FROM Planner_Tasks t
                      JOIN Planner_Columns pc ON t.column_id = pc.column_id
                      LEFT JOIN Planner_Users u_assigned ON t.assigned_to_user_id = u_assigned.user_id AND u_assigned.is_deleted = 0
                      WHERE t.board_id = ? AND t.is_deleted = 0 AND pc.is_deleted = 0
                      ORDER BY pc.column_order ASC, t.task_order ASC";
        $tasks_stmt = $connection->prepare($tasks_sql);
        if ($tasks_stmt) {
            $tasks_stmt->bind_param("i", $board_id);
            $tasks_stmt->execute();
            $tasks_result_set = $tasks_stmt->get_result();
            while ($task_row = $tasks_result_set->fetch_assoc()) {
                $board_tasks_data[] = [
                    'task_id' => $task_row['task_id'],
                    'task_name' => htmlspecialchars($task_row['task_name']),
                    'task_description' => htmlspecialchars($task_row['task_description'] ?? ''),
                    'column_id' => $task_row['column_id'],
                    'column_identifier' => htmlspecialchars($task_row['column_identifier']),
                    'task_order' => $task_row['task_order'],
                    'due_date' => $task_row['due_date'] ? date('Y-m-d', strtotime($task_row['due_date'])) : null,
                    'is_completed' => (int)$task_row['is_completed'],
                    'priority' => htmlspecialchars($task_row['priority']),
                    'assigned_to_user_id' => $task_row['assigned_to_user_id'] ? (int)$task_row['assigned_to_user_id'] : null,
                    'assigned_username' => $task_row['assigned_username'] ? htmlspecialchars($task_row['assigned_username']) : null
                ];
            }
            $tasks_stmt->close();
        } else {
            error_log("get_board_data.php - Prepare tasks_sql failed: " . $connection->error);
        }
    }
    // --- End of Replicated Data Fetching Logic ---

    $output_data = [
        'board_id' => (int)$board_id, // Ensure it's an int
        'board_name' => $board_name_from_db,
        'columns' => $board_columns_data,
        'tasks' => $board_tasks_data,
        'permission_level' => $permission_level,
        'user_id' => (int)$user_id, // Current session user_id, ensure int
        'collaborators' => $board_collaborators_for_assignment, // This must be populated
        'is_archived' => (int)$is_board_archived, // Ensure int
        'updated_at' => $board_updated_at
    ];
    echo json_encode($output_data);

} else {
    echo json_encode(['success' => false, 'message' => 'Board data not found or access denied.']);
}
$access_stmt->close();
$connection->close();
?>