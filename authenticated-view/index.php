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
            <h3>Planning Templates</h3>
            <p>Select a template to start organizing your tasks efficiently.</p>

            <!-- Template Grid -->
            <div class="template-grid">
                <div class="template-card">
                    <h4>Kanban Board</h4>
                    <p>Visualize your workflow with a drag-and-drop Kanban board.</p>
                    <a href="kanban.php" class="template-btn">Open Kanban</a>
                </div>

                <div class="template-card">
                    <h4>Daily Planner</h4>
                    <p>Plan your daily tasks and appointments in a structured format.</p>
                    <a href="daily_planner.php" class="template-btn">Open Daily Planner</a>
                </div>

                <div class="template-card">
                    <h4>Weekly Schedule</h4>
                    <p>Organize tasks and deadlines in a week-based schedule.</p>
                    <a href="weekly_schedule.php" class="template-btn">Open Weekly Schedule</a>
                </div>

                <div class="template-card">
                    <h4>Gantt Chart</h4>
                    <p>Plan tasks over a timeline for project tracking.</p>
                    <a href="gantt_chart.php" class="template-btn">Open Gantt Chart</a>
                </div>

                <div class="template-card">
                    <h4>Goal Tracker</h4>
                    <p>Set, track, and achieve your goals with structured tracking.</p>
                    <a href="goal_tracker.php" class="template-btn">Open Goal Tracker</a>
                </div>

                <div class="template-card">
                    <h4>Notes & Ideas</h4>
                    <p>Keep all your notes, ideas, and reminders in one place.</p>
                    <a href="notes.php" class="template-btn">Open Notes</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
