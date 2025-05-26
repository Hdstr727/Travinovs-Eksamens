<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: core/login.php");
    exit();
}

// Include database connection
require_once '../admin/database/connection.php';

// Get user info from database including profile picture
$user_id = $_SESSION['user_id'];
$sql = "SELECT username, profile_picture FROM Planotajs_Users WHERE user_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get board count (both owned and shared)
$board_count_sql = "SELECT
                    (SELECT COUNT(*) FROM Planotajs_Boards WHERE user_id = ? AND is_deleted = 0) +
                    (SELECT COUNT(*) FROM Planotajs_Collaborators WHERE user_id = ? AND board_id IN
                        (SELECT board_id FROM Planotajs_Boards WHERE is_deleted = 0)
                    ) as count";
$board_stmt = $connection->prepare($board_count_sql);
$board_stmt->bind_param("ii", $user_id, $user_id);
$board_stmt->execute();
$board_count_result = $board_stmt->get_result()->fetch_assoc();
$board_count = $board_count_result ? $board_count_result['count'] : 0;
$board_stmt->close();

// --- Get count of tasks created by the logged-in user ---
// This assumes you have a way to identify the creator of a task.
// If Planotajs_Tasks doesn't have a 'created_by_user_id' column, this will be tricky.
// For now, let's assume we count tasks on boards they own or are a collaborator on.
// A more accurate "tasks created by me" would require a 'creator_id' in Planotajs_Tasks.
// This query counts all non-deleted tasks on boards the user has access to.
// If you want tasks *literally created by* this user, you'll need to add a `created_by_user_id`
// to your `Planotajs_Tasks` table and update your `save_task.php` to populate it.

// For "My Tasks Created" - let's count tasks on boards they own or are a collaborator on,
// and for simplicity, assume any task on these boards is "their" task in a broad sense.
// A more precise "created by me" would need a `creator_user_id` in the tasks table.
// This query counts tasks on boards the user owns.
$my_tasks_created_sql = "SELECT COUNT(t.task_id) as count
                         FROM Planotajs_Tasks t
                         JOIN Planotajs_Boards b ON t.board_id = b.board_id
                         WHERE b.user_id = ? AND t.is_deleted = 0";
// If you want to include tasks on boards they collaborate on:
/*
$my_tasks_created_sql = "SELECT COUNT(DISTINCT t.task_id) as count
                         FROM Planotajs_Tasks t
                         WHERE t.is_deleted = 0 AND t.board_id IN (
                             SELECT board_id FROM Planotajs_Boards WHERE user_id = ? AND is_deleted = 0
                             UNION
                             SELECT board_id FROM Planotajs_Collaborators WHERE user_id = ?
                         )";
*/
$my_tasks_stmt = $connection->prepare($my_tasks_created_sql);
// If using the more complex query with UNION, bind twice: $my_tasks_stmt->bind_param("ii", $user_id, $user_id);
$my_tasks_stmt->bind_param("i", $user_id);
$my_tasks_stmt->execute();
$my_tasks_result = $my_tasks_stmt->get_result()->fetch_assoc();
$my_tasks_created_count = $my_tasks_result ? $my_tasks_result['count'] : 0;
$my_tasks_stmt->close();


/// --- Get count AND DETAILS of upcoming deadlines (due in the next 7 days) for this user's tasks ---
$today = date('Y-m-d');
$seven_days_later = date('Y-m-d', strtotime('+7 days'));

// --- Query 1: Get the TOTAL count of upcoming deadlines ---
$total_upcoming_deadlines_count = 0;
$count_deadlines_sql = "SELECT COUNT(DISTINCT t.task_id) as count
                        FROM Planotajs_Tasks t
                        WHERE t.is_deleted = 0 AND t.is_completed = 0
                          AND DATE(t.due_date) BETWEEN ? AND ?
                          AND t.board_id IN (
                              SELECT board_id FROM Planotajs_Boards WHERE user_id = ? AND is_deleted = 0
                              UNION
                              SELECT board_id FROM Planotajs_Collaborators WHERE user_id = ?
                          )";
