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

// --- Replicate data fetching logic from kanban_content.php ---
// This is a simplified example. You'd need to copy the full data fetching logic
// from kanban_content.php (lines ~19 to ~168) here to populate:
// $board_name, $board_columns_data, $board_tasks_data, $board_collaborators_for_assignment,
// $is_owner, $permission_level, $is_board_archived, $board_updated_at

// For brevity, I'm showing the structure. You MUST implement the actual data fetching.
// Example of fetching core board data (you need to add columns, tasks, collaborators etc.)
$board_data = null;
$board_columns_data = [];
$board_tasks_data = [];
$board_collaborators_for_assignment = [];
$permission_level = 'read';
$is_board_archived = 0;
$board_updated_at = null;
$board_name_from_db = "Kanban Board";


$access_sql = "SELECT b.board_id, b.board_name, b.user_id as board_owner_id, b.is_archived, b.updated_at
               FROM Planner_Boards b
               LEFT JOIN Planner_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
               WHERE b.board_id = ? AND b.is_deleted = 0
               AND (b.user_id = ? OR c.user_id = ?)";
$access_stmt = $connection->prepare($access_sql);
$access_stmt->bind_param("iiii", $user_id, $board_id, $user_id, $user_id);
$access_stmt->execute();
$access_result = $access_stmt->get_result();

if ($board_row_data = $access_result->fetch_assoc()) {
    $board_name_from_db = htmlspecialchars($board_row_data['board_name']);
    $board_owner_id = (int)$board_row_data['board_owner_id'];
    $is_board_archived = (int)$board_row_data['is_archived'];
    $board_updated_at = $board_row_data['updated_at'];
    $is_owner = ($board_owner_id == $user_id);

    if ($is_owner) {
        $permission_level = 'owner';
    } else {
        $collab_sql = "SELECT permission_level FROM Planner_Collaborators WHERE board_id = ? AND user_id = ?";
        $collab_stmt = $connection->prepare($collab_sql);
        $collab_stmt->bind_param("ii", $board_id, $user_id);
        $collab_stmt->execute();
        $collab_result = $collab_stmt->get_result();
        if ($collab_row = $collab_result->fetch_assoc()) {
            $permission_level = $collab_row['permission_level'];
        }
        $collab_stmt->close();
    }

    // Fetch Collaborators (simplified, copy from kanban_content.php for full logic)
    $owner_sql = "SELECT user_id, username FROM Planner_Users WHERE user_id = ? AND is_deleted = 0";
    $owner_stmt = $connection->prepare($owner_sql);
    $owner_stmt->bind_param("i", $board_owner_id);
    $owner_stmt->execute();
    $owner_res = $owner_stmt->get_result();
    if($owner_data = $owner_res->fetch_assoc()){
        $board_collaborators_for_assignment[] = ['user_id' => $owner_data['user_id'], 'username' => htmlspecialchars($owner_data['username']) . " (Owner)"];
    }
    $owner_stmt->close();
    // ... (add fetching other collaborators) ...


    // Fetch Columns (simplified, copy from kanban_content.php for full logic)
    $columns_sql = "SELECT column_id, column_name, column_identifier, column_order FROM Planner_Columns WHERE board_id = ? AND is_deleted = 0 ORDER BY column_order ASC";
    $columns_stmt = $connection->prepare($columns_sql);
    $columns_stmt->bind_param("i", $board_id);
    $columns_stmt->execute();
    $columns_result_set = $columns_stmt->get_result();
    while ($column_row = $columns_result_set->fetch_assoc()) {
        $board_columns_data[] = [ /* ... column data ... */ 
            'column_id' => $column_row['column_id'],
            'column_name' => htmlspecialchars($column_row['column_name']),
            'column_identifier' => htmlspecialchars($column_row['column_identifier']),
            'column_order' => $column_row['column_order']
        ];
    }
    $columns_stmt->close();
    // ... (add default column creation if none exist, as in kanban_content.php) ...

    // Fetch Tasks (simplified, copy from kanban_content.php for full logic)
    if (!empty($board_columns_data)) {
        $tasks_sql = "SELECT t.task_id, t.task_name, t.task_description, t.column_id, pc.column_identifier, t.task_order, t.due_date, t.is_completed, t.priority, t.assigned_to_user_id, u_assigned.username as assigned_username
                      FROM Planner_Tasks t
                      JOIN Planner_Columns pc ON t.column_id = pc.column_id
                      LEFT JOIN Planner_Users u_assigned ON t.assigned_to_user_id = u_assigned.user_id AND u_assigned.is_deleted = 0
                      WHERE t.board_id = ? AND t.is_deleted = 0 AND pc.is_deleted = 0
                      ORDER BY pc.column_order ASC, t.task_order ASC";
        $tasks_stmt = $connection->prepare($tasks_sql);
        $tasks_stmt->bind_param("i", $board_id);
        $tasks_stmt->execute();
        $tasks_result_set = $tasks_stmt->get_result();
        while ($task_row = $tasks_result_set->fetch_assoc()) {
            $board_tasks_data[] = [ /* ... task data ... */ 
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
    }
    // --- End of replicated data fetching logic ---

    $output_data = [
        'board_id' => $board_id,
        'board_name' => $board_name_from_db, // Make sure this is fetched
        'columns' => $board_columns_data,
        'tasks' => $board_tasks_data,
        'permission_level' => $permission_level,
        'user_id' => $user_id, // Current session user_id
        'collaborators' => $board_collaborators_for_assignment,
        'is_archived' => $is_board_archived,
        'updated_at' => $board_updated_at // Fetched from DB
    ];
    echo json_encode($output_data);

} else {
    echo json_encode(['success' => false, 'message' => 'Board data not found or access denied.']);
}
$access_stmt->close();
$connection->close();
?>