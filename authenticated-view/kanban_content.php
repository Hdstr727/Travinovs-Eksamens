<?php
// kanban_content.php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once '../admin/database/connection.php';

$user_id = $_SESSION['user_id'];
$board_id = isset($_GET['board_id']) ? intval($_GET['board_id']) : 0;

// Fetch board details if board_id is provided
$board_name = "Kanban Board";
$board_columns = [];

if ($board_id > 0) {
    // Check if user has access to this board (either as owner or collaborator)
    $access_sql = "SELECT b.board_id, b.board_name, b.user_id 
                  FROM Planotajs_Boards b
                  LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id 
                  WHERE b.board_id = ? 
                  AND (b.user_id = ? OR c.user_id = ?)
                  AND b.is_deleted = 0";
    
    $access_stmt = $connection->prepare($access_sql);
    $access_stmt->bind_param("iii", $board_id, $user_id, $user_id);
    $access_stmt->execute();
    $access_result = $access_stmt->get_result();
    
    if ($access_result->num_rows > 0) {
        $board = $access_result->fetch_assoc();
        $board_name = $board['board_name'];
        $is_owner = ($board['user_id'] == $user_id);
        
        // Fetch columns (we'll use task_status as columns)
        $columns_sql = "SELECT DISTINCT task_status FROM Planotajs_Tasks 
                        WHERE board_id = ? AND is_deleted = 0
                        ORDER BY task_order";
        $columns_stmt = $connection->prepare($columns_sql);
        $columns_stmt->bind_param("i", $board_id);
        $columns_stmt->execute();
        $columns_result = $columns_stmt->get_result();
        
        $columns = [];
        // Add default columns if none exist yet
        if ($columns_result->num_rows == 0) {
            $columns = ['todo', 'in-progress', 'done'];
        } else {
            while ($column = $columns_result->fetch_assoc()) {
                $columns[] = $column['task_status'];
            }
        }
        
        // Fetch tasks for this board
        $tasks_sql = "SELECT task_id, task_name, task_description, task_status, task_order, 
                     due_date, is_completed, priority FROM Planotajs_Tasks 
                     WHERE board_id = ? AND is_deleted = 0
                     ORDER BY task_status, task_order";
        $tasks_stmt = $connection->prepare($tasks_sql);
        $tasks_stmt->bind_param("i", $board_id);
        $tasks_stmt->execute();
        $tasks_result = $tasks_stmt->get_result();
        
        $board_tasks = [];
        while ($task = $tasks_result->fetch_assoc()) {
            $board_tasks[] = $task;
        }
        
        $tasks_stmt->close();
        $columns_stmt->close();
    } else {
        // Board not found or user doesn't have permission
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p>Board not found or you don\'t have permission to access it.</p>
              </div>';
    }
    $access_stmt->close();
}

// Fetch user's permission level for this board
$permission_level = 'read'; // Default to read-only
if ($board_id > 0) {
    // Check if user is the owner (full permissions)
    $owner_check_sql = "SELECT user_id FROM Planotajs_Boards WHERE board_id = ? AND user_id = ?";
    $owner_check_stmt = $connection->prepare($owner_check_sql);
    $owner_check_stmt->bind_param("ii", $board_id, $user_id);
    $owner_check_stmt->execute();
    $owner_result = $owner_check_stmt->get_result();
    
    if ($owner_result->num_rows > 0) {
        $permission_level = 'owner'; // User is the owner
    } else {
        // Check collaborator permission level
        $collab_sql = "SELECT permission_level FROM Planotajs_Collaborators 
                      WHERE board_id = ? AND user_id = ?";
        $collab_stmt = $connection->prepare($collab_sql);
        $collab_stmt->bind_param("ii", $board_id, $user_id);
        $collab_stmt->execute();
        $collab_result = $collab_stmt->get_result();
        
        if ($collab_result->num_rows > 0) {
            $collab = $collab_result->fetch_assoc();
            $permission_level = $collab['permission_level'];
        }
        $collab_stmt->close();
    }
    $owner_check_stmt->close();
}

// Convert the data to JSON for JavaScript to use
$board_data = [
    'board_id' => $board_id,
    'columns' => isset($columns) ? $columns : [],
    'tasks' => isset($board_tasks) ? $board_tasks : [],
    'permission_level' => $permission_level
];
$board_data_json = json_encode($board_data);
?>

<!-- Kanban Board Styles and Scripts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