$count_deadlines_stmt = $connection->prepare($count_deadlines_sql);
if ($count_deadlines_stmt) {
    $count_deadlines_stmt->bind_param("ssii", $today, $seven_days_later, $user_id, $user_id);
    $count_deadlines_stmt->execute();
    $count_deadlines_result = $count_deadlines_stmt->get_result()->fetch_assoc();
    if ($count_deadlines_result) {
        $total_upcoming_deadlines_count = $count_deadlines_result['count'];
    }
    $count_deadlines_stmt->close();
} else {
    error_log("Failed to prepare statement for total upcoming deadlines count: " . $connection->error);
}


// --- Query 2: Get DETAILS for a limited list of upcoming tasks ---
$upcoming_tasks_details = []; // Array to store task details
$details_deadlines_sql = "SELECT t.task_id, t.task_name, t.due_date, t.board_id, b.board_name
                          FROM Planotajs_Tasks t
                          JOIN Planotajs_Boards b ON t.board_id = b.board_id
                          WHERE t.is_deleted = 0 AND t.is_completed = 0
                            AND DATE(t.due_date) BETWEEN ? AND ?
                            AND t.board_id IN (
                                SELECT board_id FROM Planotajs_Boards WHERE user_id = ? AND is_deleted = 0
                                UNION
                                SELECT board_id FROM Planotajs_Collaborators WHERE user_id = ?
                            )
                          ORDER BY t.due_date ASC, CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC
                          LIMIT 5"; // Limit how many tasks you list on the dashboard, e.g., 5 or 10
$details_deadlines_stmt = $connection->prepare($details_deadlines_sql);
if ($details_deadlines_stmt) {
    $details_deadlines_stmt->bind_param("ssii", $today, $seven_days_later, $user_id, $user_id);
    $details_deadlines_stmt->execute();
    $details_deadlines_result = $details_deadlines_stmt->get_result();
    while ($task = $details_deadlines_result->fetch_assoc()) {
        $upcoming_tasks_details[] = $task;
    }
    $details_deadlines_stmt->close();
} else {
    error_log("Failed to prepare statement for upcoming deadline details: " . $connection->error);
}

// The variable for the displayed count should be the total count
$upcoming_deadlines_display_count = $total_upcoming_deadlines_count;



$username = $user['username'] ?? ($_SESSION['username'] ?? 'User');
$db_profile_picture_path = $user['profile_picture'] ?? null;
$full_server_path_to_picture = null;
if ($db_profile_picture_path) {
    $full_server_path_to_picture = __DIR__ . '/core/' . $db_profile_picture_path;
}

if (!empty($db_profile_picture_path) && $full_server_path_to_picture && file_exists($full_server_path_to_picture)) {
    $user_avatar = 'core/' . $db_profile_picture_path;
} else {
    $user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=e63946&color=fff";
}

// Dynamic greeting based on time of day
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

$boards = [];
// Fetch user's own boards from database
$own_boards_sql = "SELECT board_id, board_name, board_type, updated_at, 'owner' as access_type
                  FROM Planotajs_Boards
                  WHERE user_id = ? AND is_deleted = 0";
$own_boards_stmt = $connection->prepare($own_boards_sql);
$own_boards_stmt->bind_param("i", $user_id);
$own_boards_stmt->execute();
$own_boards_result = $own_boards_stmt->get_result();

// Store raw updated_at for sorting, then format
while ($board = $own_boards_result->fetch_assoc()) {
    $page = ($board['board_type'] === 'kanban') ? 'kanban.php' : 'kanban.php';
    $boards[] = [
        'id' => $board['board_id'],
        'name' => $board['board_name'],
        'page' => $page,
        'raw_updated_at' => $board['updated_at'], // Store raw timestamp
        'access_type' => $board['access_type']
    ];
}
$own_boards_stmt->close();

