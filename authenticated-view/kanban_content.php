<div class="flex justify-center items-center min-h-screen bg-gray-100 p-4">
  <div class="w-full max-w-7xl bg-white p-8 rounded-lg shadow-lg mx-auto">
      <div class="flex justify-between items-center border-b pb-6 mb-6">
          <h2 class="text-3xl font-bold text-[#e63946]">Kanban Board</h2>
          <a href="dashboard.php" class="bg-[#e63946] text-white py-3 px-6 rounded-lg font-semibold hover:bg-red-700 transition">
              Back to Dashboard
          </a>
      </div>
      <button class="bg-[#e63946] text-white py-3 px-6 rounded-lg mb-6 text-lg" onclick="addColumn()">Add Column</button>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8" id="kanbanBoard"></div>
  </div>
</div>

<!-- Task Modal -->
<div id="taskModal" class="fixed inset-0 bg-gray-500 bg-opacity-50 flex justify-center items-center hidden">
    <div class="bg-white p-8 rounded-lg shadow-lg w-1/3 max-w-xl">
        <h3 class="text-2xl font-semibold text-[#e63946] mb-6" id="modalTitle">Create Task</h3>
        <form id="taskForm">
            <div class="mb-6">
                <label for="taskDescription" class="block text-lg font-medium text-gray-700">Task Description</label>
                <textarea id="taskDescription" class="w-full p-3 mt-2 border border-gray-300 rounded-lg" rows="6" placeholder="Enter task description"></textarea>
            </div>
            <div class="mb-6">
                <label for="dueDate" class="block text-lg font-medium text-gray-700">Due Date</label>
                <input type="date" id="dueDate" class="w-full p-3 mt-2 border border-gray-300 rounded-lg">
            </div>
            <div class="flex justify-between">
                <button type="button" onclick="closeModal()" class="bg-gray-300 text-gray-700 py-3 px-6 rounded-lg text-lg">Cancel</button>
                <button type="submit" class="bg-[#e63946] text-white py-3 px-6 rounded-lg text-lg">Save Task</button>
            </div>
        </form>
    </div>
</div>

<script>
    function addColumn() {
        const columnId = 'column-' + Date.now();
        const columnHtml = `
            <div class="bg-gray-50 p-6 rounded-lg shadow-md" id="${columnId}">
                <div class="flex justify-between items-center mb-4">
                    <h4 contenteditable="true" class="text-xl font-semibold text-[#e63946]">New Column</h4>
                    <button class="text-red-500 text-xl" onclick="removeColumn('${columnId}')">✖</button>
                </div>
                <div class="task-list min-h-[400px] bg-white p-6 rounded-lg shadow-md" id="tasks-${columnId}"></div>
                <button class="add-task bg-[#e63946] text-white py-3 px-6 rounded-lg mt-6 w-full text-lg" onclick="openModal('tasks-${columnId}')">+ Add Task</button>
            </div>`;
        $('#kanbanBoard').append(columnHtml);
        initSortable();
    }

    function removeColumn(columnId) {
        $('#' + columnId).remove();
    }

    function initSortable() {
        $('.task-list').sortable({
            connectWith: '.task-list',
            placeholder: 'bg-gray-300 p-4 rounded-lg',
        }).disableSelection();
    }

    function openModal(column) {
        currentColumn = column;
        $('#modalTitle').text('Create Task');
        $('#taskDescription').val('');
        $('#dueDate').val('');
        $('#taskModal').removeClass('hidden');
    }

    function closeModal() {
        $('#taskModal').addClass('hidden');
    }

    $('#taskForm').submit(function (e) {
        e.preventDefault();
        const description = $('#taskDescription').val().trim();
        const dueDate = $('#dueDate').val();
        if (!description || !dueDate) {
            alert('Please fill in both fields');
            return;
        }
        const taskCardHtml = `<div class="task-card bg-blue-100 p-4 rounded-lg shadow mb-3 cursor-move">
            <div class="text-lg">${description}</div>
            <div class="text-sm text-gray-600 mt-2">Due: ${dueDate}</div>
            <button class="text-sm text-red-500 hover:underline mt-2" onclick="deleteTask(this)">Delete</button>
        </div>`;
        $('#' + currentColumn).append(taskCardHtml);
        closeModal();
    });

    function deleteTask(button) {
        $(button).closest('.task-card').remove();
    }
</script>