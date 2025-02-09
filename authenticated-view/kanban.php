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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban Board - Planotajs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col items-center p-6">
    <div class="w-full max-w-5xl bg-white p-6 rounded-lg shadow-lg">
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <h2 class="text-2xl font-bold text-[#e63946]">Kanban Board</h2>
            <a href="index.php" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Back to Dashboard</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- To Do Column -->
            <div class="bg-gray-50 p-4 rounded-lg shadow-md">
                <h4 class="text-lg font-semibold text-[#e63946] mb-2">To Do</h4>
                <div class="task-list min-h-[200px] bg-white p-4 rounded-lg shadow-md" id="todo">
                    <div class="task-card bg-blue-100 p-3 rounded-lg shadow mb-2 cursor-move">Task 1 - Description of task... <br><span class="text-sm text-gray-600">Due: 2025-02-10</span></div>
                    <div class="task-card bg-blue-100 p-3 rounded-lg shadow mb-2 cursor-move">Task 2 - Description of task... <br><span class="text-sm text-gray-600">Due: 2025-02-15</span></div>
                </div>
            </div>

            <!-- In Progress Column -->
            <div class="bg-gray-50 p-4 rounded-lg shadow-md">
                <h4 class="text-lg font-semibold text-[#e63946] mb-2">In Progress</h4>
                <div class="task-list min-h-[200px] bg-white p-4 rounded-lg shadow-md" id="in-progress">
                    <div class="task-card bg-yellow-100 p-3 rounded-lg shadow mb-2 cursor-move">Task 3 - Description of task... <br><span class="text-sm text-gray-600">Due: 2025-02-17</span></div>
                </div>
            </div>

            <!-- Completed Column -->
            <div class="bg-gray-50 p-4 rounded-lg shadow-md">
                <h4 class="text-lg font-semibold text-[#e63946] mb-2">Completed</h4>
                <div class="task-list min-h-[200px] bg-white p-4 rounded-lg shadow-md" id="completed">
                    <div class="task-card bg-green-100 p-3 rounded-lg shadow mb-2 cursor-move">Task 4 - Description of task... <br><span class="text-sm text-gray-600">Completed: 2025-02-05</span></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(function () {
            $(".task-list").sortable({
                connectWith: ".task-list",
                placeholder: "bg-gray-300 p-3 rounded-lg",
                stop: function (event, ui) {
                    console.log("Task moved!");
                }
            }).disableSelection();
        });
    </script>
</body>
</html>