// Fetch shared boards
$shared_boards_sql = "SELECT b.board_id, b.board_name, b.board_type, b.updated_at,
                     c.permission_level as access_type, u.username as owner_name
                     FROM Planotajs_Collaborators c
                     JOIN Planotajs_Boards b ON c.board_id = b.board_id
                     JOIN Planotajs_Users u ON b.user_id = u.user_id
                     WHERE c.user_id = ? AND b.is_deleted = 0";
$shared_boards_stmt = $connection->prepare($shared_boards_sql);
$shared_boards_stmt->bind_param("i", $user_id);
$shared_boards_stmt->execute();
$shared_boards_result = $shared_boards_stmt->get_result();

while ($board = $shared_boards_result->fetch_assoc()) {
    $page = ($board['board_type'] === 'kanban') ? 'kanban.php' : 'kanban.php';
    $boards[] = [
        'id' => $board['board_id'],
        'name' => $board['board_name'],
        'page' => $page,
        'raw_updated_at' => $board['updated_at'], // Store raw timestamp
        'access_type' => $board['access_type'],
        'owner_name' => $board['owner_name']
    ];
}
$shared_boards_stmt->close();

// Sort boards by raw_updated_at in descending order
usort($boards, function($a, $b) {
    return strtotime($b['raw_updated_at']) - strtotime($a['raw_updated_at']);
});

// Now format the 'updated' string after sorting
foreach ($boards as $key => $board) {
    $updated_time = strtotime($board['raw_updated_at']);
    $time_diff = time() - $updated_time;
    $days_ago = floor($time_diff / (60 * 60 * 24));

    if ($days_ago == 0) $last_updated = "Updated today";
    elseif ($days_ago == 1) $last_updated = "Updated yesterday";
    else $last_updated = "Updated $days_ago days ago";
    $boards[$key]['updated'] = $last_updated;
}


