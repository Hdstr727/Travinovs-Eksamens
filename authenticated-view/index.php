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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col items-center p-6">
    <div class="w-full max-w-4xl bg-white p-6 rounded-lg shadow-lg">
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <h2 class="text-2xl font-bold text-[#e63946]">Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
            <a href="logout.php" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Logout</a>
        </div>

        <h3 class="text-xl font-semibold text-gray-700 mb-2">Planning Templates</h3>
        <p class="text-gray-600 mb-6">Select a template to start organizing your tasks efficiently.</p>

        <!-- Template Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php 
            $templates = [
                ["Kanban Board", "Visualize your workflow with a drag-and-drop Kanban board.", "kanban.php"],
                ["Daily Planner", "Plan your daily tasks and appointments in a structured format.", "daily_planner.php"],
                ["Weekly Schedule", "Organize tasks and deadlines in a week-based schedule.", "weekly_schedule.php"],
                ["Gantt Chart", "Plan tasks over a timeline for project tracking.", "gantt_chart.php"],
                ["Goal Tracker", "Set, track, and achieve your goals with structured tracking.", "goal_tracker.php"],
                ["Notes & Ideas", "Keep all your notes, ideas, and reminders in one place.", "notes.php"]
            ];
            foreach ($templates as $template) {
                echo "<div class='bg-gray-50 p-6 rounded-lg shadow-md text-center hover:shadow-lg transition'>
                        <h4 class='text-lg font-semibold text-[#e63946] mb-2'>{$template[0]}</h4>
                        <p class='text-gray-600 mb-4'>{$template[1]}</p>
                        <a href='{$template[2]}' class='bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition'>Open</a>
                      </div>";
            }
            ?>
        </div>
    </div>
</body>
</html>
