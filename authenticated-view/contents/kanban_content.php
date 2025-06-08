<?php
// authenticated-view/contents/kanban_content.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../core/login.php"); 
    exit();
}

require_once '../admin/database/connection.php'; 

$user_id = $_SESSION['user_id'];
$board_id = isset($_GET['board_id']) ? intval($_GET['board_id']) : 0;

$board_name_php = "Kanban Board";
$board_columns_data = [];
$board_tasks_data = [];
$board_collaborators_for_assignment = []; 
$is_owner = false;
$permission_level = 'read'; 
$can_add_columns = false; 
$is_board_archived = 0; // Default to not archived

if ($board_id > 0) {
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
        $board_name_php = htmlspecialchars($board_row_data['board_name']);
        $board_owner_id = (int)$board_row_data['board_owner_id'];
        $is_board_archived = (int)$board_row_data['is_archived']; 
        $is_owner = ($board_owner_id == $user_id);
        $board_updated_at = $board_row_data['updated_at']; 

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
        
        $can_manage_content = ($permission_level === 'owner' || $permission_level === 'admin' || $permission_level === 'edit') && !$is_board_archived;
        $can_add_columns = ($permission_level === 'owner' || $permission_level === 'admin') && !$is_board_archived;

        // Fetch Board Collaborators (and Owner) for Task Assignment
        $owner_sql = "SELECT user_id, username FROM Planner_Users WHERE user_id = ? AND is_deleted = 0";
        $owner_stmt = $connection->prepare($owner_sql);
        $owner_stmt->bind_param("i", $board_owner_id);
        $owner_stmt->execute();
        $owner_res = $owner_stmt->get_result();
        if($owner_data = $owner_res->fetch_assoc()){
            $board_collaborators_for_assignment[] = [
                'user_id' => $owner_data['user_id'],
                'username' => htmlspecialchars($owner_data['username']) . " (Owner)"
            ];
        }
        $owner_stmt->close();

        $collabs_sql = "SELECT u.user_id, u.username 
                        FROM Planner_Collaborators c
                        JOIN Planner_Users u ON c.user_id = u.user_id
                        WHERE c.board_id = ? AND c.user_id != ? AND u.is_deleted = 0"; // Exclude owner, ensure active users
        $collabs_stmt = $connection->prepare($collabs_sql);
        $collabs_stmt->bind_param("ii", $board_id, $board_owner_id);
        $collabs_stmt->execute();
        $collabs_res = $collabs_stmt->get_result();
        while($collab_data = $collabs_res->fetch_assoc()){
            // Avoid adding owner again if they somehow got into collaborators table for their own board
            if ($collab_data['user_id'] != $board_owner_id) {
                $board_collaborators_for_assignment[] = [
                    'user_id' => $collab_data['user_id'],
                    'username' => htmlspecialchars($collab_data['username'])
                ];
            }
        }
        $collabs_stmt->close();
        // Remove duplicates just in case (e.g., if owner was listed twice due to data anomaly)
        $board_collaborators_for_assignment = array_values(array_unique($board_collaborators_for_assignment, SORT_REGULAR));


        $columns_sql = "SELECT column_id, column_name, column_identifier, column_order
                        FROM Planner_Columns
                        WHERE board_id = ? AND is_deleted = 0
                        ORDER BY column_order ASC";
        $columns_stmt = $connection->prepare($columns_sql);
        $columns_stmt->bind_param("i", $board_id);
        $columns_stmt->execute();
        $columns_result_set = $columns_stmt->get_result();

        if ($columns_result_set->num_rows == 0 && ($permission_level === 'owner' || $permission_level === 'admin') && !$is_board_archived) {
            $default_columns = [
                ['name' => 'To Do', 'identifier' => 'todo', 'order' => 0],
                ['name' => 'In Progress', 'identifier' => 'in-progress', 'order' => 1],
                ['name' => 'Done', 'identifier' => 'done', 'order' => 2]
            ];
            $insert_col_sql = "INSERT INTO Planner_Columns (board_id, column_name, column_identifier, column_order) VALUES (?, ?, ?, ?)";
            $insert_col_stmt = $connection->prepare($insert_col_sql);
            foreach ($default_columns as $col) {
                $insert_col_stmt->bind_param("issi", $board_id, $col['name'], $col['identifier'], $col['order']);
                if ($insert_col_stmt->execute()) {
                    $board_columns_data[] = [
                        'column_id' => $insert_col_stmt->insert_id,
                        'column_name' => $col['name'],
                        'column_identifier' => $col['identifier'],
                        'column_order' => $col['order']
                    ];
                } else { error_log("Failed to insert default column: " . $insert_col_stmt->error); }
            }
            $insert_col_stmt->close();
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

        if (!empty($board_columns_data)) {
            $tasks_sql = "SELECT t.task_id, t.task_name, t.task_description, t.column_id,
                                 pc.column_identifier,
                                 t.task_order, t.due_date, t.is_completed, t.priority,
                                 t.assigned_to_user_id, u_assigned.username as assigned_username
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
        }
    } else {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p>Board not found or you don\'t have permission to access it.</p></div>';
        $board_id = 0; 
    }
    $access_stmt->close();
} else {
     echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert"><p>No board selected. Please select a board from your dashboard.</p></div>';
}

$board_data_for_js = [
    'board_id' => $board_id,
    'board_name' => $board_name_php,
    'columns' => $board_columns_data,
    'tasks' => $board_tasks_data,
    'permission_level' => $permission_level,
    'user_id' => $user_id,
    'collaborators' => $board_collaborators_for_assignment,
    'is_archived' => $is_board_archived,
    'updated_at' => $board_updated_at
];
$board_data_json = json_encode($board_data_for_js);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