<style>
    .add-placeholder {
        border: 2px dashed #ccc;
        border-radius: 0.5rem;
        background-color: rgba(229, 231, 235, 0.5);
        transition: all 0.3s;
    }
    .add-placeholder:hover {
        background-color: rgba(229, 231, 235, 0.8);
        border-color: #e63946;
    }
    .editable:focus {
        outline: 2px solid #e63946;
        background-color: #fff;
        padding: 0.25rem;
        border-radius: 0.25rem;
    }
    .readonly .add-placeholder, .readonly .task-drag-handle, 
    .readonly button, .readonly .editable[contenteditable="true"] {
        display: none;
    }
    .permission-badge {
        background-color: #4A5568;
        color: white;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        margin-left: 0.5rem;
    }
</style>

<div class="flex justify-between items-center border-b pb-6 mb-8">
    <div class="flex items-center">
        <h2 class="text-3xl font-bold text-[#e63946]"><?= htmlspecialchars($board_name) ?></h2>
        <?php if (isset($permission_level) && $permission_level != 'owner'): ?>
            <span class="permission-badge"><?= ucfirst($permission_level) ?> access</span>
        <?php endif; ?>
    </div>
    <a href="index.php" class="bg-[#e63946] text-white py-3 px-6 rounded-lg font-semibold hover:bg-red-700 transition">
        Back to Dashboard
    </a>
</div>

<div class="flex overflow-x-auto pb-4 space-x-6 <?= ($permission_level == 'read') ? 'readonly' : '' ?>" id="kanbanBoard">
    <!-- Columns will be added here by JavaScript -->
    <div class="add-placeholder flex-shrink-0 flex items-center justify-center w-80 h-24 cursor-pointer" id="addColumnPlaceholder">
        <div class="text-gray-500 flex flex-col items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span class="mt-2">Add Column</span>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div id="taskModal" class="fixed inset-0 bg-gray-500 bg-opacity-50 flex justify-center items-center hidden z-50">
    <div class="bg-white p-8 rounded-lg shadow-lg w-1/3 max-w-xl">
        <h3 class="text-2xl font-semibold text-[#e63946] mb-6" id="modalTitle">Create Task</h3>
        <form id="taskForm">
            <input type="hidden" id="taskColumnId" value="">
            <input type="hidden" id="taskId" value="">
            <div class="mb-6">
                <label for="taskTitle" class="block text-lg font-medium text-gray-700">Task Title</label>
                <input type="text" id="taskTitle" class="w-full p-3 mt-2 border border-gray-300 rounded-lg" placeholder="Enter task title">
            </div>
            <div class="mb-6">
                <label for="taskDescription" class="block text-lg font-medium text-gray-700">Task Description</label>
                <textarea id="taskDescription" class="w-full p-3 mt-2 border border-gray-300 rounded-lg" rows="6" placeholder="Enter task description"></textarea>
            </div>
            <div class="mb-6">
                <label for="dueDate" class="block text-lg font-medium text-gray-700">Due Date</label>
                <input type="date" id="dueDate" class="w-full p-3 mt-2 border border-gray-300 rounded-lg">
            </div>
            <div class="mb-6">
                <label class="block text-lg font-medium text-gray-700">Priority</label>
                <div class="flex space-x-4 mt-2">
                    <label class="flex items-center">
                        <input type="radio" name="priority" value="low" class="mr-2">
                        <span class="text-green-600">Low</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="priority" value="medium" class="mr-2" checked>
                        <span class="text-yellow-600">Medium</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="priority" value="high" class="mr-2">
                        <span class="text-red-600">High</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-between">
                <button type="button" onclick="closeModal()" class="bg-gray-300 text-gray-700 py-3 px-6 rounded-lg text-lg">Cancel</button>
                <button type="submit" class="bg-[#e63946] text-white py-3 px-6 rounded-lg text-lg">Save Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Pass the PHP board data to JavaScript -->
<script>
    const boardData = <?= $board_data_json ?>;
</script>

<script>
    $(document).ready(function() {
    // Initialize the board from data
    initializeBoard();
    
    // Add column when clicking the placeholder
    $('#addColumnPlaceholder').on('click', function() {
        if (boardData.permission_level === 'read') {
            alert('You have read-only access to this board.');
            return;
        }
        
        const columnName = prompt("Enter column name:");
        if (columnName) {
            addColumn(columnName);
        }
    });

    // Initialize form submission
    $('#taskForm').submit(function(e) {
        e.preventDefault();
        saveTask();
    });
});