// Get unread notifications count
$unread_notifications_count = 0;
$stmt_count = $connection->prepare("SELECT COUNT(*) as count FROM Planotajs_Notifications WHERE user_id = ? AND is_read = 0");
if ($stmt_count) {
    $stmt_count->bind_param("i", $user_id);
    $stmt_count->execute();
    $count_result = $stmt_count->get_result()->fetch_assoc();
    if ($count_result) {
        $unread_notifications_count = $count_result['count'];
    }
    $stmt_count->close();
} else {
    error_log("Failed to prepare statement for unread notifications count: " . $connection->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Planotajs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/dark-theme.css">
    <style>
        .hover-scale { transition: transform 0.2s ease; }
        .hover-scale:hover { transform: scale(1.05); }
        .badge { font-size: 0.65rem; padding: 0.15rem 0.5rem; border-radius: 9999px; }
        .notification-item > a, .notification-item > div[data-id] { cursor: pointer; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-[#e63946]">Planner+</h1>
            <div class="flex items-center space-x-4">

                <!-- Notifications Bell -->
                <div class="relative">
                    <button id="notifications-toggle" class="relative bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition">
                        🔔
                        <?php if ($unread_notifications_count > 0): ?>
                            <span id="notification-count-badge" class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
                                <?php echo $unread_notifications_count; ?>
                            </span>
                        <?php else: ?>
                            <span id="notification-count-badge" class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center" style="display: none;"></span>
                        <?php endif; ?>
                    </button>
                    <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-md p-4 z-50 max-h-96 overflow-y-auto">
                        <h3 class="text-lg font-semibold mb-2">Notifications</h3>
                        <div id="notifications-list">
                            <p class="text-sm text-gray-600">Loading notifications...</p>
                        </div>
                        <div class="mt-2 text-center">
                            <a href="#" id="mark-all-read" class="text-sm text-[#e63946] hover:underline">Mark all as read</a>
                        </div>
                    </div>
                </div>

                <!-- Dark Mode Toggle -->
                <button id="dark-mode-toggle" class="bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition">
                    🌙
                </button>

                <!-- Profile Icon -->
                <div class="relative">
                    <button id="profile-toggle" class="relative">
                        <img src="<?php echo htmlspecialchars($user_avatar); ?>" class="w-10 h-10 rounded-full border hover:opacity-90 transition-opacity" alt="Avatar">
                    </button>
                    <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-md p-4">
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($username); ?></p>
                        <a href="core/profile.php" class="block mt-2 text-[#e63946] hover:underline">View Profile</a>
                    </div>
                </div>
                <!-- Logout Button -->
                <a href="core/logout.php" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Logout</a>
            </div>
        </div>

        <!-- Welcome Message -->
        <div class="mb-8">
            <h2 class="text-2xl font-semibold text-gray-700"><?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars($username); ?>!</h2>
            <p class="text-gray-600">Here's what's happening with your boards today.</p>
        </div>

        <!-- Quick Stats -->
        <div class="mb-8 grid md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md text-center card">
                <p class="text-lg font-semibold text-[#e63946]"><?php echo $board_count; ?></p>
                <p class="text-gray-600">Total Boards</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center card">
                <p class="text-lg font-semibold text-[#e63946]"><?php echo $my_tasks_created_count; ?></p>
                <p class="text-gray-600">My Tasks Created</p> 
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center card">
                <p class="text-lg font-semibold text-[#e63946]"><?php echo $total_upcoming_deadlines_count; ?></p>
                <p class="text-gray-600">Upcoming Deadlines (Next 7 Days)</p>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="mb-8">
            <input type="text" placeholder="Search boards, tasks, or templates..." class="w-full p-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#e63946]">
        </div>

        <!-- Your Boards Section -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Your Boards</h3>
            <button id="add-board-btn" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition mb-4">
                Add Board
            </button>
            <?php if (count($boards) > 0): ?>
                <div class='grid md:grid-cols-3 gap-6'>
                <?php foreach ($boards as $board):
                    $badgeColor = "bg-blue-100 text-blue-800"; // Default for shared
                    $badgeText = "";

                    if ($board['access_type'] === 'owner') {
                        $badgeColor = "bg-green-100 text-green-800";
                        $badgeText = "Owner";
                    } elseif (isset($board['owner_name'])) { // It's a shared board
                        $badgeText = "Shared by " . htmlspecialchars($board['owner_name']);
                        if ($board['access_type'] === 'admin') { // For collaborators
                             $badgeColor = "bg-purple-100 text-purple-800"; // Example for admin collaborator
                             $badgeText = "Admin • " . $badgeText;
                        } elseif ($board['access_type'] === 'edit') { // 'edit' for collaborators
                            $badgeColor = "bg-yellow-100 text-yellow-800";
                            $badgeText = "Editor • " . $badgeText;
                        } elseif ($board['access_type'] === 'view') { // 'view' for collaborators
                            $badgeColor = "bg-gray-100 text-gray-800";
                            $badgeText = "Viewer • " . $badgeText;
                        }
                    } else {
                        // Fallback for shared if owner_name somehow not set, though query should provide it
                        $badgeText = "Shared";
                    }
                ?>
                    <a href='<?php echo htmlspecialchars($board['page']); ?>?board_id=<?php echo $board['id']; ?>' class='bg-white p-6 rounded-lg shadow-md hover-scale card'>
                        <div class='flex justify-between items-start mb-2'>
                            <h4 class='text-lg font-semibold text-[#e63946]'><?php echo htmlspecialchars($board['name']); ?></h4>
                            <span class='badge <?php echo $badgeColor; ?>'><?php echo $badgeText; ?></span>
                        </div>
                        <p class='text-gray-600'><?php echo htmlspecialchars($board['updated']); ?></p>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class='col-span-3 text-center p-8 bg-white rounded-lg shadow-md card'>
                    <p class='text-gray-600'>You haven't created any boards yet. Click 'Add Board' to get started!</p>
                </div>
            <?php endif; ?>

            <!-- Modal for Add Board -->
            <div id="add-board-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg shadow-lg w-96">
                    <h2 class="text-xl font-semibold mb-4">Create New Board</h2>
                    <input type="text" id="board-name-modal" placeholder="Enter board name..." class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#e63946] mb-4">
                    <div class="mt-4">
                        <p class="text-gray-600">Select a template:</p>
                        <select id="board-template-modal" class="w-full p-2 border border-gray-300 rounded-lg mt-2 focus:ring-2 focus:ring-[#e63946]">
                            <option value="kanban">Kanban</option>
                        </select>
                    </div>
                    <div class="flex justify-end mt-6">
                        <button id="close-modal-btn" class="mr-2 text-gray-600 hover:text-gray-800 py-2 px-4 rounded-lg border border-gray-300">Cancel</button>
                        <button id="create-board-btn" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Create</button>
                    </div>
                </div>
            </div>
        </div>

              
<!-- Upcoming Deadlines -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Upcoming Deadlines</h3>
            <div class="bg-white p-6 rounded-lg shadow-md card">
                <div class="space-y-3">
                    <?php if ($upcoming_deadlines_display_count > 0): ?>
                        <p class="text-sm text-gray-700 font-medium">
                            You have <?php echo $upcoming_deadlines_display_count; ?> task(s) due in the next 7 days.
                            <?php if (count($upcoming_tasks_details) < $upcoming_deadlines_display_count && count($upcoming_tasks_details) > 0): ?>
                                <span class="text-xs">(Showing the soonest <?php echo count($upcoming_tasks_details); ?>)</span>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($upcoming_tasks_details)): ?>
                            <ul class="list-disc list-inside space-y-2 text-sm">
                                <?php foreach ($upcoming_tasks_details as $task): ?>
                                    <?php
                                        $due_date_obj = new DateTime($task['due_date']);
                                        $formatted_due_date = $due_date_obj->format('M j');
                                        $days_until_due = (strtotime(date('Y-m-d', strtotime($task['due_date']))) - strtotime(date('Y-m-d'))) / (60 * 60 * 24); // Compare date parts only
                                        $urgency_class = '';
                                        if ($days_until_due < 0) { $urgency_class = 'text-red-600 font-semibold'; }
                                        elseif ($days_until_due < 2) { $urgency_class = 'text-red-500'; }
                                        elseif ($days_until_due < 4) { $urgency_class = 'text-orange-500'; }
                                    ?>
                                    <li class="text-gray-600 hover:text-[#e63946]">
                                        <a href="kanban.php?board_id=<?php echo htmlspecialchars($task['board_id']); ?>#task-<?php echo htmlspecialchars($task['task_id']); ?>"
                                           title="Board: <?php echo htmlspecialchars($task['board_name']); ?>. Due: <?php echo $due_date_obj->format('Y-m-d'); ?>">
                                            <span class="<?php echo $urgency_class; ?>">[<?php echo $formatted_due_date; ?>]</span>
                                            <?php echo htmlspecialchars($task['task_name']); ?>
                                            <span class="text-xs text-gray-400 italic">(<?php echo htmlspecialchars($task['board_name']); ?>)</span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">No upcoming deadlines in the next 7 days. Great job!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (window.location.hash) {
                    const hash = window.location.hash; // e.g., #task-123
                    if (hash.startsWith('#task-')) {
                        const taskId = hash.substring(6); // Get "123"
                        const taskElement = document.getElementById(`task-${taskId}`); // Assumes your task cards have id="task-TASK_ID"

                        if (taskElement) {
                            // Option 1: Scroll to the task
                            taskElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

                            // Option 2: Highlight the task (add a temporary class)
                            taskElement.classList.add('highlighted-task'); // Define .highlighted-task in your CSS
                            setTimeout(() => {
                                taskElement.classList.remove('highlighted-task');
                            }, 3000); // Remove highlight after 3 seconds
                        }
                    }
                }
            });
        </script>

    

        <!-- Recent Activity (Placeholder) -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Recent Activity</h3>
            <div class="bg-white p-6 rounded-lg shadow-md card">
                <div class="space-y-4">
                     <p class="text-sm text-gray-500">No recent activity to display.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-gray-600 mt-8">
            <p>© <?php echo date("Y"); ?> Planotajs. All rights reserved.</p>
            <div class="mt-2">
                <a href="#" class="text-[#e63946] hover:underline">About</a> |
                <a href="#" class="text-[#e63946] hover:underline">Contact</a> |
                <a href="#" class="text-[#e63946] hover:underline">Privacy Policy</a>
            </div>
        </div>
    </div>

    <script>
        // Dark Mode Toggle Script
        document.addEventListener('DOMContentLoaded', function () {
            const darkModeToggle = document.getElementById('dark-mode-toggle');
            const htmlElement = document.documentElement;
            function setDarkMode(isDark) {
                if (isDark) {
                    htmlElement.classList.add('dark-mode');
                    if (darkModeToggle) darkModeToggle.textContent = '☀️';
                } else {
                    htmlElement.classList.remove('dark-mode');
                    if (darkModeToggle) darkModeToggle.textContent = '🌙';
                }
            }
            if (localStorage.getItem('darkMode') === 'true') {
                setDarkMode(true); 
            } else {
                setDarkMode(false); 
            }
            if (darkModeToggle) { 
                darkModeToggle.addEventListener('click', () => {
                    const isCurrentlyDark = htmlElement.classList.contains('dark-mode');
                    setDarkMode(!isCurrentlyDark);
                    localStorage.setItem('darkMode', !isCurrentlyDark);
                });
            }});

        // Profile Dropdown Script
        const profileToggle = document.getElementById('profile-toggle');
        const profileDropdown = document.getElementById('profile-dropdown');
        if (profileToggle && profileDropdown) {
            profileToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
                if (notificationsDropdown) notificationsDropdown.classList.add('hidden');
            });
        }

        // Notifications Dropdown Script
        const notificationsToggle = document.getElementById('notifications-toggle');
        const notificationsDropdown = document.getElementById('notifications-dropdown');
        const notificationsList = document.getElementById('notifications-list');
        const markAllReadBtn = document.getElementById('mark-all-read');

        function fetchNotifications() {
            fetch('ajax_handlers/get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderNotifications(data.notifications);
                        updateUnreadCount(data.unread_count);
                    } else {
                        notificationsList.innerHTML = `<p class="text-sm text-red-500">Error: ${data.error || 'Could not load notifications.'}</p>`;
                    }
                })
                .catch(error => {
                    notificationsList.innerHTML = '<p class="text-sm text-red-500">Error fetching notifications.</p>';
                });
        }

        function renderNotifications(notifications) {
            if (!notifications || notifications.length === 0) {
                notificationsList.innerHTML = '<p class="text-sm text-gray-600">No new notifications.</p>';
                return;
            }
            let html = '';
            notifications.forEach(notif => {
                const isUnreadClass = notif.is_read == 0 ? 'font-semibold bg-sky-50' : 'text-gray-700';
                const messageText = String(notif.message || '').replace(/</g, "<").replace(/>/g, ">");
                const createdAtText = String(notif.formatted_created_at || '').replace(/</g, "<").replace(/>/g, ">");
                const linkHtml = notif.link ?
                    `<a href="${encodeURI(notif.link)}" class="block hover:bg-gray-100 p-2 rounded ${isUnreadClass}" data-id="${notif.notification_id}">` :
                    `<div class="p-2 ${isUnreadClass}" data-id="${notif.notification_id}">`;
                const linkEndHtml = notif.link ? `</a>` : `</div>`;
                html += `<div class="notification-item border-b border-gray-200 last:border-b-0">${linkHtml}<p class="text-sm ">${messageText}</p><p class="text-xs text-gray-500 mt-1">${createdAtText}</p>${linkEndHtml}</div>`;
            });
            notificationsList.innerHTML = html;
            document.querySelectorAll('.notification-item a, .notification-item div[data-id]').forEach(item => {
                item.addEventListener('click', function(e) {
                    const notificationId = this.dataset.id;
                    const isLink = this.tagName === 'A';
                    const isCurrentlyUnread = this.classList.contains('font-semibold');
                    if (isCurrentlyUnread) {
                        markNotificationAsRead(notificationId, !isLink);
                    }
                    if (!isLink) e.stopPropagation();
                });
            });
        }

        function updateUnreadCount(count) {
            let badge = document.getElementById('notification-count-badge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'flex';
                } else {
                    badge.textContent = '';
                    badge.style.display = 'none';
                }
            }
        }

        function markNotificationAsRead(notificationId, refreshList = true) {
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            fetch('ajax_handlers/mark_notification_read.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (refreshList) {
                        fetchNotifications();
                    } else {
                        const itemClicked = notificationsList.querySelector(`.notification-item [data-id="${notificationId}"]`);
                        if (itemClicked) {
                            itemClicked.classList.remove('font-semibold', 'bg-sky-50');
                            itemClicked.classList.add('text-gray-700');
                        }
                        let currentBadge = document.getElementById('notification-count-badge');
                        if (currentBadge) {
                           let currentCount = parseInt(currentBadge.textContent || "0");
                           if (currentCount > 0) updateUnreadCount(currentCount - 1);
                        }
                    }
                }
            });
        }
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                const formData = new FormData();
                formData.append('mark_all', 'true');
                fetch('ajax_handlers/mark_notification_read.php', { method: 'POST', body: formData })
                .then(response => response.json()).then(data => { if (data.success) fetchNotifications(); });
            });
        }
        if (notificationsToggle && notificationsDropdown) {
            notificationsToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const isHidden = notificationsDropdown.classList.toggle('hidden');
                if (profileDropdown) profileDropdown.classList.add('hidden');
                if (!isHidden) fetchNotifications();
            });
        }

        document.addEventListener('click', (e) => {
            if (notificationsDropdown && !notificationsToggle.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                notificationsDropdown.classList.add('hidden');
            }
            if (profileDropdown && !profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });

        const addBoardBtn = document.getElementById('add-board-btn');
        const addBoardModal = document.getElementById('add-board-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const createBoardBtn = document.getElementById('create-board-btn');
        const boardNameModalInput = document.getElementById('board-name-modal');
        const boardTemplateModalSelect = document.getElementById('board-template-modal');

        if (addBoardBtn) addBoardBtn.addEventListener('click', () => addBoardModal.classList.remove('hidden'));
        if (closeModalBtn) closeModalBtn.addEventListener('click', () => addBoardModal.classList.add('hidden'));
        if (addBoardModal) addBoardModal.addEventListener('click', function(e) { if (e.target === this) this.classList.add('hidden'); });
        if (createBoardBtn) {
            createBoardBtn.addEventListener('click', () => {
                const boardName = boardNameModalInput.value.trim();
                const boardTemplate = boardTemplateModalSelect.value;
                if (boardName) {
                    fetch('create_board.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `board_name=${encodeURIComponent(boardName)}&board_template=${encodeURIComponent(boardTemplate)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(`Error creating board: ${data.message || 'Unknown error'}`);
                        }
                    })
                    .catch(error => alert('An error occurred while creating the board.'));
                } else {
                    alert('Please enter a board name.');
                }
            });
        }
    </script>
</body>
</html>