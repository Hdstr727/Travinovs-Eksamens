<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user information from the session
$username = $_SESSION['username'];

// You can fetch more data from the database if necessary

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Planotajs</title>z`
    <link rel="stylesheet" href="style.css">
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

            <!-- Add your dashboard content here, like a task list, calendar, etc. -->
            
            <div class="task-list">
                <ul>
                    <!-- Example Task List -->
                    <li>Task 1 - In Progress</li>
                    <li>Task 2 - Completed</li>
                    <li>Task 3 - Pending</li>
                </ul>
            </div>

            <!-- You can also display user-specific information from the database here -->
        </div>
    </div>
</body>
</html>
