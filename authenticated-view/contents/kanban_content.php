<?php
// kanban_content.php

// Check if user is logged in (assuming session_start() is called in a parent file like index.php or layout.php)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authenticated-view/core/login.php"); // Adjust path as needed
    exit();
}

// Include database connection
require_once '../admin/database/connection.php'; // Adjust path as needed

$user_id = $_SESSION['user_id'];
$board_id = isset($_GET['board_id']) ? intval($_GET['board_id']) : 0;

$board_name = "Kanban Board";
$board_columns_data = [];
$board_tasks_data = [];
$is_owner = false;
$permission_level = 'read'; // Default

if ($board_id > 0) {
    // Check if user has access to this board (owner or collaborator)
    $access_sql = "SELECT b.board_id, b.board_name, b.user_id
                   FROM Planner_Boards b
                   LEFT JOIN Planner_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                   WHERE b.board_id = ? AND b.is_deleted = 0
                   AND (b.user_id = ? OR c.user_id = ?)";

    $access_stmt = $connection->prepare($access_sql);
    $access_stmt->bind_param("iiii", $user_id, $board_id, $user_id, $user_id);
    $access_stmt->execute();
    $access_result = $access_stmt->get_result();

    if ($access_result->num_rows > 0) {
        $board = $access_result->fetch_assoc();
        $board_name = htmlspecialchars($board['board_name']);
        $is_owner = ($board['user_id'] == $user_id);

        if ($is_owner) {
            $permission_level = 'owner';
        } else {
            $collab_sql = "SELECT permission_level FROM Planner_Collaborators
                           WHERE board_id = ? AND user_id = ?";
            $collab_stmt = $connection->prepare($collab_sql);
            $collab_stmt->bind_param("ii", $board_id, $user_id);
            $collab_stmt->execute();
            $collab_result = $collab_stmt->get_result();
            if ($collab_row = $collab_result->fetch_assoc()) {
                $permission_level = $collab_row['permission_level'];
            }
            $collab_stmt->close();
        }
        $can_add_columns = ($permission_level === 'owner' || $permission_level === 'edit' || $permission_level === 'admin');

        $columns_sql = "SELECT column_id, column_name, column_identifier, column_order
                        FROM Planner_Columns
                        WHERE board_id = ? AND is_deleted = 0
                        ORDER BY column_order ASC";
        $columns_stmt = $connection->prepare($columns_sql);
        $columns_stmt->bind_param("i", $board_id);
        $columns_stmt->execute();
        $columns_result_set = $columns_stmt->get_result();

        if ($columns_result_set->num_rows == 0 && $is_owner) {
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
                } else {
                    error_log("Failed to insert default column: " . $insert_col_stmt->error);
                }
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
                                 t.task_order, t.due_date, t.is_completed, t.priority
                          FROM Planner_Tasks t
                          JOIN Planner_Columns pc ON t.column_id = pc.column_id
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
                    'task_description' => htmlspecialchars($task_row['task_description']),
                    'column_id' => $task_row['column_id'],
                    'column_identifier' => htmlspecialchars($task_row['column_identifier']),
                    'task_order' => $task_row['task_order'],
                    'due_date' => $task_row['due_date'] ? date('Y-m-d', strtotime($task_row['due_date'])) : null,
                    'is_completed' => $task_row['is_completed'],
                    'priority' => htmlspecialchars($task_row['priority'])
                ];
            }
            $tasks_stmt->close();
        }
    } else {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p>Board not found or you don\'t have permission to access it.</p>
              </div>';
        $board_id = 0;
    }
    $access_stmt->close();
} else {
     echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
            <p>No board selected. Please select a board from your dashboard.</p>
          </div>';
}

$board_data_for_js = [
    'board_id' => $board_id,
    'columns' => $board_columns_data,
    'tasks' => $board_tasks_data,
    'permission_level' => $permission_level,
    'user_id' => $user_id
];
$board_data_json = json_encode($board_data_for_js);
?>

<!-- Kanban Board Styles and Scripts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

