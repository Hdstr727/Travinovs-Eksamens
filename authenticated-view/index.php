<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: core/login.php");
    exit();
}

// Include database connection
require_once '../admin/database/connection.php';

$user_id = $_SESSION['user_id'];

// --- Handle "Show Archived" toggle ---
$show_archived = isset($_GET['show_archived']) && $_GET['show_archived'] == '1';

// --- SQL Condition for archived boards ---
$archived_filter_boards_table = ""; // For queries directly on Planotajs_Boards
$archived_filter_aliased_b_table = ""; // For queries where Planotajs_Boards is aliased as 'b'

if (!$show_archived) {
    $archived_filter_boards_table = " AND is_archived = 0";
    $archived_filter_aliased_b_table = " AND b.is_archived = 0";
}

// Get user info
$sql_user = "SELECT username, profile_picture FROM Planotajs_Users WHERE user_id = ?";
$stmt_user = $connection->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();

// Get board count (respecting archive filter)
$board_count_sql = "SELECT COUNT(DISTINCT b.board_id) as count
                    FROM Planotajs_Boards b
                    LEFT JOIN Planotajs_Collaborators c ON b.board_id = c.board_id AND c.user_id = ?
                    WHERE b.is_deleted = 0 " . $archived_filter_aliased_b_table . "
                    AND (b.user_id = ? OR c.user_id = ?)";
$board_stmt = $connection->prepare($board_count_sql);
$board_stmt->bind_param("iii", $user_id, $user_id, $user_id);
$board_stmt->execute();
$board_count_result = $board_stmt->get_result()->fetch_assoc();
$board_count = $board_count_result ? $board_count_result['count'] : 0;
$board_stmt->close();

// Get count of tasks created by the logged-in user (on non-deleted, and conditionally non-archived boards they own)
$my_tasks_created_sql = "SELECT COUNT(t.task_id) as count
                         FROM Planotajs_Tasks t
                         JOIN Planotajs_Boards b ON t.board_id = b.board_id
                         WHERE b.user_id = ? AND t.is_deleted = 0 AND b.is_deleted = 0" . $archived_filter_aliased_b_table;
$my_tasks_stmt = $connection->prepare($my_tasks_created_sql);
$my_tasks_stmt->bind_param("i", $user_id);
$my_tasks_stmt->execute();
$my_tasks_result = $my_tasks_stmt->get_result()->fetch_assoc();
$my_tasks_created_count = $my_tasks_result ? $my_tasks_result['count'] : 0;
$my_tasks_stmt->close();

// --- Upcoming Deadlines (respecting archive filter) ---
$today = date('Y-m-d');
$seven_days_later = date('Y-m-d', strtotime('+7 days'));

// Subquery for boards user has access to (respecting archive filter)
$board_access_subquery_archived_filter_direct = "";
$board_access_subquery_archived_filter_collab_alias = "";
if (!$show_archived) {
    $board_access_subquery_archived_filter_direct = " AND is_archived = 0";
    $board_access_subquery_archived_filter_collab_alias = " AND b_collab.is_archived = 0";
}

$board_access_subquery = "
    SELECT board_id FROM Planotajs_Boards WHERE user_id = ? AND is_deleted = 0 {$board_access_subquery_archived_filter_direct}
    UNION
    SELECT c.board_id FROM Planotajs_Collaborators c JOIN Planotajs_Boards b_collab ON c.board_id = b_collab.board_id WHERE c.user_id = ? AND b_collab.is_deleted = 0 {$board_access_subquery_archived_filter_collab_alias}
";

$total_upcoming_deadlines_count = 0;
$count_deadlines_sql = "SELECT COUNT(DISTINCT t.task_id) as count
                        FROM Planotajs_Tasks t
                        JOIN Planotajs_Boards b_main ON t.board_id = b_main.board_id
                        WHERE t.is_deleted = 0 AND t.is_completed = 0 AND b_main.is_deleted = 0 " . ($show_archived ? "" : "AND b_main.is_archived = 0") . "
                          AND DATE(t.due_date) BETWEEN ? AND ?
                          AND t.board_id IN ({$board_access_subquery})";