<style>
    .add-placeholder { border: 2px dashed #ccc; border-radius: 0.5rem; background-color: rgba(229, 231, 235, 0.5); transition: all 0.3s; }
    .add-placeholder:hover { background-color: rgba(229, 231, 235, 0.8); border-color: #e63946; }
    .editable:focus { outline: 2px solid #e63946; background-color: #fff; padding: 0.25rem; border-radius: 0.25rem; }
    .task-list { min-height: 100px; }
    .ui-sortable-placeholder { background: #f0f0f0 !important; border: 1px dashed #ccc !important; visibility: visible !important; height: 50px !important; margin-bottom: 0.75rem; }
    .ui-sortable-helper { box-shadow: 0 4px 8px rgba(0,0,0,0.2); transform: rotate(2deg); }
    .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .permission-badge { background-color: #4A5568; color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; margin-left: 0.5rem; }

    .readonly .add-placeholder#addColumnPlaceholder,
    .readonly .task-list + .add-placeholder,
    .readonly .task-drag-handle,
    .readonly .column-actions button:not([title*="View"]),
    .readonly .task-actions button:not([title*="View"]),
    .readonly .editable[contenteditable="true"] {
        display: none !important;
    }
    .readonly .task-card { cursor: default !important; }

    .archived-board-overlay .add-placeholder#addColumnPlaceholder,
    .archived-board-overlay .task-list + .add-placeholder,
    .archived-board-overlay .task-drag-handle,
    .archived-board-overlay .column-actions button,
    .archived-board-overlay .task-actions button,
    .archived-board-overlay .editable[contenteditable="true"] {
        display: none !important;
        cursor: not-allowed !important;
    }
    .archived-board-overlay h4.editable { cursor: default !important; }
    .archived-board-overlay .task-card { cursor: default !important; opacity: 0.7; }


    .task-card.completed { opacity: 0.6; }
    .dark-mode .task-card.completed { background-color: #2c2d3f !important; opacity: 0.5; }
    .task-card.completed .task-title,
    .task-card.completed .task-description,
    .task-card.completed .text-xs.text-gray-500 { 
        text-decoration: line-through;
    }
    .assignee-avatar {
        width: 24px; height: 24px; border-radius: 50%;
        background-color: #718096; color: white;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 0.7rem; font-weight: bold;
        margin-left: 8px;
        border: 1px solid #fff; 
    }
    .dark-mode .assignee-avatar { border-color: #242535; background-color: #4A5568; }
    .kanban-column {
    display: flex; /* Enable flexbox */
    flex-direction: column; /* Stack children vertically */
    /* You might want to set a fixed height or max-height for columns if they can grow too tall */
    /* For example: max-height: 80vh; or a fixed pixel value */
    /* If you set a max-height, the task-list will become scrollable */
}

    .task-list {
        flex-grow: 1; /* Allows the task list to take up available vertical space */
        overflow-y: auto; /* Makes the task list scrollable if content exceeds its height */
        /* Your existing min-height is good, but flex-grow will handle expansion */
    }
</style>

<div class="flex justify-between items-center border-b pb-4 md:pb-6 mb-6 md:mb-8">
    <div class="flex items-center" id="boardHeaderInfo">
        <h2 id="boardNameDisplay" class="text-2xl md:text-3xl font-bold text-[#e63946]"><?= $board_name_php ?></h2>
        <?php if ($is_board_archived): ?>
            <span class="ml-3 bg-yellow-200 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded-full archived-badge">Archived</span>
        <?php endif; ?>
        <?php if ($permission_level !== 'owner' && $board_id > 0): ?>
            <span class="permission-badge"><?= ucfirst(htmlspecialchars($permission_level)) ?> access</span>
        <?php endif; ?>
    </div>
    <a href="index.php" class="bg-[#e63946] text-white py-2 px-4 md:py-3 md:px-6 rounded-lg font-semibold hover:bg-red-700 transition text-sm md:text-base">
        Back to Dashboard
    </a>
</div>

<?php if ($board_id > 0): ?>
<div class="flex overflow-x-auto pb-4 space-x-4 md:space-x-6 <?= ($permission_level === 'read' || $is_board_archived) ? ($is_board_archived ? 'archived-board-overlay' : 'readonly') : '' ?>" id="kanbanBoard">
    <?php if ($can_add_columns): // This already checks for !is_board_archived ?>
    <div class="add-placeholder flex-shrink-0 flex items-center justify-center w-64 md:w-80 h-20 md:h-24 cursor-pointer" id="addColumnPlaceholder">
        <div class="text-gray-500 flex flex-col items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span class="mt-1 md:mt-2 text-sm md:text-base">Add Column</span>
        </div>
    </div>
    <?php endif; ?>
</div>

      
<!-- Task Modal -->
<div id="taskModal" class="fixed inset-0 bg-gray-500 bg-opacity-50 flex justify-center items-center hidden z-50 p-4 overflow-y-auto">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
        <h3 class="text-xl font-semibold text-[#e63946] mb-4" id="modalTitle">Create Task</h3> 

      
<form id="taskForm" class="space-y-4"> 
        <input type="hidden" id="taskBoardId" value="<?= $board_id ?>">
        <input type="hidden" id="taskColumnDbId" value="">
        <input type="hidden" id="taskColumnIdentifier" value="">
        <input type="hidden" id="taskId" value="">

        <div>
            <label for="taskTitle" class="block text-sm font-medium text-gray-700">Task Title</label> 
            <input type="text" id="taskTitle" class="w-full p-2 mt-1 border border-gray-300 rounded-lg" placeholder="Enter task title" required> 
        </div>

        <div>
            <label for="taskDescription" class="block text-sm font-medium text-gray-700">Task Description</label>
            <textarea id="taskDescription" class="w-full p-2 mt-1 border border-gray-300 rounded-lg" rows="3" placeholder="Enter task description"></textarea> 
        </div>
        
        
        <div>
            <div>
                <label for="dueDate" class="block text-sm font-medium text-gray-700">Due Date</label>
                <input type="date" id="dueDate" class="w-full p-2 mt-1 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label for="taskAssignee" class="block text-sm font-medium text-gray-700">Assign To</label>
                <select id="taskAssignee" class="w-full p-2 mt-1 border border-gray-300 rounded-lg bg-white">
                    <option value="">Unassigned</option>
                    <?php foreach ($board_collaborators_for_assignment as $collaborator): ?>
                        <option value="<?= $collaborator['user_id'] ?>"><?= $collaborator['username'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Priority</label>
            <div class="flex flex-wrap gap-x-3 gap-y-1 mt-1"> 
                <label class="flex items-center">
                    <input type="radio" name="priority" value="low" class="mr-1 h-4 w-4 text-green-600 border-gray-300 focus:ring-green-500">
                    <span class="text-sm text-green-700">Low</span> 
                </label>
                <label class="flex items-center">
                    <input type="radio" name="priority" value="medium" class="mr-1 h-4 w-4 text-yellow-600 border-gray-300 focus:ring-yellow-500" checked>
                    <span class="text-sm text-yellow-700">Medium</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="priority" value="high" class="mr-1 h-4 w-4 text-red-600 border-gray-300 focus:ring-red-500">
                    <span class="text-sm text-red-700">High</span>
                </label>
            </div>
        </div>

        <div class="pt-1"> 
            <div class="flex items-center">
                <input type="checkbox" id="taskCompleted" name="taskCompleted" class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                <label for="taskCompleted" class="ml-2 block text-sm font-medium text-gray-700">Mark as Completed</label>
            </div>
        </div>

        <div class="flex justify-between pt-2"> 
            <button type="button" onclick="closeModal()" class="bg-gray-300 text-gray-700 py-2 px-4 rounded-lg text-sm hover:bg-gray-400 transition-colors">Cancel</button> 
            <button type="submit" class="bg-[#e63946] text-white py-2 px-4 rounded-lg text-sm hover:bg-red-700 transition-colors">Save Task</button>
        </div>
    </form>
</div>
</div>

    
<?php endif; ?>

<!-- ... (all your PHP and HTML above this point in kanban_content.php remains the same) ... -->

<script>
    let boardData = <?= $board_data_json ?>;
    const AJAX_BASE_URL = 'ajax_handlers/';

    // --- Polling Variables ---
    let currentBoardUpdatedAt = null;
    let currentBoardIsArchived = null;
    let boardUpdatePoller = null;
    let isLoadingBoardUpdates = false;
    const POLLING_INTERVAL = 7000; // Poll every 7 seconds

    function sanitizeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }

    $(document).ready(function() {
        console.log("KANBAN: Document Ready. Initial boardData from PHP:", JSON.parse(JSON.stringify(boardData)));

        if (boardData.board_id > 0) {
            currentBoardUpdatedAt = boardData.updated_at || null;
            currentBoardIsArchived = parseInt(boardData.is_archived);

            initializeBoard(); // Initial full board render

            $('#taskForm').submit(function(e) {
                e.preventDefault();
                if (boardData.permission_level !== 'read' && !parseInt(boardData.is_archived)) {
                    saveTask();
                } else {
                    alert(parseInt(boardData.is_archived) ? 'This project is archived. No new tasks can be added or edited.' : 'You have read-only access.');
                }
            });

            if (boardUpdatePoller) clearInterval(boardUpdatePoller);
            boardUpdatePoller = setInterval(checkForBoardUpdates, POLLING_INTERVAL);

            document.addEventListener('visibilitychange', function() {
                if (!boardData || boardData.board_id <= 0) return;
                if (document.hidden) {
                    if (boardUpdatePoller) clearInterval(boardUpdatePoller);
                } else {
                    checkForBoardUpdates();
                    if (boardUpdatePoller) clearInterval(boardUpdatePoller);
                    boardUpdatePoller = setInterval(checkForBoardUpdates, POLLING_INTERVAL);
                }
            });

        } else {
            console.log("KANBAN: No valid board_id on initial load. boardData:", JSON.parse(JSON.stringify(boardData)));
            // If no board_id, the PHP part already shows a message.
            // We might still want to hide/disable parts of the UI if #kanbanBoard exists.
            // The PHP already outputs a message, so JS might not need to do much here unless
            // there are JS-driven UI elements that need explicit hiding.
            const $kanbanBoardElement = $('#kanbanBoard');
            if ($kanbanBoardElement.length && !$kanbanBoardElement.find('.bg-yellow-100').length) { // Check if PHP message isn't already there
                 $kanbanBoardElement.empty().html('<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert"><p>No board selected or board data is incomplete. Please select a board from your dashboard.</p></div>');
            }
            const $addColumnPlaceholder = $('#addColumnPlaceholder');
            if ($addColumnPlaceholder.length) $addColumnPlaceholder.hide();
        }
    });

    function checkForBoardUpdates() {
        if (isLoadingBoardUpdates || !boardData || !boardData.board_id || boardData.board_id <= 0) {
            return;
        }
        const cacheBuster = new Date().getTime();
        $.ajax({
            url: `${AJAX_BASE_URL}check_board_update.php?t=${cacheBuster}`,
            type: 'GET',
            data: { board_id: boardData.board_id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const remoteUpdatedAt = response.updated_at;
                    const remoteIsArchived = parseInt(response.is_archived);

                    if (remoteUpdatedAt !== currentBoardUpdatedAt || remoteIsArchived !== currentBoardIsArchived) {
                        console.log('KANBAN: Board changes detected. Fetching new data.');
                        fetchAndReinitializeBoard();
                    }
                } else {
                    console.error('KANBAN: Error checking for board updates:', response.message);
                    if (response.stop_polling) {
                        if (boardUpdatePoller) clearInterval(boardUpdatePoller);
                        // alert("This board is no longer accessible. Further updates will not be loaded.");
                    }
                }
            },
            error: function(xhr) {
                console.error('KANBAN: Failed to connect for board update check.', xhr.responseText);
            }
        });
    }

    function fetchAndReinitializeBoard() {
        if (isLoadingBoardUpdates) return;
        isLoadingBoardUpdates = true;
        console.log('KANBAN: Fetching updated board data...');
        const cacheBuster = new Date().getTime();
        $.ajax({
            url: `${AJAX_BASE_URL}get_board_data.php?t=${cacheBuster}`,
            type: 'GET',
            data: { board_id: boardData.board_id },
            dataType: 'json',
            success: function(newFullBoardData) {
                if (newFullBoardData && newFullBoardData.board_id) { // Check for a valid board_id in response
                    boardData = newFullBoardData;
                    currentBoardUpdatedAt = boardData.updated_at || null;
                    currentBoardIsArchived = parseInt(boardData.is_archived);
                    initializeBoard();
                    console.log('KANBAN: Board re-initialized with new data.');
                } else {
                    console.error('KANBAN: Received invalid data from get_board_data.php. Full response:', newFullBoardData);
                    // Potentially stop polling or show error to user
                    // alert("Error fetching board details. The board might no longer be accessible.");
                    // if (boardUpdatePoller) clearInterval(boardUpdatePoller);
                }
            },
            error: function(xhr) {
                console.error('KANBAN: Failed to fetch full board data.', xhr.responseText);
                // alert("Failed to refresh board data. Please check your connection or try again later.");
            },
            complete: function() {
                isLoadingBoardUpdates = false;
            }
        });
    }

    function initializeBoard() {
        console.log("--- KANBAN: initializeBoard() CALLED ---");
        console.log("Current boardData.board_id:", boardData.board_id);
        console.log("Current boardData.board_name:", boardData.board_name);
        const boardNameDisplayElement = document.getElementById('boardNameDisplay');
        if (boardNameDisplayElement) {
            console.log("HTML content of #boardNameDisplay BEFORE JS update:", boardNameDisplayElement.innerHTML);
        } else {
            console.error("KANBAN: #boardNameDisplay element NOT FOUND!");
        }

        // 1. Clear existing columns from the board
        $('#kanbanBoard .kanban-column').remove();

        // 2. Update Board Name
        if (boardData.board_name && boardNameDisplayElement) {
            $(boardNameDisplayElement).text(sanitizeHTML(boardData.board_name));
            console.log("HTML content of #boardNameDisplay AFTER JS update:", boardNameDisplayElement.innerHTML);
        } else {
            console.warn("KANBAN: Board name in JS boardData or #boardNameDisplay element missing. Name not updated by JS.");
            if (boardNameDisplayElement && (!boardData.board_name && boardData.board_id > 0) ) {
                // This case is problematic: element exists, board_id is valid, but name is missing in JS data.
                // This points to an issue in get_board_data.php not returning board_name.
                $(boardNameDisplayElement).text("Board (Name Error)");
                console.error("KANBAN: boardData.board_name is missing from JS data, but board_id is present!");
            } else if (boardNameDisplayElement && boardData.board_id <= 0) {
                // If no valid board, PHP should have set a default or message.
                // JS shouldn't clear it unless boardData.board_name is explicitly empty.
                // The PHP part already handles displaying "Kanban Board" or an error message.
                // So, if boardData.board_name is the default "Kanban Board", this is fine.
                // If boardData.board_name is empty and board_id is 0, we let PHP's output stand.
                if (boardData.board_name) { // Only update if JS has a name (even default)
                     $(boardNameDisplayElement).text(sanitizeHTML(boardData.board_name));
                }
            }
        }

        // 3. Update Archived Status Badge & Permission Badge
        const $boardHeaderInfo = $('#boardHeaderInfo');
        $boardHeaderInfo.find('.archived-badge').remove();
        $boardHeaderInfo.find('.permission-badge').remove();

        if (parseInt(boardData.is_archived) === 1) {
            $('#boardNameDisplay').after('<span class="ml-3 bg-yellow-200 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded-full archived-badge">Archived</span>');
        }
        // Only show permission badge if it's a valid board and user is not owner
        if (boardData.board_id > 0 && boardData.permission_level && boardData.permission_level !== 'owner') {
            const permissionText = boardData.permission_level.charAt(0).toUpperCase() + boardData.permission_level.slice(1);
            const $archivedBadge = $boardHeaderInfo.find('.archived-badge');
            const permissionBadgeHtml = `<span class="permission-badge ml-2">${sanitizeHTML(permissionText)} access</span>`;
            if ($archivedBadge.length) {
                $archivedBadge.after(permissionBadgeHtml);
            } else {
                 $('#boardNameDisplay').after(permissionBadgeHtml);
            }
        }

        // 4. Handle main board container classes
        $('#kanbanBoard').removeClass('readonly archived-board-overlay');
        if (parseInt(boardData.is_archived) === 1) {
            $('#kanbanBoard').addClass('archived-board-overlay');
        } else if (boardData.permission_level === 'read') {
            $('#kanbanBoard').addClass('readonly');
        }

        // 5. Handle #addColumnPlaceholder
        let $addColumnPlaceholder = $('#addColumnPlaceholder');
        const canAddColumnsJS = (boardData.permission_level === 'owner' || boardData.permission_level === 'admin') && !parseInt(boardData.is_archived);

        if (canAddColumnsJS) {
            if (!$addColumnPlaceholder.length) {
                const addColumnHtml = `
                <div class="add-placeholder flex-shrink-0 flex items-center justify-center w-64 md:w-80 h-20 md:h-24 cursor-pointer" id="addColumnPlaceholder">
                    <div class="text-gray-500 flex flex-col items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        <span class="mt-1 md:mt-2 text-sm md:text-base">Add Column</span>
                    </div>
                </div>`;
                if ($('#kanbanBoard .kanban-column').length) {
                    $('#kanbanBoard .kanban-column:first').before(addColumnHtml);
                } else {
                    // If #kanbanBoard is empty, and we can add columns, append it.
                    // This handles the case where PHP didn't render it because $can_add_columns was false initially.
                    $('#kanbanBoard').append(addColumnHtml);
                }
                $addColumnPlaceholder = $('#addColumnPlaceholder');
            }
            $addColumnPlaceholder.show();
            $addColumnPlaceholder.off('click').on('click', addNewColumnPrompt);
        } else {
            if ($addColumnPlaceholder.length) $addColumnPlaceholder.hide();
        }

        // 6. Render columns
        if (boardData.columns && Array.isArray(boardData.columns)) {
            boardData.columns.forEach(column => {
                renderColumn(column.column_id, column.column_name, column.column_identifier);
            });
        } else {
            console.warn("KANBAN: boardData.columns is not an array or is missing. Columns not rendered.");
        }

        // 7. Render tasks
        if (boardData.tasks && Array.isArray(boardData.tasks)) {
            boardData.tasks.forEach(task => {
                const columnDomId = `column-${task.column_identifier}`;
                if ($(`#tasks-${columnDomId}`).length) {
                     createTaskCard(columnDomId, task);
                } else {
                    // This can happen if a column was just deleted by another user and tasks for it are still in boardData
                    // console.warn(`KANBAN: Task list for column identifier ${task.column_identifier} not found for task ID ${task.task_id}. Task not rendered.`);
                }
            });
        } else {
            console.warn("KANBAN: boardData.tasks is not an array or is missing. Tasks not rendered.");
        }

        // 8. Initialize or destroy sortable
        if (!parseInt(boardData.is_archived) && boardData.permission_level !== 'read') {
            initSortable();
        } else {
            if ($('.task-list').data('ui-sortable')) {
                $('.task-list').sortable('destroy');
            }
        }
        
        // 9. Update assignee dropdown
        const $assigneeSelect = $('#taskAssignee');
        $assigneeSelect.empty().append('<option value="">Unassigned</option>'); 
        if (boardData.collaborators && Array.isArray(boardData.collaborators) && boardData.collaborators.length > 0) {
            boardData.collaborators.forEach(collab => {
                $assigneeSelect.append(`<option value="${collab.user_id}">${sanitizeHTML(collab.username)}</option>`);
            });
        } else {
            console.warn("KANBAN: boardData.collaborators is missing, not an array, or empty. Assignee dropdown might be incomplete.");
        }
        console.log("--- KANBAN: initializeBoard() FINISHED ---");
    }
    
    function renderColumn(columnDbId, columnName, columnIdentifier) {
        const columnDomId = `column-${columnIdentifier}`;
        const canRenameColumn = (boardData.permission_level === 'owner' || boardData.permission_level === 'admin') && !parseInt(boardData.is_archived);
         const canDeleteColumn = (boardData.permission_level === 'owner' || boardData.permission_level === 'admin') && !parseInt(boardData.is_archived);
        const canAddTask = boardData.permission_level !== 'read' && !parseInt(boardData.is_archived);

        const columnHtml = `
            <div class="flex-shrink-0 bg-gray-50 p-4 md:p-6 rounded-lg shadow-md w-72 md:w-80 kanban-column" id="${columnDomId}" data-column-db-id="${columnDbId}" data-column-identifier="${columnIdentifier}">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-lg md:text-xl font-semibold text-[#e63946] editable break-all" ${canRenameColumn ? 'contenteditable="true"' : ''} title="${canRenameColumn ? 'Click to edit name' : ''}">${sanitizeHTML(columnName)}</h4>
                    ${ (canRenameColumn || canDeleteColumn) ? `
                    <div class="column-actions flex space-x-1 md:space-x-2">
                        ${canRenameColumn ? `
                        <button class="text-gray-500 hover:text-gray-700 p-1" onclick="focusColumnTitle('${columnDomId}')" title="Edit column name">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                        </button>` : ''}
                        ${canDeleteColumn ? `
                        <button class="text-red-500 hover:text-red-700 p-1" onclick="removeColumn('${columnDomId}')" title="Delete column">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>` : ''}
                    </div>` : ''}
                </div>
                <div class="task-list min-h-[300px] md:min-h-[400px] bg-white p-3 md:p-4 rounded-lg shadow-inner mb-4" id="tasks-${columnDomId}">
                </div>
                ${ canAddTask ? `
                <div class="add-placeholder flex items-center justify-center h-12 md:h-16 cursor-pointer" onclick="openTaskModal('${columnDomId}')">
                    <div class="text-gray-500 flex items-center text-sm md:text-base">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        <span>Add Task</span>
                    </div>
                </div>` : ''}
            </div>`;
        const $addColumnPlaceholderLocal = $('#addColumnPlaceholder'); 
        if ($addColumnPlaceholderLocal.length) {
            $(columnHtml).insertBefore($addColumnPlaceholderLocal);
        } else {
            $('#kanbanBoard').append(columnHtml);
        }

        if (canRenameColumn) {
            $(`#${columnDomId} h4.editable`).on('blur', function() {
                const newName = $(this).text().trim();
                const colDbId = $(this).closest('.kanban-column').data('column-db-id');
                const originalColumn = boardData.columns.find(c => c.column_id == colDbId);
                if (originalColumn && newName && newName !== originalColumn.column_name) {
                    updateColumnName(colDbId, newName, $(this));
                } else if (originalColumn) { $(this).text(sanitizeHTML(originalColumn.column_name)); }
            }).on('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); $(this).blur(); }
                else if (e.key === 'Escape') {
                    const colDbId = $(this).closest('.kanban-column').data('column-db-id');
                    const originalColumn = boardData.columns.find(c => c.column_id == colDbId);
                    if (originalColumn) { $(this).text(sanitizeHTML(originalColumn.column_name)); }
                    $(this).blur();
                }
            });
        }
    }

    function focusColumnTitle(columnDomId) {
        const el = $(`#${columnDomId} h4.editable`)[0];
        if(el) { el.focus(); const range = document.createRange(); range.selectNodeContents(el); const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range); }
    }

    function addNewColumnPrompt() {
        const columnName = prompt("Enter new column name:");
        if (columnName && columnName.trim() !== "") { saveNewColumn(columnName.trim()); }
    }

    function saveNewColumn(columnName) {
        let columnIdentifier = columnName.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
        if (!columnIdentifier) { columnIdentifier = `column-${Date.now()}`; }
        let tempIdentifier = columnIdentifier;
        let counter = 1;
        while (boardData.columns.some(col => col.column_identifier === tempIdentifier)) {
            tempIdentifier = `${columnIdentifier}-${counter++}`;
        }
        columnIdentifier = tempIdentifier;

        const maxOrder = boardData.columns.reduce((max, col) => Math.max(max, col.column_order), -1);
        const newOrder = maxOrder + 1;
        $.ajax({
            url: AJAX_BASE_URL + 'save_column.php', type: 'POST',
            data: { board_id: boardData.board_id, column_name: columnName, column_identifier: columnIdentifier, column_order: newOrder },
            dataType: 'json',
            success: function(result) {
                if (result.success) {
                    const newColData = { 
                        column_id: result.column_id, 
                        column_name: columnName, 
                        column_identifier: result.column_identifier || columnIdentifier, 
                        column_order: newOrder 
                    };
                    boardData.columns.push(newColData);
                    // Sort columns by order before rendering, in case the new order isn't just at the end
                    boardData.columns.sort((a, b) => a.column_order - b.column_order);
                    // Re-render all columns to ensure correct order if middle-insertion happens (though unlikely with current logic)
                    // For simplicity, just rendering the new one. If order becomes complex, re-render all.
                    renderColumn(newColData.column_id, newColData.column_name, newColData.column_identifier);
                    if (!parseInt(boardData.is_archived)) initSortable();
                } else { alert('Error adding column: ' + result.message); }
            }, error: function(xhr) { alert('Failed to save column. ' + xhr.responseText); }
        });
    }

    function updateColumnName(columnDbId, newName, element) {
        const originalColumn = boardData.columns.find(c => c.column_id == columnDbId);
        $.ajax({
            url: AJAX_BASE_URL + 'update_column.php', type: 'POST',
            data: { column_id: columnDbId, column_name: newName, board_id: boardData.board_id },
            dataType: 'json',
            success: function(result) {
                if (result.success) { 
                    if(originalColumn) originalColumn.column_name = newName; 
                    element.text(sanitizeHTML(newName)); 
                } 
                else { 
                    alert('Error updating column name: ' + result.message); 
                    if(originalColumn) element.text(sanitizeHTML(originalColumn.column_name));
                }
            }, error: function() { 
                alert('Failed to update column name.'); 
                if(originalColumn) element.text(sanitizeHTML(originalColumn.column_name));
            }
        });
    }

    function removeColumn(columnDomId) {
        if (!confirm('Are you sure you want to delete this column and ALL its tasks? This cannot be undone.')) return;
        const columnDbId = $(`#${columnDomId}`).data('column-db-id');
        $.ajax({
            url: AJAX_BASE_URL + 'delete_column.php', type: 'POST',
            data: { column_id: columnDbId, board_id: boardData.board_id },
            dataType: 'json',
            success: function(result) {
                if (result.success) {
                    $(`#${columnDomId}`).remove();
                    boardData.columns = boardData.columns.filter(col => col.column_id != columnDbId);
                    boardData.tasks = boardData.tasks.filter(task => task.column_id != columnDbId);
                } else { alert('Error deleting column: ' + result.message); }
            }, error: function() { alert('Failed to delete column.'); }
        });
    }

    function initSortable() {
    if ($('.task-list').data('ui-sortable')) {
        $('.task-list').sortable('destroy');
    }
    if (boardData.permission_level === 'read' || parseInt(boardData.is_archived) === 1) {
        return;
    }
    $('.task-list').sortable({
        connectWith: '.task-list',
        handle: '.task-drag-handle',
        placeholder: 'ui-sortable-placeholder',
        forcePlaceholderSize: true,
        opacity: 0.8,
        helper: function(event, ui) { // Custom helper function
            // Clone the original item to preserve its width and styles
            var $clone = $(ui).clone();
            // Set the width of the clone to the width of the original item
            // This helps prevent the helper from becoming wider than the column
            $clone.width($(ui).outerWidth());
            return $clone;
        },
        start: function(event, ui) {
            ui.placeholder.height(ui.item.outerHeight());
            ui.placeholder.width(ui.item.outerWidth()); // Good to set placeholder width too
            // Optional: Add a class to the body to temporarily hide overflow if needed
            // $('body').addClass('dragging-task');
        },
        stop: function(event, ui) {
            updateTaskPositions();
            // Optional: Remove the class from the body
            // $('body').removeClass('dragging-task');
        }
    }).disableSelection();
}

    function updateTaskPositions() {
        const tasksToUpdate = [];
        $('.kanban-column').each(function() {
            const columnDbId = $(this).data('column-db-id');
            $(this).find('.task-list .task-card').each(function(index) {
                const taskId = $(this).data('task-id');
                const taskInBoardData = boardData.tasks.find(t => t.task_id == taskId);
                if (taskInBoardData) {
                     tasksToUpdate.push({ 
                        task_id: taskId, 
                        column_id: columnDbId, 
                        task_order: index 
                    });
                }
            });
        });

        if (tasksToUpdate.length > 0) {
            $.ajax({
                url: AJAX_BASE_URL + 'update_task_positions.php', type: 'POST',
                data: { tasks: JSON.stringify(tasksToUpdate), board_id: boardData.board_id },
                dataType: 'json',
                success: function(result) {
                    if (result.success) {
                        tasksToUpdate.forEach(updatedTaskInfo => {
                            const taskInBoardData = boardData.tasks.find(t => t.task_id == updatedTaskInfo.task_id);
                            if (taskInBoardData) {
                                taskInBoardData.column_id = updatedTaskInfo.column_id;
                                taskInBoardData.task_order = updatedTaskInfo.task_order;
                                const newColumn = boardData.columns.find(c => c.column_id == updatedTaskInfo.column_id);
                                if (newColumn) {
                                    taskInBoardData.column_identifier = newColumn.column_identifier;
                                }
                            }
                        });
                    } else { 
                        alert('Error updating task positions: ' + result.message); 
                        fetchAndReinitializeBoard(); 
                    }
                }, error: function() { 
                    alert('Failed to update task positions.'); 
                    fetchAndReinitializeBoard(); 
                }
            });
        }
    }

    function openTaskModal(columnDomId, taskIdToEdit = null) {
        if (parseInt(boardData.is_archived) === 1 && !taskIdToEdit) {
             alert("This project is archived. New tasks cannot be added.");
             return;
        }
        const $columnElement = $(`#${columnDomId}`);
        const columnDbId = $columnElement.data('column-db-id');
        const columnIdentifier = $columnElement.data('column-identifier');
        
        $('#taskForm')[0].reset(); 
        $('#taskBoardId').val(boardData.board_id); 
        $('#taskColumnDbId').val(columnDbId);
        $('#taskColumnIdentifier').val(columnIdentifier);
        $('input[name="priority"][value="medium"]').prop('checked', true); 
        $('#taskCompleted').prop('checked', false); 

        // Assignee select is populated by initializeBoard, so it should be up-to-date when modal opens

        if (taskIdToEdit) {
            const taskId = taskIdToEdit.replace('task-', '');
            const taskData = boardData.tasks.find(t => t.task_id == taskId);
            if (taskData) {
                $('#modalTitle').text('Edit Task');
                $('#taskId').val(taskData.task_id);
                $('#taskTitle').val(taskData.task_name);
                $('#taskDescription').val(taskData.task_description);
                $('#dueDate').val(taskData.due_date);
                $(`input[name="priority"][value="${taskData.priority}"]`).prop('checked', true);
                $('#taskCompleted').prop('checked', taskData.is_completed == 1);
                $('#taskAssignee').val(taskData.assigned_to_user_id || ''); 
            } else { 
                alert('Task data not found for editing.'); 
                return; 
            }
        } else {
            $('#modalTitle').text('Create Task');
            $('#taskId').val('');
            $('#taskAssignee').val(''); 
        }
        
        const canEditContent = boardData.permission_level !== 'read' && !parseInt(boardData.is_archived);

        if (parseInt(boardData.is_archived) === 1 && taskIdToEdit) { // Viewing archived task
            $('#taskForm input, #taskForm textarea, #taskForm select, #taskForm button[type="submit"]').prop('disabled', true);
            $('#modalTitle').text('View Task Details (Archived)');
        } else if (boardData.permission_level === 'read' && taskIdToEdit) { // Viewing read-only task
             $('#taskForm input, #taskForm textarea, #taskForm select, #taskForm button[type="submit"]').prop('disabled', true);
            $('#modalTitle').text('View Task Details (Read-only)');
        } else if (!canEditContent && !taskIdToEdit) { // Trying to create task on archived/read-only board
             $('#taskForm input, #taskForm textarea, #taskForm select, #taskForm button[type="submit"]').prop('disabled', true);
             // This case should ideally be caught before opening modal, but as a safeguard:
             alert(parseInt(boardData.is_archived) ? 'This project is archived. New tasks cannot be added.' : 'You have read-only access and cannot create tasks.');
             return; // Don't show modal
        }
        else { // Creating new or editing on active/editable board
             $('#taskForm input, #taskForm textarea, #taskForm select, #taskForm button[type="submit"]').prop('disabled', false);
        }

        $('#taskModal').removeClass('hidden');
        $('#taskTitle').focus();
    }

    function closeModal() { $('#taskModal').addClass('hidden'); }

    function saveTask() {
        const taskId = $('#taskId').val();
        const taskDataPayload = {
            task_id: taskId,
            board_id: $('#taskBoardId').val(),
            column_id: $('#taskColumnDbId').val(),
            task_name: $('#taskTitle').val().trim(),
            task_description: $('#taskDescription').val().trim(),
            due_date: $('#dueDate').val() || null,
            priority: $('input[name="priority"]:checked').val(),
            is_completed: $('#taskCompleted').is(':checked') ? 1 : 0,
            assigned_to_user_id: $('#taskAssignee').val() || null 
        };

        if (!taskDataPayload.task_name) { alert('Task title is required.'); return; }
        if (!taskDataPayload.column_id) { alert('Column information missing.'); return; }
        if (!taskDataPayload.board_id) { alert('Board information missing.'); return; }

        $.ajax({
            url: AJAX_BASE_URL + 'save_task.php', type: 'POST', data: taskDataPayload, dataType: 'json',
            success: function(result) {
                if (result.success) {
                    let assigneeUsername = null;
                    if (taskDataPayload.assigned_to_user_id) {
                        const assignee = boardData.collaborators.find(c => c.user_id == taskDataPayload.assigned_to_user_id);
                        if (assignee) { assigneeUsername = assignee.username.replace(" (Owner)", "").trim(); }
                    }
                    const savedTask = {
                        task_id: result.task_id, 
                        board_id: parseInt(taskDataPayload.board_id),
                        column_id: parseInt(taskDataPayload.column_id),
                        column_identifier: $('#taskColumnIdentifier').val(), 
                        task_name: taskDataPayload.task_name, 
                        task_description: taskDataPayload.task_description,
                        due_date: taskDataPayload.due_date, 
                        priority: taskDataPayload.priority,
                        task_order: result.task_order, 
                        is_completed: taskDataPayload.is_completed,
                        assigned_to_user_id: taskDataPayload.assigned_to_user_id ? parseInt(taskDataPayload.assigned_to_user_id) : null,
                        assigned_username: assigneeUsername
                    };

                    if (taskId) { 
                        const index = boardData.tasks.findIndex(t => t.task_id == savedTask.task_id);
                        if (index > -1) {
                            boardData.tasks[index] = savedTask;
                        } else { 
                            boardData.tasks.push(savedTask); 
                        }
                        updateTaskCard(savedTask); 
                    } else { 
                        boardData.tasks.push(savedTask);
                        createTaskCard(`column-${savedTask.column_identifier}`, savedTask); 
                    }
                    closeModal();
                } else { alert('Error saving task: ' + result.message); }
            },
            error: function(xhr) { alert('Failed to save task. ' + xhr.responseText); }
        });
    }
    
    function getAssigneeAvatar(userId, username) {
        if (!userId || !username) return '';
        const cleanUsername = username.replace(" (Owner)", "").trim();
        const nameParts = cleanUsername.split(' ');
        let initials = '';
        if (nameParts.length > 1 && nameParts[0] && nameParts[nameParts.length - 1]) {
            initials = nameParts[0].charAt(0) + nameParts[nameParts.length - 1].charAt(0);
        } else if (cleanUsername.length > 0) {
            initials = cleanUsername.substring(0, 2);
        } else {
            initials = 'U'; 
        }
        return `<span class="assignee-avatar" title="${sanitizeHTML(cleanUsername)}">${initials.toUpperCase()}</span>`;
    }

    function createTaskCard(columnDomId, task) {
        const { priorityClass, priorityLabel } = getPriorityStyling(task.priority);
        const dueDateFormatted = task.due_date ? new Date(task.due_date.replace(/-/g, '\/')).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '';
        const canEditDeleteTask = boardData.permission_level !== 'read' && !parseInt(boardData.is_archived);
        const assigneeHtml = task.assigned_to_user_id && task.assigned_username ? getAssigneeAvatar(task.assigned_to_user_id, task.assigned_username) : '';
        const isCompletedClass = task.is_completed == 1 ? 'completed' : '';

        const taskCardHtml = `
            <div id="task-${task.task_id}" class="task-card ${priorityClass} ${isCompletedClass} p-3 md:p-4 rounded-lg shadow border-l-4 mb-3 ${canEditDeleteTask ? 'cursor-grab' : 'cursor-default'}" 
                 data-task-id="${task.task_id}" data-column-id="${task.column_id}" data-due-date="${task.due_date || ''}" data-priority="${task.priority}" data-completed="${task.is_completed}" data-assigned-to="${task.assigned_to_user_id || ''}">
                <div class="flex justify-between items-start mb-1">
                    <div class="task-title font-medium text-sm md:text-base break-all ${task.is_completed == 1 ? 'line-through' : ''}">${sanitizeHTML(task.task_name)}</div>
                    ${canEditDeleteTask ? `
                    <div class="task-drag-handle cursor-move ml-2 text-gray-400 hover:text-gray-600 p-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 md:h-5 md:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                    </div>` : ''}
                </div>
                ${task.task_description ? `<div class="task-description text-xs md:text-sm text-gray-600 mt-1 line-clamp-3 ${task.is_completed == 1 ? 'line-through' : ''}">${sanitizeHTML(task.task_description)}</div>` : ''}
                <div class="flex justify-between items-center mt-2 md:mt-3">
                    <div class="flex items-center">
                        ${priorityLabel}
                        ${dueDateFormatted ? `<span class="text-xs text-gray-500 ${task.is_completed == 1 ? 'line-through' : ''}">${dueDateFormatted}</span>` : ''}
                    </div>
                    <div class="flex items-center">
                        ${assigneeHtml}
                        ${canEditDeleteTask ? `
                        <div class="task-actions flex space-x-1 md:space-x-2 ml-2">
                            <button class="text-blue-500 hover:text-blue-700 p-0.5" onclick="openTaskModal('${columnDomId}', 'task-${task.task_id}')" title="Edit task">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 md:h-4 md:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            </button>
                            <button class="text-red-500 hover:text-red-700 p-0.5" onclick="deleteTask(${task.task_id})" title="Delete task">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 md:h-4 md:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>` : (parseInt(boardData.is_archived) === 1 || boardData.permission_level === 'read' ? `<button class="text-blue-500 hover:text-blue-700 p-0.5" onclick="openTaskModal('${columnDomId}', 'task-${task.task_id}')" title="View task details"><svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 md:h-4 md:w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 10a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1zM9 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd" /></svg></button>` : '')}
                    </div>
                </div>
            </div>`;
        const $taskList = $(`#tasks-${columnDomId}`);
        if ($taskList.length) {
            $taskList.append(taskCardHtml);
        }
    }

    function updateTaskCard(task) {
        const $existingCard = $(`#task-${task.task_id}`);
        if ($existingCard.length) {
            $existingCard.remove(); 
        }
        const columnForTask = boardData.columns.find(col => col.column_id == task.column_id);
        if (columnForTask) {
            createTaskCard(`column-${columnForTask.column_identifier}`, task); 
        } else {
            console.error("KANBAN: Column not found for updated task, cannot re-render card:", task);
        }
    }

    function deleteTask(taskId) {
        if (parseInt(boardData.is_archived) === 1) {
            alert("This project is archived. Tasks cannot be deleted.");
            return;
        }
        if (!confirm('Are you sure you want to delete this task?')) return;
        $.ajax({
            url: AJAX_BASE_URL + 'delete_task.php', type: 'POST',
            data: { task_id: taskId, board_id: boardData.board_id },
            dataType: 'json',
            success: function(result) {
                if (result.success) {
                    $(`#task-${taskId}`).remove();
                    boardData.tasks = boardData.tasks.filter(t => t.task_id != taskId);
                } else { alert('Error deleting task: ' + result.message); }
            }, error: function() { alert('Failed to delete task.'); }
        });
    }

    function getPriorityStyling(priorityValue) {
        let priorityClass = 'border-gray-300'; 
        let priorityLabel = `<span class="text-xs font-medium mr-2 text-gray-600">N/A</span>`;
        switch (priorityValue) {
            case 'low':
                priorityClass = 'border-green-400'; 
                priorityLabel = '<span class="text-xs font-medium mr-2 px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">LOW</span>';
                break;
            case 'medium':
                priorityClass = 'border-yellow-400';
                priorityLabel = '<span class="text-xs font-medium mr-2 px-1.5 py-0.5 rounded-full bg-yellow-100 text-yellow-700">MEDIUM</span>';
                break;
            case 'high':
                priorityClass = 'border-red-400';
                priorityLabel = '<span class="text-xs font-medium mr-2 px-1.5 py-0.5 rounded-full bg-red-100 text-red-700">HIGH</span>';
                break;
        }
        return { priorityClass, priorityLabel };
    }
</script>