<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user information from the session
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Kanban Board - Planotajs</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col items-center p-6">
  <div class="w-full max-w-5xl bg-white p-6 rounded-lg shadow-lg">
    <div class="flex justify-between items-center border-b pb-4 mb-4">
      <h2 class="text-2xl font-bold text-[#e63946]">Kanban Board</h2>
      <a href="index.php" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">
        Back to Dashboard
      </a>
    </div>
    <button class="bg-[#e63946] text-white py-2 px-4 rounded-lg mb-4" onclick="addColumn()">Add Column</button>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="kanbanBoard">
      <!-- Columns will be added dynamically -->
    </div>
  </div>

  <!-- Task Modal -->
  <div id="taskModal" class="fixed inset-0 bg-gray-500 bg-opacity-50 flex justify-center items-center hidden">
    <div class="bg-white p-6 rounded-lg shadow-lg w-96">
      <h3 class="text-2xl font-semibold text-[#e63946] mb-4" id="modalTitle">Create Task</h3>
      <form id="taskForm">
        <div class="mb-4">
          <label for="taskDescription" class="block text-sm font-medium text-gray-700">Task Description</label>
          <textarea id="taskDescription" class="w-full p-2 mt-2 border border-gray-300 rounded-lg" rows="4" placeholder="Enter task description"></textarea>
        </div>
        <div class="mb-4">
          <label for="dueDate" class="block text-sm font-medium text-gray-700">Due Date</label>
          <input type="date" id="dueDate" class="w-full p-2 mt-2 border border-gray-300 rounded-lg">
        </div>
        <div class="flex justify-between">
          <button type="button" onclick="closeModal()" class="bg-gray-300 text-gray-700 py-2 px-4 rounded-lg">Cancel</button>
          <button type="submit" class="bg-[#e63946] text-white py-2 px-4 rounded-lg">Save Task</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    let currentColumn = '';
    let editingTaskElement = null;
    
    function addColumn() {
      const columnId = 'column-' + Date.now();
      const columnHtml = `
        <div class="bg-gray-50 p-4 rounded-lg shadow-md" id="${columnId}">
          <div class="flex justify-between items-center mb-2">
            <h4 contenteditable="true" class="text-lg font-semibold text-[#e63946]">New Column</h4>
            <button class="text-red-500" onclick="removeColumn('${columnId}')">✖</button>
          </div>
          <div class="task-list min-h-[200px] bg-white p-4 rounded-lg shadow-md" id="tasks-${columnId}"></div>
          <button class="add-task bg-[#e63946] text-white py-2 px-4 rounded-lg mt-4 w-full" onclick="openModal('tasks-${columnId}')">+ Add Task</button>
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
        placeholder: 'bg-gray-300 p-3 rounded-lg',
      }).disableSelection();
    }

    function openModal(column, task = null) {
      currentColumn = column;
      if (task) {
        $('#modalTitle').text('Edit Task');
        $('#taskDescription').val(task.description);
        $('#dueDate').val(task.dueDate);
      } else {
        $('#modalTitle').text('Create Task');
        $('#taskDescription').val('');
        $('#dueDate').val('');
      }
      $('#taskModal').removeClass('hidden');
    }

    function closeModal() {
      $('#taskModal').addClass('hidden');
      editingTaskElement = null;
    }

    $('#taskForm').submit(function (e) {
      e.preventDefault();
      const description = $('#taskDescription').val().trim();
      const dueDate = $('#dueDate').val();
      if (!description || !dueDate) {
        alert('Please fill in both fields');
        return;
      }
      if (editingTaskElement) {
        editingTaskElement.data('description', description);
        editingTaskElement.data('due-date', dueDate);
        editingTaskElement.html(`${description} <br>
          <span class="text-sm text-gray-600">Due: ${dueDate}</span>
          <div class="task-controls mt-2 flex justify-between">
            <button class="text-sm text-blue-500 hover:underline" onclick="editTask(this)">Edit</button>
            <button class="text-sm text-red-500 hover:underline" onclick="deleteTask(this)">Delete</button>
          </div>`);
      } else {
        addTaskCard({ description, dueDate });
      }
      closeModal();
    });

    function addTaskCard(task) {
      const taskCardHtml = `<div class="task-card bg-blue-100 p-3 rounded-lg shadow mb-2 cursor-move" data-description="${task.description}" data-due-date="${task.dueDate}">
          ${task.description} <br>
          <span class="text-sm text-gray-600">Due: ${task.dueDate}</span>
          <div class="task-controls mt-2 flex justify-between">
            <button class="text-sm text-blue-500 hover:underline" onclick="editTask(this)">Edit</button>
            <button class="text-sm text-red-500 hover:underline" onclick="deleteTask(this)">Delete</button>
          </div>
        </div>`;
      $('#' + currentColumn).append(taskCardHtml);
    }

    function deleteTask(button) {
      $(button).closest('.task-card').remove();
    }

    function editTask(button) {
      const taskCard = $(button).closest('.task-card');
      const description = taskCard.data('description');
      const dueDate = taskCard.data('due-date');
      editingTaskElement = taskCard;
      currentColumn = taskCard.closest('.task-list').attr('id');
      openModal(currentColumn, { description, dueDate });
    }
  </script>
</body>
</html>