$count_deadlines_stmt = $connection->prepare($count_deadlines_sql);
if ($count_deadlines_stmt) {
    $count_deadlines_stmt->bind_param("ssii", $today, $seven_days_later, $user_id, $user_id); // Params for subquery
    $count_deadlines_stmt->execute();
    $count_deadlines_result = $count_deadlines_stmt->get_result()->fetch_assoc();
    if ($count_deadlines_result) {
        $total_upcoming_deadlines_count = $count_deadlines_result['count'];
    }
    $count_deadlines_stmt->close();
} else {
    error_log("Failed to prepare statement for total upcoming deadlines count: " . $connection->error);
}

$upcoming_tasks_details = [];
$details_deadlines_sql = "SELECT t.task_id, t.task_name, t.due_date, t.board_id, b.board_name, b.is_archived as board_is_archived
                          FROM Planotajs_Tasks t
                          JOIN Planotajs_Boards b ON t.board_id = b.board_id
                          WHERE t.is_deleted = 0 AND t.is_completed = 0 AND b.is_deleted = 0 " . ($show_archived ? "" : "AND b.is_archived = 0") . "
                            AND DATE(t.due_date) BETWEEN ? AND ?
                            AND t.board_id IN ({$board_access_subquery})
                          ORDER BY t.due_date ASC, CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC
                          LIMIT 5";
$details_deadlines_stmt = $connection->prepare($details_deadlines_sql);
if ($details_deadlines_stmt) {
    $details_deadlines_stmt->bind_param("ssii", $today, $seven_days_later, $user_id, $user_id); // Params for subquery
    $details_deadlines_stmt->execute();
    $details_deadlines_result = $details_deadlines_stmt->get_result();
    while ($task = $details_deadlines_result->fetch_assoc()) {
        $upcoming_tasks_details[] = $task;
    }
    $details_deadlines_stmt->close();
} else {
    error_log("Failed to prepare statement for upcoming deadline details: " . $connection->error);
}
$upcoming_deadlines_display_count = $total_upcoming_deadlines_count;


$username = $user['username'] ?? ($_SESSION['username'] ?? 'User');
// ... (rest of your avatar and greeting logic remains the same) ...
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

$hour = date('H');
if ($hour < 12) { $greeting = "Good Morning"; }
elseif ($hour < 18) { $greeting = "Good Afternoon"; }
else { $greeting = "Good Evening"; }


$boards = [];
// Fetch user's own boards (respecting archive filter)
$own_boards_sql = "SELECT board_id, board_name, board_type, updated_at, is_archived, 'owner' as access_type
                  FROM Planotajs_Boards
                  WHERE user_id = ? AND is_deleted = 0" . $archived_filter_boards_table;
$own_boards_stmt = $connection->prepare($own_boards_sql);
$own_boards_stmt->bind_param("i", $user_id);
$own_boards_stmt->execute();
$own_boards_result = $own_boards_stmt->get_result();
while ($board = $own_boards_result->fetch_assoc()) {
    $page = ($board['board_type'] === 'kanban') ? 'kanban.php' : 'kanban.php'; // Or other types
    $boards[] = [
        'id' => $board['board_id'],
        'name' => $board['board_name'],
        'page' => $page,
        'raw_updated_at' => $board['updated_at'],
        'access_type' => $board['access_type'],
        'is_archived' => $board['is_archived']
    ];
}
$own_boards_stmt->close();

// Fetch shared boards (respecting archive filter)
$shared_boards_sql = "SELECT b.board_id, b.board_name, b.board_type, b.updated_at, b.is_archived,
                     c.permission_level as access_type, u.username as owner_name
                     FROM Planotajs_Collaborators c
                     JOIN Planotajs_Boards b ON c.board_id = b.board_id
                     JOIN Planotajs_Users u ON b.user_id = u.user_id
                     WHERE c.user_id = ? AND b.is_deleted = 0" . $archived_filter_aliased_b_table;
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
        'raw_updated_at' => $board['updated_at'],
        'access_type' => $board['access_type'],
        'owner_name' => $board['owner_name'],
        'is_archived' => $board['is_archived']
    ];
}
$shared_boards_stmt->close();

// Sort boards by raw_updated_at
usort($boards, function($a, $b) {
    return strtotime($b['raw_updated_at']) - strtotime($a['raw_updated_at']);
});