function initializeBoard() {
    // First create the columns
    if (boardData.columns.length === 0) {
        // Create default columns if none exist
        addColumn("Todo");
        addColumn("In Progress");
        addColumn("Done");
    } else {
        boardData.columns.forEach(columnStatus => {
            // Convert task_status to a display name (replace hyphens with spaces and capitalize)
            const columnName = columnStatus.replace(/-/g, ' ')
                .replace(/\w\S*/g, txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
            addColumn(columnName, columnStatus);
        });
    }
    
    // Then add tasks to columns
    boardData.tasks.forEach(task => {
        const columnId = `column-${task.task_status}`;
        
        // Create task card
        createTaskCard(columnId, task);
    });
    
    // Initialize sortable functionality
    initSortable();
}

function addColumn(columnName, columnStatus = '') {
    // If no status is provided, create a machine-friendly version of the name
    const status = columnStatus || columnName.toLowerCase().replace(/\s+/g, '-');
    const columnId = `column-${status}`;
    
    // Check if this column already exists
    if ($(`#${columnId}`).length > 0) {
        return; // Column already exists
    }
    
    const columnHtml = `
        <div class="flex-shrink-0 bg-gray-50 p-6 rounded-lg shadow-md w-80" id="${columnId}" data-status="${status}">
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-xl font-semibold text-[#e63946] editable" contenteditable="${boardData.permission_level !== 'read'}">${columnName}</h4>
                <div class="flex space-x-2">
                    <button class="text-gray-500 hover:text-gray-700" onclick="editColumn('${columnId}')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </button>
                    <button class="text-red-500 hover:text-red-700" onclick="removeColumn('${columnId}')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="task-list min-h-[400px] bg-white p-4 rounded-lg shadow-inner mb-4" id="tasks-${columnId}">
                <!-- Tasks will be added here -->
            </div>
            <div class="add-placeholder flex items-center justify-center h-16 cursor-pointer" onclick="openTaskModal('${columnId}')">
                <div class="text-gray-500 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add Task</span>
                </div>
            </div>
        </div>`;
    
    // Insert the new column before the add placeholder
    $(columnHtml).insertBefore('#addColumnPlaceholder');
    
    // Add change event listener for the column title
    $(`#${columnId} h4.editable`).on('blur', function() {
        // Save column name change
        const newName = $(this).text().trim();
        const status = $(`#${columnId}`).data('status');
        
        // We're not updating column names in DB for this implementation
        // as we're using the task_status field to track columns
    });
    
    // Save new column to database if not already in our data
    if (!boardData.columns.includes(status)) {
        boardData.columns.push(status);
        
        // Add default task to create this column status in DB
        if (boardData.board_id) {
            $.ajax({
                url: 'ajax_handlers/save_task.php',
                type: 'POST',
                data: {
                    board_id: boardData.board_id,
                    task_name: 'Initialize Column',
                    task_description: 'System task to initialize column',
                    task_status: status,
                    due_date: '',
                    priority: 'medium',
                    is_system: 1
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        // Column initialized
                        console.log('Column created successfully');
                        
                        // Remove the system task as it was just to initialize the column
                        $.ajax({
                            url: 'ajax_handlers/delete_task.php',
                            type: 'POST',
                            data: {
                                task_id: result.task_id
                            }
                        });
                    }
                }
            });
        }
    }
}

function editColumn(columnId) {
    // Only allow editing if user has proper permissions
    if (boardData.permission_level === 'read') {
        alert('You have read-only access to this board.');
        return;
    }
    
    // Focus on the column title to make it editable
    $(`#${columnId} h4`).focus();
}

function removeColumn(columnId) {
    // Only allow deleting if user has proper permissions
    if (boardData.permission_level === 'read' || boardData.permission_level === 'write') {
        alert('You don\'t have permission to delete columns.');
        return;
    }
    
    if (confirm('Are you sure you want to delete this column and all its tasks?')) {
        const status = $(`#${columnId}`).data('status');
        
        // Get task IDs in this column to delete them
        const taskIds = [];
        $(`#tasks-${columnId} .task-card`).each(function() {
            const taskId = $(this).attr('id').replace('task-', '');
            if ($.isNumeric(taskId)) {
                taskIds.push(taskId);
            }
        });
        
        // Delete tasks from database
        if (taskIds.length > 0) {
            $.ajax({
                url: 'ajax_handlers/delete_column_tasks.php',
                type: 'POST',
                data: {
                    board_id: boardData.board_id,
                    task_status: status
                },
                success: function(response) {
                    console.log('Tasks deleted successfully');
                }
            });
        }
        
        // Remove column from DOM
        $(`#${columnId}`).remove();
        
        // Remove from boardData
        const index = boardData.columns.indexOf(status);
        if (index > -1) {
            boardData.columns.splice(index, 1);
        }
    }
}

