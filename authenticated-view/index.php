<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user information from the session
$username = $_SESSION['username'];

// Example: Fetch tasks from the database if needed
// $tasks = fetchTasksFromDatabase();  // You can fetch tasks for this user
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Planotajs</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <div class="content">
            <h3>Your Tasks:</h3>
            <p>Here, you can manage and view your tasks, progress, and deadlines.</p>

            <!-- Task Management Section (Trello-like layout) -->
            <div class="task-container">
                <!-- To Do Column -->
                <div class="task-column">
                    <h4>To Do</h4>
                    <div class="task-list">
                        <div class="task-card">
                            <p>Task 1 - Description of task...</p>
                            <span>Due: 2025-02-10</span>
                            <button class="task-card-btn">Move to In Progress</button>
                        </div>
                        <div class="task-card">
                            <p>Task 2 - Description of task...</p>
                            <span>Due: 2025-02-15</span>
                            <button class="task-card-btn">Move to In Progress</button>
                        </div>
                    </div>
                </div>

                <!-- In Progress Column -->
                <div class="task-column">
                    <h4>In Progress</h4>
                    <div class="task-list">
                        <div class="task-card">
                            <p>Task 3 - Description of task...</p>
                            <span>Due: 2025-02-17</span>
                            <button class="task-card-btn">Move to Completed</button>
                        </div>
                    </div>
                </div>

                <!-- Completed Column -->
                <div class="task-column">
                    <h4>Completed</h4>
                    <div class="task-list">
                        <div class="task-card">
                            <p>Task 4 - Description of task...</p>
                            <span>Completed: 2025-02-05</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- You can also display user-specific information from the database here -->
        </div>
    </div>
</body>
</html>