// Format 'updated' string
foreach ($boards as $key => $board) {
    // ... (your existing updated time formatting logic) ...
    $updated_time = strtotime($board['raw_updated_at']);
    $time_diff = time() - $updated_time;
    $days_ago = floor($time_diff / (60 * 60 * 24));

    if ($days_ago == 0) $last_updated = "Updated today";
    elseif ($days_ago == 1) $last_updated = "Updated yesterday";
    else $last_updated = "Updated $days_ago days ago";
    $boards[$key]['updated'] = $last_updated;
}

// Get unread notifications count
// ... (your existing notifications count logic) ...
$unread_notifications_count = 0;
$stmt_count_notif = $connection->prepare("SELECT COUNT(*) as count FROM Planotajs_Notifications WHERE user_id = ? AND is_read = 0");
if ($stmt_count_notif) {
    $stmt_count_notif->bind_param("i", $user_id);
    $stmt_count_notif->execute();
    $count_result_notif = $stmt_count_notif->get_result()->fetch_assoc();
    if ($count_result_notif) {
        $unread_notifications_count = $count_result_notif['count'];
    }
    $stmt_count_notif->close();
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
        .hover-scale:hover { transform: scale(1.03); } /* Slightly less aggressive scale */
        .badge { font-size: 0.65rem; padding: 0.15rem 0.5rem; border-radius: 9999px; }
        .notification-item > a, .notification-item > div[data-id] { cursor: pointer; }
        .archived-board {
            opacity: 0.7;
            border-left: 4px solid #a0aec0; /* gray-500 */
        }
        .archived-board:hover {
            opacity: 0.9;
        }
        .highlighted-task {
            background-color: #fefcbf; /* yellow-100 */
            border: 1px solid #fbd38d; /* yellow-300 */
            border-radius: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <!-- ... (Your existing header HTML) ... -->
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
        <!-- ... (Your existing welcome message HTML) ... -->
         <div class="mb-8">
            <h2 class="text-2xl font-semibold text-gray-700"><?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars($username); ?>!</h2>
            <p class="text-gray-600">Here's what's happening with your boards today.</p>
        </div>


        <!-- Quick Stats -->
        <!-- ... (Your existing quick stats HTML - these now respect the archive filter) ... -->
        <div class="mb-8 grid md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md text-center card">
                <p class="text-lg font-semibold text-[#e63946]"><?php echo $board_count; ?></p>
                <p class="text-gray-600">Total Boards <?php echo $show_archived ? "(incl. archived)" : ""; ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center card">
                <p class="text-lg font-semibold text-[#e63946]"><?php echo $my_tasks_created_count; ?></p>
                <p class="text-gray-600">My Tasks Created <?php echo $show_archived ? "(on all boards)" : "(on active boards)"; ?></p> 
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center card">
                <p class="text-lg font-semibold text-[#e63946]"><?php echo $total_upcoming_deadlines_count; ?></p>
                <p class="text-gray-600">Upcoming Deadlines <?php echo $show_archived ? "(all boards)" : "(active boards)"; ?></p>
            </div>
        </div>


        <!-- Search Bar -->
        <!-- ... (Your existing search bar HTML) ... -->
        <div class="mb-8">
            <input type="text" placeholder="Search boards, tasks, or templates..." class="w-full p-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#e63946]">
        </div>


        <!-- Your Boards Section -->
        <div class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-700">Your Boards</h3>
                <div class="flex items-center space-x-4">
                    <button id="add-board-btn" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">
                        Add Board
                    </button>
                    <div>
                        <input type="checkbox" id="show-archived-toggle" class="form-checkbox h-5 w-5 text-[#e63946] rounded focus:ring-[#e63946]" <?php echo $show_archived ? 'checked' : ''; ?>>
                        <label for="show-archived-toggle" class="ml-2 text-sm text-gray-600">Show Archived</label>
                    </div>
                </div>
            </div>
            
            <?php if (count($boards) > 0): ?>
                <div class='grid md:grid-cols-3 gap-6'>
                <?php foreach ($boards as $board):
                    $badgeColor = "bg-blue-100 text-blue-800";
                    $badgeText = "";
                    $is_archived_board = $board['is_archived'] ?? 0;

                    if ($board['access_type'] === 'owner') {
                        $badgeColor = "bg-green-100 text-green-800";
                        $badgeText = "Owner";
                    } elseif (isset($board['owner_name'])) {
                        $badgeText = "Shared by " . htmlspecialchars($board['owner_name']);
                        if ($board['access_type'] === 'admin') {
                             $badgeColor = "bg-purple-100 text-purple-800";
                             $badgeText = "Admin • " . $badgeText;
                        } elseif ($board['access_type'] === 'edit') {
                            $badgeColor = "bg-yellow-100 text-yellow-800";
                            $badgeText = "Editor • " . $badgeText;
                        } elseif ($board['access_type'] === 'view') {
                            $badgeColor = "bg-gray-200 text-gray-700"; // Darker gray for better contrast
                            $badgeText = "Viewer • " . $badgeText;
                        }
                    } else {
                        $badgeText = "Shared";
                    }
                ?>
                    <a href='<?php echo htmlspecialchars($board['page']); ?>?board_id=<?php echo $board['id']; ?>' 
                       class='bg-white p-6 rounded-lg shadow-md hover-scale card <?php echo $is_archived_board ? "archived-board" : ""; ?>'>
                        <div class='flex justify-between items-start mb-2'>
                            <h4 class='text-lg font-semibold text-[#e63946]'>
                                <?php echo htmlspecialchars($board['name']); ?>
                                <?php if ($is_archived_board): ?>
                                    <span class="text-xs text-gray-500 font-normal">(Archived)</span>
                                <?php endif; ?>
                            </h4>
                            <span class='badge <?php echo $badgeColor; ?>'><?php echo $badgeText; ?></span>
                        </div>
                        <p class='text-gray-600'><?php echo htmlspecialchars($board['updated']); ?></p>
                    </a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class='col-span-3 text-center p-8 bg-white rounded-lg shadow-md card'>
                    <p class='text-gray-600'>
                        <?php if ($show_archived): ?>
                            No boards found (including archived).
                        <?php else: ?>
                            You haven't created any active boards yet. Click 'Add Board' or check 'Show Archived'.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Modal for Add Board -->
            <!-- ... (Your existing modal HTML) ... -->
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
                            You have <?php echo $upcoming_deadlines_display_count; ?> task(s) due in the next 7 days
                            <?php if (!$show_archived) echo " on active boards"; ?>.
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
                                        $days_until_due = (strtotime(date('Y-m-d', strtotime($task['due_date']))) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
                                        $urgency_class = '';
                                        if ($days_until_due < 0) { $urgency_class = 'text-red-600 font-semibold'; }
                                        elseif ($days_until_due < 2) { $urgency_class = 'text-red-500'; }
                                        elseif ($days_until_due < 4) { $urgency_class = 'text-orange-500'; }
                                        $task_board_is_archived = $task['board_is_archived'] ?? 0;
                                    ?>
                                    <li class="text-gray-600 hover:text-[#e63946] <?php echo $task_board_is_archived ? 'opacity-75' : ''; ?>">
                                        <a href="kanban.php?board_id=<?php echo htmlspecialchars($task['board_id']); ?>#task-<?php echo htmlspecialchars($task['task_id']); ?>"
                                           title="Board: <?php echo htmlspecialchars($task['board_name']); ?>. Due: <?php echo $due_date_obj->format('Y-m-d'); ?>">
                                            <span class="<?php echo $urgency_class; ?>">[<?php echo $formatted_due_date; ?>]</span>
                                            <?php echo htmlspecialchars($task['task_name']); ?>
                                            <span class="text-xs text-gray-400 italic">(<?php echo htmlspecialchars($task['board_name']); ?>)</span>
                                            <?php if ($task_board_is_archived): ?>
                                                <span class="text-xs text-gray-400 italic">(Archived Board)</span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">No upcoming deadlines in the next 7 days<?php if (!$show_archived) echo " on active boards"; ?>. Great job!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity (Placeholder) -->
        <!-- ... (Your existing recent activity HTML) ... -->
         <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Recent Activity</h3>
            <div class="bg-white p-6 rounded-lg shadow-md card">
                <div class="space-y-4">
                     <p class="text-sm text-gray-500">No recent activity to display.</p>
                </div>
            </div>
        </div>


        <!-- Footer -->
        <!-- ... (Your existing footer HTML) ... -->
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
        document.addEventListener('DOMContentLoaded', function() {
            // --- Highlight task from URL hash ---
            if (window.location.hash && window.location.hash.startsWith('#task-')) {
                const taskId = window.location.hash.substring(6);
                // Attempt to find the task link in upcoming deadlines or other potential task lists
                const taskLink = document.querySelector(`a[href*="#task-${taskId}"]`);
                if (taskLink) {
                    const taskElement = taskLink.closest('li') || taskLink; // Get parent li or the link itself
                    taskElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    taskElement.classList.add('highlighted-task');
                    setTimeout(() => {
                        taskElement.classList.remove('highlighted-task');
                    }, 3000);
                }
            }

            // --- Dark Mode Toggle Script ---
            // ... (Your existing dark mode script) ...
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
            }

            // --- Profile Dropdown Script ---
            // ... (Your existing profile dropdown script) ...
            const profileToggle = document.getElementById('profile-toggle');
            const profileDropdown = document.getElementById('profile-dropdown');
            if (profileToggle && profileDropdown) {
                profileToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('hidden');
                    if (notificationsDropdown) notificationsDropdown.classList.add('hidden');
                });
            }


            // --- Notifications Dropdown Script ---
            // ... (Your existing notifications script) ...
            const notificationsToggle = document.getElementById('notifications-toggle');
            const notificationsDropdown = document.getElementById('notifications-dropdown');
            const notificationsList = document.getElementById('notifications-list');
            const markAllReadBtn = document.getElementById('mark-all-read');

            function fetchNotifications() { /* ... same ... */ 
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
            function renderNotifications(notifications) { /* ... same ... */ 
                if (!notifications || notifications.length === 0) {
                    notificationsList.innerHTML = '<p class="text-sm text-gray-600">No new notifications.</p>';
                    return;
                }
                let html = '';
                notifications.forEach(notif => {
                    const isUnreadClass = notif.is_read == 0 ? 'font-semibold bg-sky-50' : 'text-gray-700';
                    const messageText = String(notif.message || '').replace(/</g, "<").replace(/>/g, ">"); // Sanitize
                    const createdAtText = String(notif.formatted_created_at || '').replace(/</g, "<").replace(/>/g, ">"); // Sanitize
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
                        if (!isLink) e.stopPropagation(); // Prevent dropdown close if not a link
                    });
                });
            }
            function updateUnreadCount(count) { /* ... same ... */ 
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
            function markNotificationAsRead(notificationId, refreshList = true) { /* ... same ... */ 
                const formData = new FormData();
                formData.append('notification_id', notificationId);
                fetch('ajax_handlers/mark_notification_read.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (refreshList) {
                            fetchNotifications();
                        } else {
                            // Update UI for the single item without full refresh
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
            if (markAllReadBtn) { /* ... same ... */ 
                markAllReadBtn.addEventListener('click', function(e) {
                    e.preventDefault(); e.stopPropagation();
                    const formData = new FormData();
                    formData.append('mark_all', 'true');
                    fetch('ajax_handlers/mark_notification_read.php', { method: 'POST', body: formData })
                    .then(response => response.json()).then(data => { if (data.success) fetchNotifications(); });
                });
            }
            if (notificationsToggle && notificationsDropdown) { /* ... same ... */ 
                notificationsToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isHidden = notificationsDropdown.classList.toggle('hidden');
                    if (profileDropdown) profileDropdown.classList.add('hidden');
                    if (!isHidden) fetchNotifications(); // Fetch only when opening
                });
            }


            // --- Close dropdowns on outside click ---
            document.addEventListener('click', (e) => {
                if (notificationsDropdown && !notificationsToggle.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                    notificationsDropdown.classList.add('hidden');
                }
                if (profileDropdown && !profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.add('hidden');
                }
            });

            // --- Add Board Modal Script ---
            // ... (Your existing add board modal script) ...
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
                                // Redirect to the new board or reload dashboard
                                // For simplicity, reload. For better UX, redirect to kanban.php?board_id=NEW_ID
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


            // --- Show Archived Toggle Script ---
            const showArchivedToggle = document.getElementById('show-archived-toggle');
            if (showArchivedToggle) {
                showArchivedToggle.addEventListener('change', function() {
                    const currentUrl = new URL(window.location.href);
                    if (this.checked) {
                        currentUrl.searchParams.set('show_archived', '1');
                    } else {
                        currentUrl.searchParams.delete('show_archived');
                    }
                    window.location.href = currentUrl.toString();
                });
            }
        });
    </script>
</body>
</html>