<style>
    .add-placeholder { border: 2px dashed #ccc; border-radius: 0.5rem; background-color: rgba(229, 231, 235, 0.5); transition: all 0.3s; }
    .add-placeholder:hover { background-color: rgba(229, 231, 235, 0.8); border-color: #e63946; }
    .editable:focus { outline: 2px solid #e63946; background-color: #fff; padding: 0.25rem; border-radius: 0.25rem; }
    .task-list { min-height: 100px; }
    .ui-sortable-placeholder { background: #f0f0f0 !important; border: 1px dashed #ccc !important; visibility: visible !important; height: 50px !important; margin-bottom: 0.75rem; } /* Ensure mb-3 like task cards */
    .ui-sortable-helper { box-shadow: 0 4px 8px rgba(0,0,0,0.2); transform: rotate(2deg); }
    .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .permission-badge { background-color: #4A5568; color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; margin-left: 0.5rem; }

    .readonly .add-placeholder#addColumnPlaceholder,
    .readonly .task-list + .add-placeholder,
    .readonly .task-drag-handle,
    .readonly .column-actions button,
    .readonly .task-actions button,
    .readonly .editable[contenteditable="true"] {
        display: none !important;
    }
    .readonly .task-card { cursor: default !important; }
</style>

<div class="flex justify-between items-center border-b pb-6 mb-8">
    <div class="flex items-center">
        <h2 class="text-3xl font-bold text-[#e63946]"><?= $board_name ?></h2>
        <?php if ($permission_level !== 'owner' && $board_id > 0): ?>
            <span class="permission-badge"><?= ucfirst(htmlspecialchars($permission_level)) ?> access</span>
        <?php endif; ?>
    </div>
    <a href="index.php" class="bg-[#e63946] text-white py-3 px-6 rounded-lg font-semibold hover:bg-red-700 transition">
        Back to Dashboard
    </a>
</div>

<?php if ($board_id > 0): ?>
<div class="flex overflow-x-auto pb-4 space-x-6 <?= ($permission_level === 'read') ? 'readonly' : '' ?>" id="kanbanBoard">
    <!-- Columns will be added here by JavaScript -->
    <?php if ($can_add_columns): ?>
    <div class="add-placeholder flex-shrink-0 flex items-center justify-center w-80 h-24 cursor-pointer" id="addColumnPlaceholder">
        <div class="text-gray-500 flex flex-col items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span class="mt-2">Add Column</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Task Modal (Original Structure) -->
<div id="taskModal" class="fixed inset-0 bg-gray-500 bg-opacity-50 flex justify-center items-center hidden z-50">
    <div class="bg-white p-8 rounded-lg shadow-lg w-1/3 max-w-xl"> <!-- Original: w-1/3 -->
        <h3 class="text-2xl font-semibold text-[#e63946] mb-6" id="modalTitle">Create Task</h3>
        <form id="taskForm">
            <input type="hidden" id="taskBoardId" value="<?= $board_id ?>">
            <input type="hidden" id="taskColumnDbId" value="">
            <input type="hidden" id="taskColumnIdentifier" value="">
            <input type="hidden" id="taskId" value="">

            <div class="mb-6"> <!-- Original: mb-6 -->
                <label for="taskTitle" class="block text-lg font-medium text-gray-700">Task Title</label>
                <input type="text" id="taskTitle" class="w-full p-3 mt-2 border border-gray-300 rounded-lg" placeholder="Enter task title" required>
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
                <div class="flex space-x-4 mt-2"> <!-- Original radio buttons -->
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
<?php endif; ?>

<script>
    const boardData = <?= $board_data_json ?>;
    const AJAX_BASE_URL = 'ajax_handlers/';

    function sanitizeHTML(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    }

    $(document).ready(function() {
        if (boardData.board_id > 0) {
            initializeBoard();
            if (boardData.permission_level === 'owner' || boardData.permission_level === 'edit' || boardData.permission_level === 'admin') {
                $('#addColumnPlaceholder').on('click', addNewColumnPrompt);
            }
            $('#taskForm').submit(function(e) {
                e.preventDefault();
                if (boardData.permission_level !== 'read') {
                    saveTask();
                } else {
                    alert('You have read-only access.');
                }
            });
        }
    });

    function initializeBoard() {
        $('#kanbanBoard .kanban-column').remove();
        boardData.columns.forEach(column => {
            renderColumn(column.column_id, column.column_name, column.column_identifier);
        });
        boardData.tasks.forEach(task => {
            const columnDomId = `column-${task.column_identifier}`;
            createTaskCard(columnDomId, task);
        });
        initSortable();
    }

    function renderColumn(columnDbId, columnName, columnIdentifier) {
        const columnDomId = `column-${columnIdentifier}`;
        if ($(`#${columnDomId}`).length > 0) return;

        const canEditDeleteColumn = boardData.permission_level === 'owner';
        const canRenameColumn = boardData.permission_level === 'owner' || boardData.permission_level === 'edit';

        const columnHtml = `
            <div class="flex-shrink-0 bg-gray-50 p-6 rounded-lg shadow-md w-80 kanban-column" id="${columnDomId}" data-column-db-id="${columnDbId}" data-column-identifier="${columnIdentifier}">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-xl font-semibold text-[#e63946] editable" ${canRenameColumn ? 'contenteditable="true"' : ''} title="${canRenameColumn ? 'Click to edit name' : ''}">${sanitizeHTML(columnName)}</h4>
                    ${ (canEditDeleteColumn || canRenameColumn) ? `
                    <div class="column-actions flex space-x-2">
                        ${canRenameColumn ? `
                        <button class="text-gray-500 hover:text-gray-700" onclick="focusColumnTitle('${columnDomId}')" title="Edit column name">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>` : ''}
                        ${canEditDeleteColumn ? `
                        <button class="text-red-500 hover:text-red-700" onclick="removeColumn('${columnDomId}')" title="Delete column">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>` : ''}
                    </div>` : ''}
                </div>
                <div class="task-list min-h-[400px] bg-white p-4 rounded-lg shadow-inner mb-4" id="tasks-${columnDomId}">
                </div>
                ${ (boardData.permission_level !== 'read') ? `
                <div class="add-placeholder flex items-center justify-center h-16 cursor-pointer" onclick="openTaskModal('${columnDomId}')">
                    <div class="text-gray-500 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Add Task</span>
                    </div>
                </div>` : ''}
            </div>`;
        const $addColumnPlaceholder = $('#addColumnPlaceholder');
        if ($addColumnPlaceholder.length) {
            $(columnHtml).insertBefore($addColumnPlaceholder);
        } else {
            $('#kanbanBoard').append(columnHtml);
        }

        if (canRenameColumn) {
            $(`#${columnDomId} h4.editable`).on('blur', function() {
                const newName = $(this).text().trim();
                const colDbId = $(`#${columnDomId}`).data('column-db-id');
                const originalColumn = boardData.columns.find(c => c.column_id == colDbId);
                if (newName && newName !== originalColumn.column_name) {
                    updateColumnName(colDbId, newName, $(this));
                } else { $(this).text(sanitizeHTML(originalColumn.column_name)); }
            }).on('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); $(this).blur(); }
                else if (e.key === 'Escape') {
                    const colDbId = $(`#${columnDomId}`).data('column-db-id');
                    const originalColumn = boardData.columns.find(c => c.column_id == colDbId);
                    $(this).text(sanitizeHTML(originalColumn.column_name)); $(this).blur();
                }
            });
        }
    }

    function focusColumnTitle(columnDomId) {
        const el = $(`#${columnDomId} h4.editable`)[0];
        el.focus();
        const range = document.createRange();
        range.selectNodeContents(el);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    function addNewColumnPrompt() {
        const columnName = prompt("Enter new column name:");
        if (columnName && columnName.trim() !== "") { saveNewColumn(columnName.trim()); }
    }

    function saveNewColumn(columnName) {
        let columnIdentifier = columnName.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
        if (!columnIdentifier) { columnIdentifier = `column-${Date.now()}`; }
        if (boardData.columns.some(col => col.column_identifier === columnIdentifier)) {
            columnIdentifier += `-${Math.random().toString(36).substr(2, 5)}`;
        }
        const maxOrder = boardData.columns.reduce((max, col) => Math.max(max, col.column_order), -1);
        const newOrder = maxOrder + 1;
        $.ajax({
            url: AJAX_BASE_URL + 'save_column.php', type: 'POST',
            data: { board_id: boardData.board_id, column_name: columnName, column_identifier: columnIdentifier, column_order: newOrder },
            dataType: 'json',
            success: function(result) {
                if (result.success) {
                    const newColData = { column_id: result.column_id, column_name: columnName, column_identifier: columnIdentifier, column_order: newOrder };
                    boardData.columns.push(newColData);
                    renderColumn(newColData.column_id, newColData.column_name, newColData.column_identifier);
                    initSortable();
                } else { alert('Error adding column: ' + result.message); }
            },
            error: function(xhr) { alert('Failed to save column. ' + xhr.responseText); }
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
                    originalColumn.column_name = newName;
                    element.text(sanitizeHTML(newName));
                } else {
                    alert('Error updating column name: ' + result.message);
                    element.text(sanitizeHTML(originalColumn.column_name));
                }
            },
            error: function() {
                alert('Failed to update column name.');
                element.text(sanitizeHTML(originalColumn.column_name));
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
            },
            error: function() { alert('Failed to delete column.'); }
        });
    }

    function initSortable() {
        if (boardData.permission_level === 'read') {
            $('.task-list').sortable('destroy'); return;
        }
        $('.task-list').sortable({
            connectWith: '.task-list', handle: '.task-drag-handle',
            placeholder: 'ui-sortable-placeholder', forcePlaceholderSize: true, opacity: 0.8,
            start: function(event, ui) { ui.placeholder.height(ui.item.outerHeight()); },
            stop: function(event, ui) { updateTaskPositions(); }
        }).disableSelection();
    }

    function updateTaskPositions() {
        const tasksToUpdate = [];
        $('.kanban-column').each(function() {
            const columnDbId = $(this).data('column-db-id');
            $(this).find('.task-list .task-card').each(function(index) {
                const taskId = $(this).data('task-id');
                tasksToUpdate.push({ task_id: taskId, column_id: columnDbId, task_order: index });
            });
        });
        if (tasksToUpdate.length > 0) {
            $.ajax({
                url: AJAX_BASE_URL + 'update_task_positions.php', type: 'POST',
                data: { tasks: JSON.stringify(tasksToUpdate), board_id: boardData.board_id },
                dataType: 'json',
                success: function(result) {
                    if (result.success) {
                        tasksToUpdate.forEach(updatedTask => {
                            const taskInBoardData = boardData.tasks.find(t => t.task_id == updatedTask.task_id);
                            if (taskInBoardData) {
                                taskInBoardData.column_id = updatedTask.column_id;
                                taskInBoardData.task_order = updatedTask.task_order;
                                const newColumn = boardData.columns.find(c => c.column_id == updatedTask.column_id);
                                if (newColumn) taskInBoardData.column_identifier = newColumn.column_identifier;
                            }
                        });
                    } else { alert('Error updating task positions: ' + result.message); }
                },
                error: function() { alert('Failed to update task positions.'); }
            });
        }
    }

    function openTaskModal(columnDomId, taskIdToEdit = null) {
        const $columnElement = $(`#${columnDomId}`);
        const columnDbId = $columnElement.data('column-db-id');
        const columnIdentifier = $columnElement.data('column-identifier');
        $('#taskColumnDbId').val(columnDbId);
        $('#taskColumnIdentifier').val(columnIdentifier);
        $('#taskForm')[0].reset();
        $('input[name="priority"][value="medium"]').prop('checked', true); // Default priority for new

        if (taskIdToEdit) {
            const taskId = taskIdToEdit.replace('task-', '');
            const taskData = boardData.tasks.find(t => t.task_id == taskId);
            if (taskData) {
                $('#modalTitle').text('Edit Task');
                $('#taskId').val(taskData.task_id);
                $('#taskTitle').val(taskData.task_name);
                $('#taskDescription').val(taskData.task_description);
                $('#dueDate').val(taskData.due_date); // Assumes YYYY-MM-DD
                $(`input[name="priority"][value="${taskData.priority}"]`).prop('checked', true);
            } else { alert('Task data not found.'); return; }
        } else {
            $('#modalTitle').text('Create Task');
            $('#taskId').val('');
        }
        $('#taskModal').removeClass('hidden');
        $('#taskTitle').focus();
    }

    function closeModal() {
        $('#taskModal').addClass('hidden');
    }

    function saveTask() {
        const taskId = $('#taskId').val();
        const taskDataPayload = {
            task_id: taskId,
            board_id: boardData.board_id,
            column_id: $('#taskColumnDbId').val(),
            task_name: $('#taskTitle').val().trim(),
            task_description: $('#taskDescription').val().trim(),
            due_date: $('#dueDate').val(),
            priority: $('input[name="priority"]:checked').val() // Get from radio
        };
        if (!taskDataPayload.task_name) { alert('Task title is required.'); return; }
        if (!taskDataPayload.column_id) { alert('Column information missing.'); return; }

        $.ajax({
            url: AJAX_BASE_URL + 'save_task.php', type: 'POST', data: taskDataPayload, dataType: 'json',
            success: function(result) {
                if (result.success) {
                    const savedTask = {
                        task_id: result.task_id, board_id: parseInt(boardData.board_id),
                        column_id: parseInt(taskDataPayload.column_id),
                        column_identifier: $('#taskColumnIdentifier').val(),
                        task_name: taskDataPayload.task_name, task_description: taskDataPayload.task_description,
                        due_date: taskDataPayload.due_date, priority: taskDataPayload.priority,
                        task_order: result.task_order, is_completed: 0
                    };
                    if (taskId) {
                        const index = boardData.tasks.findIndex(t => t.task_id == savedTask.task_id);
                        if (index > -1) boardData.tasks[index] = savedTask;
                        updateTaskCard(savedTask);
                    } else {
                        boardData.tasks.push(savedTask);
                        const columnDomIdForNewTask = `column-${savedTask.column_identifier}`;
                        createTaskCard(columnDomIdForNewTask, savedTask);
                    }
                    closeModal();
                } else { alert('Error saving task: ' + result.message); }
            },
            error: function() { alert('Failed to save task.'); }
        });
    }

    function getPriorityStyling(priorityValue) { // Renamed parameter to avoid conflict
        let priorityClass = '';
        let priorityLabel = '';
        switch (priorityValue) {
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
            default:
                priorityClass = 'bg-gray-100 border-gray-300';
                priorityLabel = '<span class="text-gray-600 text-xs font-medium mr-2">N/A</span>';
        }
        return { priorityClass, priorityLabel };
    }

    function createTaskCard(columnDomId, task) {
        const { priorityClass, priorityLabel } = getPriorityStyling(task.priority);
        const dueDateFormatted = task.due_date ? task.due_date : ''; // Original just used task.due_date
        const canEditDeleteTask = boardData.permission_level !== 'read';

        const taskCardHtml = `
            <div id="task-${task.task_id}" class="task-card ${priorityClass} p-4 rounded-lg shadow border-l-4 mb-3 cursor-pointer"
                 data-task-id="${task.task_id}" data-column-id="${task.column_id}" data-due-date="${task.due_date}" data-priority="${task.priority}">
                <div class="flex justify-between items-start">
                    <div class="task-title font-medium">${sanitizeHTML(task.task_name)}</div>
                    ${canEditDeleteTask ? `
                    <div class="task-drag-handle cursor-move ml-2 text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                        </svg>
                    </div>` : ''}
                </div>
                <div class="task-description text-sm text-gray-600 mt-2 line-clamp-3">${sanitizeHTML(task.task_description)}</div>
                <div class="flex justify-between items-center mt-4">
                    <div class="flex items-center">
                        ${priorityLabel}
                        <span class="text-xs text-gray-500">${dueDateFormatted}</span>
                    </div>
                    ${canEditDeleteTask ? `
                    <div class="task-actions flex space-x-2">
                        <button class="text-blue-500 hover:text-blue-700" onclick="event.stopPropagation(); openTaskModal('${columnDomId}', 'task-${task.task_id}')" title="Edit task">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>
                        <button class="text-red-500 hover:text-red-700" onclick="event.stopPropagation(); deleteTask(${task.task_id})" title="Delete task">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>` : ''}
                </div>
            </div>`;
        $(`#tasks-${columnDomId}`).append(taskCardHtml);
    }

    function updateTaskCard(task) {
        $(`#task-${task.task_id}`).remove();
        const columnDomId = `column-${task.column_identifier}`;
        createTaskCard(columnDomId, task);
    }

    function deleteTask(taskId) {
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
            },
            error: function() { alert('Failed to delete task.'); }
        });
    }
</script>