function initSortable() {
    // Only make sortable if user has sufficient permissions
    if (boardData.permission_level === 'read') {
        return;
    }
    
    $('.task-list').sortable({
        connectWith: '.task-list',
        placeholder: 'bg-gray-300 p-4 rounded-lg my-2',
        handle: '.task-drag-handle',
        stop: function(event, ui) {
            // Get the task ID and new column ID
            const taskId = ui.item.attr('id').replace('task-', '');
            const newColumnId = ui.item.parent().attr('id').replace('tasks-column-', '');
            
            // Update task order
            updateTaskPositions();
        }
    }).disableSelection();
}

function updateTaskPositions() {
    // Only allow updates if user has proper permissions
    if (boardData.permission_level === 'read') {
        return;
    }
    
    // Go through each column
    $('.task-list').each(function() {
        const columnId = $(this).attr('id').replace('tasks-', '');
        const columnStatus = $(`#${columnId}`).data('status');
        
        // Get all tasks in order
        const tasks = [];
        $(this).find('.task-card').each(function(index) {
            const taskId = $(this).attr('id').replace('task-', '');
            if ($.isNumeric(taskId)) {
                tasks.push({
                    task_id: taskId,
                    task_order: index,
                    task_status: columnStatus
                });
            }
        });
        
        // Update task positions in database
        if (tasks.length > 0) {
            $.ajax({
                url: 'ajax_handlers/update_task_positions.php',
                type: 'POST',
                data: {
                    tasks: JSON.stringify(tasks)
                },
                success: function(response) {
                    console.log('Task positions updated successfully');
                }
            });
        }
    });
}

function openTaskModal(columnId, taskId = null) {
    // Check permissions
    if (boardData.permission_level === 'read') {
        // If read-only and trying to view existing task
        if (taskId) {
            // Allow viewing but disable editing
            const taskIdNum = taskId.replace('task-', '');
            const $task = $(`#task-${taskIdNum}`);
            
            alert(`Task: ${$task.find('.task-title').text()}\n\nDescription: ${$task.find('.task-description').text()}\n\nDue Date: ${$task.data('due-date')}\n\nPriority: ${$task.data('priority')}`);
        } else {
            alert('You have read-only access to this board.');
        }
        return;
    }
    
    $('#taskColumnId').val(columnId);
    
    if (taskId) {
        // Edit existing task
        const taskIdNum = taskId.replace('task-', '');
        $('#modalTitle').text('Edit Task');
        $('#taskId').val(taskIdNum);
        
        // Get task data from the DOM
        const $task = $(`#task-${taskIdNum}`);
        $('#taskTitle').val($task.find('.task-title').text());
        $('#taskDescription').val($task.find('.task-description').text());
        $('#dueDate').val($task.data('due-date'));
        $(`input[name="priority"][value="${$task.data('priority')}"]`).prop('checked', true);
    } else {
        // New task
        $('#modalTitle').text('Create Task');
        $('#taskId').val('');
        $('#taskTitle').val('');
        $('#taskDescription').val('');
        $('#dueDate').val('');
        $('input[name="priority"][value="medium"]').prop('checked', true);
    }
    
    $('#taskModal').removeClass('hidden');
}

function closeModal() {
    $('#taskModal').addClass('hidden');
}

function saveTask() {
    // Check permissions first
    if (boardData.permission_level === 'read') {
        alert('You have read-only access to this board.');
        closeModal();
        return;
    }
    
    const columnId = $('#taskColumnId').val();
    const taskId = $('#taskId').val();
    const title = $('#taskTitle').val().trim();
    const description = $('#taskDescription').val().trim();
    const dueDate = $('#dueDate').val();
    const priority = $('input[name="priority"]:checked').val();
    const columnStatus = $(`#${columnId}`).data('status');
    
    if (!title) {
        alert('Please enter a task title');
        return;
    }
    
    // Save to database
    $.ajax({
        url: 'ajax_handlers/save_task.php',
        type: 'POST',
        data: {
            task_id: taskId,
            board_id: boardData.board_id,
            task_name: title,
            task_description: description,
            task_status: columnStatus,
            due_date: dueDate,
            priority: priority
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                const newTaskId = result.task_id;
                
                if (taskId) {
                    // Update existing task card
                    updateTaskCard(columnId, {
                        task_id: newTaskId,
                        task_name: title,
                        task_description: description,
                        due_date: dueDate,
                        priority: priority
                    });
                } else {
                    // Create new task card
                    createTaskCard(columnId, {
                        task_id: newTaskId,
                        task_name: title,
                        task_description: description,
                        due_date: dueDate,
                        priority: priority
                    });
                    
                    // Update task positions
                    updateTaskPositions();
                }
                
                closeModal();
            } else {
                alert('Error saving task: ' + result.message);
            }
        },
        error: function() {
            alert('Error connecting to server. Please try again.');
        }
    });
}

