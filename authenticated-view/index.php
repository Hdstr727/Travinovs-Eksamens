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
$archived_filter_boards_table = ""; 
$archived_filter_aliased_b_table = ""; 

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

// Get count of tasks created by the logged-in user
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

// --- Upcoming Deadlines ---
$today = date('Y-m-d');
$seven_days_later = date('Y-m-d', strtotime('+7 days'));
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
$count_deadlines_sql = "SELECT COUNT(DISTINCT t.task_id) as count FROM Planotajs_Tasks t JOIN Planotajs_Boards b_main ON t.board_id = b_main.board_id WHERE t.is_deleted = 0 AND t.is_completed = 0 AND b_main.is_deleted = 0 " . ($show_archived ? "" : "AND b_main.is_archived = 0") . " AND DATE(t.due_date) BETWEEN ? AND ? AND t.board_id IN ({$board_access_subquery})";
$count_deadlines_stmt = $connection->prepare($count_deadlines_sql);
if ($count_deadlines_stmt) {
    $count_deadlines_stmt->bind_param("ssii", $today, $seven_days_later, $user_id, $user_id); $count_deadlines_stmt->execute();
    $count_deadlines_result = $count_deadlines_stmt->get_result()->fetch_assoc();
    if ($count_deadlines_result) { $total_upcoming_deadlines_count = $count_deadlines_result['count']; }
    $count_deadlines_stmt->close();
} else { error_log("Failed to prepare statement for total upcoming deadlines count: " . $connection->error); }

$upcoming_tasks_details = [];
$details_deadlines_sql = "SELECT t.task_id, t.task_name, t.due_date, t.board_id, b.board_name, b.is_archived as board_is_archived FROM Planotajs_Tasks t JOIN Planotajs_Boards b ON t.board_id = b.board_id WHERE t.is_deleted = 0 AND t.is_completed = 0 AND b.is_deleted = 0 " . ($show_archived ? "" : "AND b.is_archived = 0") . " AND DATE(t.due_date) BETWEEN ? AND ? AND t.board_id IN ({$board_access_subquery}) ORDER BY t.due_date ASC, CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC LIMIT 5";
$details_deadlines_stmt = $connection->prepare($details_deadlines_sql);
if ($details_deadlines_stmt) {
    $details_deadlines_stmt->bind_param("ssii", $today, $seven_days_later, $user_id, $user_id); $details_deadlines_stmt->execute();
    $details_deadlines_result = $details_deadlines_stmt->get_result();
    while ($task = $details_deadlines_result->fetch_assoc()) { $upcoming_tasks_details[] = $task; }
    $details_deadlines_stmt->close();
} else { error_log("Failed to prepare statement for upcoming deadline details: " . $connection->error); }
$upcoming_deadlines_display_count = $total_upcoming_deadlines_count;

