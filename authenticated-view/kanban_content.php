<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Kanban Board</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
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
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex justify-center w-full pt-16 pb-16 px-4">
        <div class="w-full max-w-8xl bg-white p-8 rounded-lg shadow-lg mx-auto">
            <div class="flex justify-between items-center border-b pb-6 mb-8">
                <h2 class="text-3xl font-bold text-[#e63946]">Kanban Board</h2>
                <a href="dashboard.php" class="bg-[#e63946] text-white py-3 px-6 rounded-lg font-semibold hover:bg-red-700 transition">
                    Back to Dashboard
                </a>
            </div>

            <div class="flex overflow-x-auto pb-4 space-x-6" id="kanbanBoard">
                <!-- Columns will be added here -->
                <div class="add-placeholder flex-shrink-0 flex items-center justify-center w-80 h-24 cursor-pointer" id="addColumnPlaceholder">
                    <div class="text-gray-500 flex flex-col items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="mt-2">Add Column</span>
                    </div>
                </div>
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

    <script>
        $(document).ready(function() {
            // Add column when clicking the placeholder
            $('#addColumnPlaceholder').on('click', function() {
                addColumn();
            });

            // Initialize form submission
            $('#taskForm').submit(function(e) {
                e.preventDefault();
                saveTask();
            });
        });

        function addColumn() {
            const columnId = 'column-' + Date.now();
            const columnHtml = `
                <div class="flex-shrink-0 bg-gray-50 p-6 rounded-lg shadow-md w-80" id="${columnId}">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-xl font-semibold text-[#e63946] editable" contenteditable="true">New Column</h4>
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
            initSortable();
        }

        function editColumn(columnId) {
            // Focus on the column title to make it editable
            $(`#${columnId} h4`).focus();
        }

        function removeColumn(columnId) {
            if (confirm('Are you sure you want to delete this column and all its tasks?')) {
                $(`#${columnId}`).remove();
            }
        }

        function initSortable() {
            $('.task-list').sortable({
                connectWith: '.task-list',
                placeholder: 'bg-gray-300 p-4 rounded-lg my-2',
                handle: '.task-drag-handle',
            }).disableSelection();
        }

        function openTaskModal(columnId, taskId = null) {
            $('#taskColumnId').val(columnId);
            
            if (taskId) {
                // Edit existing task
                $('#modalTitle').text('Edit Task');
                $('#taskId').val(taskId);
                
                // Get task data from the DOM
                const $task = $(`#${taskId}`);
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
            const columnId = $('#taskColumnId').val();
            const taskId = $('#taskId').val() || 'task-' + Date.now();
            const title = $('#taskTitle').val().trim();
            const description = $('#taskDescription').val().trim();
            const dueDate = $('#dueDate').val();
            const priority = $('input[name="priority"]:checked').val();
            
            if (!title) {
                alert('Please enter a task title');
                return;
            }
            
            // Get priority class based on selected value
            let priorityClass = '';
            let priorityLabel = '';
            
            switch (priority) {
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
                <div id="${taskId}" class="task-card ${priorityClass} p-4 rounded-lg shadow border-l-4 mb-3 cursor-pointer" 
                     data-due-date="${dueDate}" data-priority="${priority}">
                    <div class="flex justify-between items-start">
                        <div class="task-title font-medium">${title}</div>
                        <div class="task-drag-handle cursor-move ml-2 text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                            </svg>
                        </div>
                    </div>
                    <div class="task-description text-sm text-gray-600 mt-2 line-clamp-3">${description}</div>
                    <div class="flex justify-between items-center mt-4">
                        <div class="flex items-center">
                            ${priorityLabel}
                            <span class="text-xs text-gray-500">${dueDate}</span>
                        </div>
                        <div class="flex space-x-2">
                            <button class="text-blue-500 hover:text-blue-700" onclick="event.stopPropagation(); openTaskModal('${columnId}', '${taskId}')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                            <button class="text-red-500 hover:text-red-700" onclick="event.stopPropagation(); deleteTask('${taskId}')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>`;
            
            if ($(`#${taskId}`).length > 0) {
                // Update existing task
                $(`#${taskId}`).replaceWith(taskCardHtml);
            } else {
                // Add new task
                $(`#tasks-${columnId}`).append(taskCardHtml);
            }
            
            closeModal();
        }

        function deleteTask(taskId) {
            if (confirm('Are you sure you want to delete this task?')) {
                $(`#${taskId}`).remove();
            }
        }
    </script>
</body>
</html>