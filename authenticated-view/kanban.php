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
    <link rel="stylesheet" href="../style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h2>Kanban Board</h2>
            <a href="index.php" class="back-btn">Back to Dashboard</a>
        </div>

        <div class="kanban-board">
            <!-- To Do Column -->
            <div class="kanban-column" id="todo">
                <h4>To Do</h4>
                <div class="task-list">
                    <div class="task-card" draggable="true">
                        <p>Task 1 - Description of task...</p>
                        <span>Due: 2025-02-10</span>
                    </div>
                    <div class="task-card" draggable="true">
                        <p>Task 2 - Description of task...</p>
                        <span>Due: 2025-02-15</span>
                    </div>
                </div>
            </div>

            <!-- In Progress Column -->
            <div class="kanban-column" id="in-progress">
                <h4>In Progress</h4>
                <div class="task-list">
                    <div class="task-card" draggable="true">
                        <p>Task 3 - Description of task...</p>
                        <span>Due: 2025-02-17</span>
                    </div>
                </div>
            </div>

            <!-- Completed Column -->
            <div class="kanban-column" id="completed">
                <h4>Completed</h4>
                <div class="task-list">
                    <div class="task-card" draggable="true">
                        <p>Task 4 - Description of task...</p>
                        <span>Completed: 2025-02-05</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(function () {
            $(".task-list").sortable({
                connectWith: ".task-list",
                placeholder: "task-placeholder",
                stop: function (event, ui) {
                    // Optional: Save the new task positions in the database
                    console.log("Task moved!");
                }
            }).disableSelection();
        });
    </script>
</body>
</html>