$username = $user['username'] ?? ($_SESSION['username'] ?? 'User');
$db_profile_picture_path = $user['profile_picture'] ?? null;
$full_server_path_to_picture = null;
if ($db_profile_picture_path) { $full_server_path_to_picture = __DIR__ . '/core/' . $db_profile_picture_path; }
if (!empty($db_profile_picture_path) && $full_server_path_to_picture && file_exists($full_server_path_to_picture)) {
    $user_avatar = 'core/' . $db_profile_picture_path;
} else { $user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=e63946&color=fff"; }
$hour = date('H');
if ($hour < 12) { $greeting = "Good Morning"; } elseif ($hour < 18) { $greeting = "Good Afternoon"; } else { $greeting = "Good Evening"; }

$boards_data_for_php = [];
$own_boards_sql = "SELECT board_id, board_name, board_type, updated_at, is_archived, 'owner' as access_type FROM Planotajs_Boards WHERE user_id = ? AND is_deleted = 0" . $archived_filter_boards_table;
$own_boards_stmt = $connection->prepare($own_boards_sql); $own_boards_stmt->bind_param("i", $user_id); $own_boards_stmt->execute();
$own_boards_result = $own_boards_stmt->get_result();
while ($board = $own_boards_result->fetch_assoc()) {
    $page = ($board['board_type'] === 'kanban') ? 'kanban.php' : 'kanban.php';
    $boards_data_for_php[] = ['id' => $board['board_id'], 'name' => $board['board_name'], 'page' => $page, 'raw_updated_at' => $board['updated_at'], 'access_type' => $board['access_type'], 'is_archived' => $board['is_archived']];
} $own_boards_stmt->close();

$shared_boards_sql = "SELECT b.board_id, b.board_name, b.board_type, b.updated_at, b.is_archived, c.permission_level as access_type, u.username as owner_name FROM Planotajs_Collaborators c JOIN Planotajs_Boards b ON c.board_id = b.board_id JOIN Planotajs_Users u ON b.user_id = u.user_id WHERE c.user_id = ? AND b.is_deleted = 0" . $archived_filter_aliased_b_table;
$shared_boards_stmt = $connection->prepare($shared_boards_sql); $shared_boards_stmt->bind_param("i", $user_id); $shared_boards_stmt->execute();
$shared_boards_result = $shared_boards_stmt->get_result();
while ($board = $shared_boards_result->fetch_assoc()) {
    $page = ($board['board_type'] === 'kanban') ? 'kanban.php' : 'kanban.php';
    $boards_data_for_php[] = ['id' => $board['board_id'], 'name' => $board['board_name'], 'page' => $page, 'raw_updated_at' => $board['updated_at'], 'access_type' => $board['access_type'], 'owner_name' => $board['owner_name'], 'is_archived' => $board['is_archived']];
} $shared_boards_stmt->close();

usort($boards_data_for_php, function($a, $b) { return strtotime($b['raw_updated_at']) - strtotime($a['raw_updated_at']); });
foreach ($boards_data_for_php as $key => $board) {
    $updated_time = strtotime($board['raw_updated_at']); $time_diff = time() - $updated_time; $days_ago = floor($time_diff / (60 * 60 * 24));
    if ($days_ago == 0) $last_updated = "Updated today"; elseif ($days_ago == 1) $last_updated = "Updated yesterday"; else $last_updated = "Updated $days_ago days ago";
    $boards_data_for_php[$key]['updated'] = $last_updated;
}

$unread_notifications_count = 0;
$stmt_count_notif = $connection->prepare("SELECT COUNT(*) as count FROM Planotajs_Notifications WHERE user_id = ? AND is_read = 0");
if ($stmt_count_notif) {
    $stmt_count_notif->bind_param("i", $user_id); $stmt_count_notif->execute();
    $count_result_notif = $stmt_count_notif->get_result()->fetch_assoc();
    if ($count_result_notif) { $unread_notifications_count = $count_result_notif['count']; }
    $stmt_count_notif->close();
} else { error_log("Failed to prepare statement for unread notifications count: " . $connection->error);}

// --- Fetch Recent Activities for Dashboard ---
$recent_activities = [];
$limit_recent_activities = 10; // Changed to 10 as requested

$accessible_board_ids = [];
$stmt_accessible_boards = $connection->prepare($board_access_subquery);
if ($stmt_accessible_boards) {
    $stmt_accessible_boards->bind_param("ii", $user_id, $user_id);
    $stmt_accessible_boards->execute();
    $result_accessible_boards = $stmt_accessible_boards->get_result();
    while ($row_board_id = $result_accessible_boards->fetch_assoc()) {
        $accessible_board_ids[] = $row_board_id['board_id'];
    }
    $stmt_accessible_boards->close();
} else {
    error_log("Dashboard: Failed to prepare statement for accessible board IDs in recent activity: " . $connection->error);
}

if (!empty($accessible_board_ids)) {
    // Ensure Planotajs_ActivityLog table exists
    $sql_create_activity_table_dashboard = "CREATE TABLE IF NOT EXISTS Planotajs_ActivityLog (activity_id INT AUTO_INCREMENT PRIMARY KEY, board_id INT NOT NULL, user_id INT NOT NULL, activity_type VARCHAR(50) NOT NULL, activity_description TEXT NOT NULL, related_entity_id INT NULL, related_entity_type VARCHAR(50) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (board_id) REFERENCES Planotajs_Boards(board_id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES Planotajs_Users(user_id) ON DELETE CASCADE)";
    $connection->query($sql_create_activity_table_dashboard);

    $board_ids_placeholders_recent = implode(',', array_fill(0, count($accessible_board_ids), '?'));
    $types_recent = str_repeat('i', count($accessible_board_ids));
    $params_recent = $accessible_board_ids;

    $sql_recent_activities = "SELECT al.*, u.username as actor_username, b.board_name 
                              FROM Planotajs_ActivityLog al
                              JOIN Planotajs_Users u ON al.user_id = u.user_id
                              JOIN Planotajs_Boards b ON al.board_id = b.board_id
                              WHERE al.board_id IN ({$board_ids_placeholders_recent})
                              ORDER BY al.created_at DESC
                              LIMIT ?";
    
    $types_recent .= 'i'; 
    $params_recent[] = $limit_recent_activities;

    $stmt_recent_activities = $connection->prepare($sql_recent_activities);
    if ($stmt_recent_activities) {
        $stmt_recent_activities->bind_param($types_recent, ...$params_recent);
        $stmt_recent_activities->execute();
        $result_recent_activities = $stmt_recent_activities->get_result();
        while ($activity_item_recent = $result_recent_activities->fetch_assoc()) {
            $recent_activities[] = $activity_item_recent;
        }
        $stmt_recent_activities->close();
    } else {
        error_log("Dashboard: Failed to prepare statement for recent activities: " . $connection->error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Planotajs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/dark-theme.css"> 
    <style>
        .hover-scale { transition: transform 0.2s ease; }
        .hover-scale:hover { transform: scale(1.03); }
        .badge { font-size: 0.65rem; padding: 0.15rem 0.5rem; border-radius: 9999px; }
        .notification-item > a, .notification-item > div[data-id] { cursor: pointer; }
        .archived-board { opacity: 0.7; border-left: 4px solid #a0aec0; }
        .archived-board:hover { opacity: 0.9; }
        .highlighted-task { background-color: #fefcbf; border: 1px solid #fbd38d; border-radius: 0.25rem; }
        .board-card-item.hidden-by-search { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-6">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-[#e63946]">Planner+</h1>
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <button id="notifications-toggle" class="relative bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition">
                        🔔
                        <span id="notification-count-badge" class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center" style="<?= $unread_notifications_count > 0 ? '' : 'display: none;' ?>"><?= $unread_notifications_count > 0 ? $unread_notifications_count : '' ?></span>
                    </button>
                    <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-md p-4 z-50 max-h-96 overflow-y-auto"><h3 class="text-lg font-semibold mb-2">Notifications</h3><div id="notifications-list"><p class="text-sm text-gray-600">Loading notifications...</p></div><div class="mt-2 text-center"><a href="#" id="mark-all-read" class="text-sm text-[#e63946] hover:underline">Mark all as read</a></div></div>
                </div>
                <button id="dark-mode-toggle" class="bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition">🌙</button>
                <div class="relative">
                    <button id="profile-toggle" class="relative"><img src="<?php echo htmlspecialchars($user_avatar); ?>" class="w-10 h-10 rounded-full border hover:opacity-90 transition-opacity" alt="Avatar"></button>
                    <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-md p-4"><p class="text-sm text-gray-600"><?php echo htmlspecialchars($username); ?></p><a href="core/profile.php" class="block mt-2 text-[#e63946] hover:underline">View Profile</a></div>
                </div>
                <a href="core/logout.php" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Logout</a>
            </div>
        </div>

         <div class="mb-8">
            <h2 class="text-2xl font-semibold text-gray-700"><?php echo htmlspecialchars($greeting); ?>, <?php echo htmlspecialchars($username); ?>!</h2>
            <p class="text-gray-600">Here's what's happening with your boards today.</p>
        </div>

        <div class="mb-8 grid md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md text-center card"><p class="text-lg font-semibold text-[#e63946]"><?php echo $board_count; ?></p><p class="text-gray-600">Total Boards <?php echo $show_archived ? "(incl. archived)" : ""; ?></p></div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center card"><p class="text-lg font-semibold text-[#e63946]"><?php echo $my_tasks_created_count; ?></p><p class="text-gray-600">My Tasks Created <?php echo $show_archived ? "(on all boards)" : "(on active boards)"; ?></p></div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center card"><p class="text-lg font-semibold text-[#e63946]"><?php echo $total_upcoming_deadlines_count; ?></p><p class="text-gray-600">Upcoming Deadlines <?php echo $show_archived ? "(all boards)" : "(active boards)"; ?></p></div>
        </div>

        <div class="mb-8">
            <input type="text" id="dashboardSearchInput" placeholder="Search boards..." class="w-full p-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#e63946]">
        </div>

        <div class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-700">Your Boards</h3>
                <div class="flex items-center space-x-4">
                    <button id="add-board-btn" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Add Board</button>
                    <div><input type="checkbox" id="show-archived-toggle" class="form-checkbox h-5 w-5 text-[#e63946] rounded focus:ring-[#e63946]" <?php echo $show_archived ? 'checked' : ''; ?>><label for="show-archived-toggle" class="ml-2 text-sm text-gray-600">Show Archived</label></div>
                </div>
            </div>
            
            <div id="boardsGridContainer" class='grid md:grid-cols-3 gap-6'>
                <?php if (count($boards_data_for_php) > 0): ?>
                    <?php foreach ($boards_data_for_php as $board_item_php):
                        $badgeColor = "bg-blue-100 text-blue-800"; $badgeText = ""; $is_archived_board = $board_item_php['is_archived'] ?? 0;
                        if ($board_item_php['access_type'] === 'owner') { $badgeColor = "bg-green-100 text-green-800"; $badgeText = "Owner"; } 
                        elseif (isset($board_item_php['owner_name'])) {
                            $badgeText = "Shared by " . htmlspecialchars($board_item_php['owner_name']);
                            if ($board_item_php['access_type'] === 'admin') { $badgeColor = "bg-purple-100 text-purple-800"; $badgeText = "Admin • " . $badgeText; } 
                            elseif ($board_item_php['access_type'] === 'edit') { $badgeColor = "bg-yellow-100 text-yellow-800"; $badgeText = "Editor • " . $badgeText; } 
                            elseif ($board_item_php['access_type'] === 'view') { $badgeColor = "bg-gray-200 text-gray-700"; $badgeText = "Viewer • " . $badgeText; }
                        } else { $badgeText = "Shared"; }
                    ?>
                        <a href='<?php echo htmlspecialchars($board_item_php['page']); ?>?board_id=<?php echo $board_item_php['id']; ?>' 
                           class='board-card-item bg-white p-6 rounded-lg shadow-md hover-scale card <?php echo $is_archived_board ? "archived-board" : ""; ?>'
                           data-board-name="<?php echo strtolower(htmlspecialchars($board_item_php['name'])); ?>">
                            <div class='flex justify-between items-start mb-2'>
                                <h4 class='text-lg font-semibold text-[#e63946]'>
                                    <?php echo htmlspecialchars($board_item_php['name']); ?>
                                    <?php if ($is_archived_board): ?><span class="text-xs text-gray-500 font-normal">(Archived)</span><?php endif; ?>
                                </h4>
                                <span class='badge <?php echo $badgeColor; ?>'><?php echo $badgeText; ?></span>
                            </div>
                            <p class='text-gray-600'><?php echo htmlspecialchars($board_item_php['updated']); ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class='col-span-1 md:col-span-3 text-center p-8 bg-white rounded-lg shadow-md card' id="noBoardsMessage">
                        <p class='text-gray-600'>
                            <?php if ($show_archived): ?>No boards found (including archived).
                            <?php else: ?>You haven't created any active boards yet. Click 'Add Board' or check 'Show Archived'.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
                <div id="noSearchResultsMessage" class="hidden col-span-1 md:col-span-3 text-center p-8 bg-white rounded-lg shadow-md card">
                    <p class="text-gray-600">No boards found matching your search.</p>
                </div>
            </div>

            <div id="add-board-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg shadow-lg w-96">
                    <h2 class="text-xl font-semibold mb-4">Create New Board</h2>
                    <input type="text" id="board-name-modal" placeholder="Enter board name..." class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#e63946] mb-4">
                    <div class="mt-4"><p class="text-gray-600">Select a template:</p><select id="board-template-modal" class="w-full p-2 border border-gray-300 rounded-lg mt-2 focus:ring-2 focus:ring-[#e63946]"><option value="kanban">Kanban</option></select></div>
                    <div class="flex justify-end mt-6"><button id="close-modal-btn" class="mr-2 text-gray-600 hover:text-gray-800 py-2 px-4 rounded-lg border border-gray-300">Cancel</button><button id="create-board-btn" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Create</button></div>
                </div>
            </div>
        </div>
              
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Upcoming Deadlines</h3>
            <div class="bg-white p-6 rounded-lg shadow-md card">
                <div class="space-y-3">
                    <?php if ($upcoming_deadlines_display_count > 0): ?>
                        <p class="text-sm text-gray-700 font-medium">You have <?php echo $upcoming_deadlines_display_count; ?> task(s) due in the next 7 days<?php if (!$show_archived) echo " on active boards"; ?>.<?php if (count($upcoming_tasks_details) < $upcoming_deadlines_display_count && count($upcoming_tasks_details) > 0): ?><span class="text-xs">(Showing the soonest <?php echo count($upcoming_tasks_details); ?>)</span><?php endif; ?></p>
                        <?php if (!empty($upcoming_tasks_details)): ?>
                            <ul class="list-disc list-inside space-y-2 text-sm">
                                <?php foreach ($upcoming_tasks_details as $task): ?>
                                    <?php $due_date_obj = new DateTime($task['due_date']); $formatted_due_date = $due_date_obj->format('M j'); $days_until_due = (strtotime(date('Y-m-d', strtotime($task['due_date']))) - strtotime(date('Y-m-d'))) / (60 * 60 * 24); $urgency_class = ''; if ($days_until_due < 0) { $urgency_class = 'text-red-600 font-semibold'; } elseif ($days_until_due < 2) { $urgency_class = 'text-red-500'; } elseif ($days_until_due < 4) { $urgency_class = 'text-orange-500'; } $task_board_is_archived = $task['board_is_archived'] ?? 0; ?>
                                    <li class="text-gray-600 hover:text-[#e63946] <?php echo $task_board_is_archived ? 'opacity-75' : ''; ?>"><a href="kanban.php?board_id=<?php echo htmlspecialchars($task['board_id']); ?>#task-<?php echo htmlspecialchars($task['task_id']); ?>" title="Board: <?php echo htmlspecialchars($task['board_name']); ?>. Due: <?php echo $due_date_obj->format('Y-m-d'); ?>"><span class="<?php echo $urgency_class; ?>">[<?php echo $formatted_due_date; ?>]</span> <?php echo htmlspecialchars($task['task_name']); ?> <span class="text-xs text-gray-400 italic">(<?php echo htmlspecialchars($task['board_name']); ?>)</span><?php if ($task_board_is_archived): ?><span class="text-xs text-gray-400 italic">(Archived Board)</span><?php endif; ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php else: ?><p class="text-sm text-gray-500">No upcoming deadlines in the next 7 days<?php if (!$show_archived) echo " on active boards"; ?>. Great job!</p><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Recent Activity (Last <?= $limit_recent_activities ?> items)</h3>
            <div class="bg-white p-6 rounded-lg shadow-md card">
                <div class="space-y-1"> 
                    <?php if (empty($recent_activities)): ?>
                        <p class="text-sm text-gray-500">No recent activity to display across your accessible projects.</p>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity_item_recent_loop): ?>
                            <?php
                                $icon_class = 'fas fa-info-circle text-blue-500'; 
                                $activity_type_key = strtolower($activity_item_recent_loop['activity_type']);
                                $icon_map = [ 
                                    'task_created' => 'fas fa-plus-circle text-green-500', 'task_updated' => 'fas fa-edit text-blue-500', 
                                    'task_deleted' => 'fas fa-trash-alt text-red-500', 'task_moved' => 'fas fa-arrows-alt text-indigo-500', 
                                    'task_completed' => 'fas fa-check-circle text-green-600', 'task_reopened'  => 'fas fa-undo text-yellow-500', 
                                    'comment_added'  => 'fas fa-comment text-gray-500', 'collaborator_added' => 'fas fa-user-plus text-purple-500', 
                                    'collaborator_removed' => 'fas fa-user-minus text-orange-500', 
                                    'collaborator_left' => 'fas fa-sign-out-alt text-orange-600',
                                    'collaborator_permission_changed' => 'fas fa-user-shield text-teal-500', 
                                    'settings_updated' => 'fas fa-cog text-gray-600', 'board_created' => 'fas fa-chalkboard text-pink-500', 
                                    'project_archived' => 'fas fa-archive text-yellow-600', 'project_unarchived' => 'fas fa-undo text-green-600', 
                                    'project_deleted' => 'fas fa-trash-alt text-red-700',
                                    'invitation_sent' => 'fas fa-paper-plane text-blue-500',
                                    'invitation_accepted' => 'fas fa-user-check text-green-500',
                                    'invitation_declined' => 'fas fa-user-times text-red-500',
                                    'invitation_cancelled' => 'fas fa-ban text-orange-500'
                                ];
                                if (array_key_exists($activity_type_key, $icon_map)) { 
                                    $icon_class = $icon_map[$activity_type_key]; 
                                }
                                $activity_date = new DateTime($activity_item_recent_loop['created_at'], new DateTimeZone('UTC')); 
                                $activity_date->setTimezone(new DateTimeZone(date_default_timezone_get())); 
                                $formatted_date = $activity_date->format('M d, H:i');

                                $activity_link = "project_settings.php?board_id=" . $activity_item_recent_loop['board_id'] . "#activity"; 
                                if ($activity_item_recent_loop['related_entity_type'] == 'task' && $activity_item_recent_loop['related_entity_id']) {
                                    $activity_link = "kanban.php?board_id=" . $activity_item_recent_loop['board_id'] . "&task_id=" . $activity_item_recent_loop['related_entity_id'];
                                }
                            ?>
                            <div class="p-2.5 hover:bg-gray-50 flex items-start border-b border-gray-200 last:border-b-0">
                                <div class="mr-3 mt-1 flex-shrink-0 activity-item-icon">
                                    <i class="<?= $icon_class ?> text-base"></i>
                                </div>
                                <div class="flex-grow text-sm">
                                    <div class="flex justify-between items-baseline">
                                        <div>
                                            <span class="font-semibold text-gray-800"><?= htmlspecialchars($activity_item_recent_loop['actor_username']) ?></span>
                                            <span class="text-gray-600 ml-1"><?= htmlspecialchars($activity_item_recent_loop['activity_description']) ?></span>
                                        </div>
                                        <div class="text-xs text-gray-400 whitespace-nowrap ml-2 "><?= $formatted_date ?></div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        On project: 
                                        <a href="project_settings.php?board_id=<?= $activity_item_recent_loop['board_id'] ?>" class="text-blue-600 hover:underline">
                                            <?= htmlspecialchars($activity_item_recent_loop['board_name']) ?>
                                        </a>
                                        <?php if ($activity_item_recent_loop['related_entity_type'] == 'task' && $activity_item_recent_loop['related_entity_id']): ?>
                                            | <a href="<?= htmlspecialchars($activity_link) ?>" class="text-blue-600 hover:underline">View Task</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php /* Removed the "View all activity..." link as per request */ ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="text-center text-gray-600 mt-8">
            <p>© <?php echo date("Y"); ?> Planotajs. All rights reserved.</p>
            <div class="mt-2"><a href="#" class="text-[#e63946] hover:underline">About</a> | <a href="#" class="text-[#e63946] hover:underline">Contact</a> | <a href="#" class="text-[#e63946] hover:underline">Privacy Policy</a></div>
        </div>
    </div>

    <script>
        // Pass PHP variable to JavaScript for the external script
        const initialBoardCount = <?php echo count($boards_data_for_php); ?>;
    </script>
    <!-- Path relative to authenticated-view/index.php -->
    <script src="js/dashboard.js" defer></script> 
</body>
</html>