function createTaskCard(columnId, task) {
    // Get priority class based on selected value
    let priorityClass = '';
    let priorityLabel = '';
    
    switch (task.priority) {
        case 'low':
            priorityClass = 'bg-green-100 border-green-300';
            priorityLabel = '<span class="text-green-600 text-xs font-medium mr-2">LOW</span>';
            break;
        case 'medium':
            priorityClass = 'bg-yellow-100 border-yellow-300';
            priorityLabel = '<span class="text-yellow-600 text-xs font-medium mr-2">MEDIUM</span>';
            break;
        case 'high':
            priorityClass = 'bg-red-100 border-red-300';
            priorityLabel = '<span class="text-red-600 text-xs font-medium mr-2">HIGH</span>';
            break;
    }
    
    const taskCardHtml = `
        <div id="task-${task.task_id}" class="task-card ${priorityClass} p-4 rounded-lg shadow border-l-4 mb-3 cursor-pointer" 
             data-due-date="${task.due_date}" data-priority="${task.priority}">
            <div class="flex justify-between items-start">
                <div class="task-title font-medium">${task.task_name}</div>
                <div class="task-drag-handle cursor-move ml-2 text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                    </svg>
                </div>
            </div>
            <div class="task-description text-sm text-gray-600 mt-2 line-clamp-3">${task.task_description}</div>
            <div class="flex justify-between items-center mt-4">
                <div class="flex items-center">
                    ${priorityLabel}
                    <span class="text-xs text-gray-500">${task.due_date}</span>
                </div>
                <div class="flex space-x-2">
                    <button class="text-blue-500 hover:text-blue-700" onclick="event.stopPropagation(); openTaskModal('${columnId}', 'task-${task.task_id}')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </button>
                    <button class="text-red-500 hover:text-red-700" onclick="event.stopPropagation(); deleteTask(${task.task_id})">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>`;
    
    $(`#tasks-${columnId}`).append(taskCardHtml);
}

function updateTaskCard(columnId, task) {
    // Get priority class based on selected value
    let priorityClass = '';
    let priorityLabel = '';
    
    switch (task.priority) {
        case 'low':
            priorityClass = 'bg-green-100 border-green-300';
            priorityLabel = '<span class="text-green-600 text-xs font-medium mr-2">LOW</span>';
            break;
        case 'medium':
            priorityClass = 'bg-yellow-100 border-yellow-300';
            priorityLabel = '<span class="text-yellow-600 text-xs font-medium mr-2">MEDIUM</span>';
            break;
        case 'high':
            priorityClass = 'bg-red-100 border-red-300';
            priorityLabel = '<span class="text-red-600 text-xs font-medium mr-2">HIGH</span>';
            break;
    }
    
    const taskCardHtml = `
        <div id="task-${task.task_id}" class="task-card ${priorityClass} p-4 rounded-lg shadow border-l-4 mb-3 cursor-pointer" 
             data-due-date="${task.due_date}" data-priority="${task.priority}">
            <div class="flex justify-between items-start">
                <div class="task-title font-medium">${task.task_name}</div>
                <div class="task-drag-handle cursor-move ml-2 text-gray-400 hover:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                    </svg>
                </div>
            </div>
            <div class="task-description text-sm text-gray-600 mt-2 line-clamp-3">${task.task_description}</div>
            <div class="flex justify-between items-center mt-4">
                <div class="flex items-center">
                    ${priorityLabel}
                    <span class="text-xs text-gray-500">${task.due_date}</span>
                </div>
                <div class="flex space-x-2">
                    <button class="text-blue-500 hover:text-blue-700" onclick="event.stopPropagation(); openTaskModal('${columnId}', 'task-${task.task_id}')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </button>
                    <button class="text-red-500 hover:text-red-700" onclick="event.stopPropagation(); deleteTask(${task.task_id})">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>`;
    
    $(`#task-${task.task_id}`).replaceWith(taskCardHtml);
}

function deleteTask(taskId) {
    if (confirm('Are you sure you want to delete this task?')) {
        // Delete from database
        $.ajax({
            url: 'ajax_handlers/delete_task.php',
            type: 'POST',
            data: {
                task_id: taskId
            },
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    // Remove from DOM
                    $(`#task-${taskId}`).remove();
                    
                    // Update task positions
                    updateTaskPositions();
                } else {
                    alert('Error deleting task: ' + result.message);
                }
            },
            error: function() {
                alert('Error connecting to server. Please try again.');
            }
        });
    }
}
</script>

