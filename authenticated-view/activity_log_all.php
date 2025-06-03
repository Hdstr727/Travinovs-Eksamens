<?php
// authenticated-view/activity_log_all.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: core/login.php");
    exit();
}

require_once '../admin/database/connection.php';
require_once 'core/functions.php';

$user_id = $_SESSION['user_id'];

// --- Filtering Parameters (from GET request) ---
$search_keyword = trim($_GET['keyword'] ?? '');
$filter_board_id_from_url = isset($_GET['board_id']) ? (int)$_GET['board_id'] : 0;
$filter_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$filter_activity_type = trim($_GET['activity_type'] ?? '');
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to = trim($_GET['date_to'] ?? '');


// --- Pagination ---
$items_per_page = 25;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;


// --- Determine boards for which the user can view activity logs ---
$board_ids_user_can_view_activity_for = [];

// 1. Boards where user is owner (always has access to logs)
$owner_boards_sql = "SELECT board_id FROM Planner_Boards WHERE user_id = ? AND is_deleted = 0";
$stmt_owner_log_access = $connection->prepare($owner_boards_sql);
if ($stmt_owner_log_access) {
    $stmt_owner_log_access->bind_param("i", $user_id);
    $stmt_owner_log_access->execute();
    $res_owner_log_access = $stmt_owner_log_access->get_result();
    while ($row = $res_owner_log_access->fetch_assoc()) {
        $board_ids_user_can_view_activity_for[] = $row['board_id'];
    }
    $stmt_owner_log_access->close();
} else {
    error_log("Activity Log All: Failed to prepare owner_boards_sql for activity log: " . $connection->error);
}

// 2. Boards where user is a collaborator with specific log access rights
$collab_boards_log_access_sql = "
    SELECT 
        c.board_id, 
        b.activity_log_permissions,
        c.permission_level as collaborator_permission_level 
    FROM Planner_Collaborators c
    JOIN Planner_Boards b ON c.board_id = b.board_id
    WHERE c.user_id = ? AND b.is_deleted = 0";
$stmt_collab_log_access = $connection->prepare($collab_boards_log_access_sql);
if ($stmt_collab_log_access) {
    $stmt_collab_log_access->bind_param("i", $user_id);
    $stmt_collab_log_access->execute();
    $res_collab_log_access = $stmt_collab_log_access->get_result();
    while ($board_perm_info = $res_collab_log_access->fetch_assoc()) {
        $can_view_log_collab = false;
        $log_perms = json_decode($board_perm_info['activity_log_permissions'] ?? '', true);
        if (!is_array($log_perms)) $log_perms = [];

        $role = $board_perm_info['collaborator_permission_level'];

        if ($role === 'admin' && ($log_perms['admin'] ?? true)) {
            $can_view_log_collab = true;
        } elseif ($role === 'edit' && ($log_perms['edit'] ?? false)) {
            $can_view_log_collab = true;
        } elseif ($role === 'view' && ($log_perms['view'] ?? false)) {
            $can_view_log_collab = true;
        }
        
        if ($can_view_log_collab) {
            $board_ids_user_can_view_activity_for[] = $board_perm_info['board_id'];
        }
    }
    $stmt_collab_log_access->close();
} else {
    error_log("Activity Log All: Failed to prepare collab_boards_log_access_sql: " . $connection->error);
}
$board_ids_user_can_view_activity_for = array_unique($board_ids_user_can_view_activity_for);
// --- End of determining accessible boards for activity log ---


// --- Build SQL Query for fetching activities ---
$sql_base = "SELECT al.*, u.username as actor_username, b.board_name 
             FROM Planner_ActivityLog al
             JOIN Planner_Users u ON al.user_id = u.user_id
             JOIN Planner_Boards b ON al.board_id = b.board_id";
$where_clauses = [];
$params = [];
$types = "";

if (empty($board_ids_user_can_view_activity_for)) {
    $activities = [];
    $total_activities = 0;
    $all_my_boards_for_filter = [];
    $all_users_for_filter = [];
    $all_activity_types_for_filter = [];
} else {
    $board_ids_placeholders = implode(',', array_fill(0, count($board_ids_user_can_view_activity_for), '?'));
    $where_clauses[] = "al.board_id IN ({$board_ids_placeholders})";
    foreach ($board_ids_user_can_view_activity_for as $id) {
        $params[] = $id;
        $types .= "i";
    }

    if (!empty($search_keyword)) {
        $where_clauses[] = "(al.activity_description LIKE ? OR u.username LIKE ? OR b.board_name LIKE ?)";
        $keyword_param = "%" . $search_keyword . "%";
        array_push($params, $keyword_param, $keyword_param, $keyword_param);
        $types .= "sss";
    }
    if ($filter_board_id_from_url > 0) {
        if (in_array($filter_board_id_from_url, $board_ids_user_can_view_activity_for)) {
            $where_clauses[] = "al.board_id = ?";
            $params[] = $filter_board_id_from_url;
            $types .= "i";
        } else {
            $where_clauses[] = "1 = 0";
        }
    }
    if ($filter_user_id > 0) {
        $where_clauses[] = "al.user_id = ?";
        $params[] = $filter_user_id;
        $types .= "i";
    }
    if (!empty($filter_activity_type)) {
        $where_clauses[] = "al.activity_type = ?";
        $params[] = $filter_activity_type;
        $types .= "s";
    }
    if (!empty($filter_date_from)) {
        $where_clauses[] = "DATE(al.created_at) >= ?";
        $params[] = $filter_date_from;
        $types .= "s";
    }
    if (!empty($filter_date_to)) {
        $where_clauses[] = "DATE(al.created_at) <= ?";
        $params[] = $filter_date_to;
        $types .= "s";
    }
}

$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

$total_activities = 0;
if (!empty($board_ids_user_can_view_activity_for)) {
    $sql_count = "SELECT COUNT(al.activity_id) as total FROM Planner_ActivityLog al JOIN Planner_Users u ON al.user_id = u.user_id JOIN Planner_Boards b ON al.board_id = b.board_id" . $sql_where;
    $stmt_count = $connection->prepare($sql_count);
    if ($stmt_count) {
        if (!empty($params)) {
            $stmt_count->bind_param($types, ...$params);
        }
        $stmt_count->execute();
        $count_res = $stmt_count->get_result()->fetch_assoc();
        $total_activities = $count_res ? (int)$count_res['total'] : 0;
        $stmt_count->close();
    } else {
        error_log("Activity Log All: Failed to prepare count query: " . $connection->error . " SQL: " . $sql_count);
    }
}

$total_pages = ($items_per_page > 0 && $total_activities > 0) ? ceil($total_activities / $items_per_page) : 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$offset = ($current_page - 1) * $items_per_page;

$activities = [];
if ($total_activities > 0 && !empty($board_ids_user_can_view_activity_for)) {
    $sql_fetch = $sql_base . $sql_where . " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
    $stmt_fetch = $connection->prepare($sql_fetch);
    if ($stmt_fetch) {
        $current_types_fetch = $types . "ii";
        $current_params_fetch = $params;
        $current_params_fetch[] = $items_per_page;
        $current_params_fetch[] = $offset;
        $stmt_fetch->bind_param($current_types_fetch, ...$current_params_fetch);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        while ($row = $result_fetch->fetch_assoc()) {
            $activities[] = $row;
        }
        $stmt_fetch->close();
    } else {
        error_log("Activity Log All: Failed to prepare fetch query: " . $connection->error . " SQL: " . $sql_fetch);
    }
}

$all_my_boards_for_filter = [];
if (!empty($board_ids_user_can_view_activity_for)) {
    $sql_boards_filter = "SELECT board_id, board_name FROM Planner_Boards 
                          WHERE is_deleted = 0 AND board_id IN (" . implode(',', array_fill(0, count($board_ids_user_can_view_activity_for), '?')) . ") 
                          ORDER BY board_name ASC";
    $stmt_boards_filter = $connection->prepare($sql_boards_filter);
    if($stmt_boards_filter){
        $stmt_boards_filter->bind_param(str_repeat('i', count($board_ids_user_can_view_activity_for)), ...$board_ids_user_can_view_activity_for);
        $stmt_boards_filter->execute();
        $res_boards_filter = $stmt_boards_filter->get_result();
        while($b = $res_boards_filter->fetch_assoc()) $all_my_boards_for_filter[] = $b;
        $stmt_boards_filter->close();
    } else {
        error_log("Activity Log All: Failed to prepare boards_filter query: " . $connection->error);
    }
}

$all_users_for_filter = [];
if (!empty($board_ids_user_can_view_activity_for)) {
    $sql_users_filter = "SELECT DISTINCT u.user_id, u.username FROM Planner_Users u 
                         JOIN Planner_ActivityLog al ON u.user_id = al.user_id 
                         WHERE al.board_id IN (" . implode(',', array_fill(0, count($board_ids_user_can_view_activity_for), '?')) . ")
                         ORDER BY u.username ASC";
    $stmt_users_filter = $connection->prepare($sql_users_filter);
     if($stmt_users_filter){
        $stmt_users_filter->bind_param(str_repeat('i', count($board_ids_user_can_view_activity_for)), ...$board_ids_user_can_view_activity_for);
        $stmt_users_filter->execute();
        $res_users_filter = $stmt_users_filter->get_result();
        while($u = $res_users_filter->fetch_assoc()) $all_users_for_filter[] = $u;
        $stmt_users_filter->close();
    } else {
        error_log("Activity Log All: Failed to prepare users_filter query: " . $connection->error);
    }
}

$all_activity_types_for_filter = [];
if (!empty($board_ids_user_can_view_activity_for)) {
    $sql_types_filter = "SELECT DISTINCT activity_type FROM Planner_ActivityLog 
                         WHERE board_id IN (" . implode(',', array_fill(0, count($board_ids_user_can_view_activity_for), '?')) . ")
                         ORDER BY activity_type ASC";
    $stmt_types_filter = $connection->prepare($sql_types_filter);
    if($stmt_types_filter){
        $stmt_types_filter->bind_param(str_repeat('i', count($board_ids_user_can_view_activity_for)), ...$board_ids_user_can_view_activity_for);
        $stmt_types_filter->execute();
        $res_types_filter = $stmt_types_filter->get_result();
        while($t = $res_types_filter->fetch_assoc()) $all_activity_types_for_filter[] = $t['activity_type'];
        $stmt_types_filter->close();
    } else {
        error_log("Activity Log All: Failed to prepare types_filter query: " . $connection->error);
    }
}

if (function_exists('date_default_timezone_set') && @date_default_timezone_get() == 'UTC') {
    date_default_timezone_set('Europe/Riga');
} elseif (!@date_default_timezone_get()) {
    date_default_timezone_set('Europe/Riga');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Activity Log - Planner+</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/dark-theme.css">
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    <style>
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <?php
        echo "<script>
            (function() {
                const htmlElement = document.documentElement;
                let currentDarkMode = localStorage.getItem('darkMode');
                if (currentDarkMode === 'true') {
                    htmlElement.classList.add('dark-mode');
                } else if (currentDarkMode === null && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    htmlElement.classList.add('dark-mode');
                }
            })();
        </script>";
    ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Global Activity Log</h1>
            <a href="index.php" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition text-sm">
                Back to Dashboard
            </a>
        </div>

        <!-- Filter Section -->
        <form method="GET" action="activity_log_all.php" class="bg-white p-6 rounded-lg shadow-md mb-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 items-end">
                <div>
                    <label for="keyword" class="block text-sm font-medium text-gray-700">Keyword</label>
                    <input type="text" name="keyword" id="keyword" value="<?= htmlspecialchars($search_keyword ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Search description, user, board...">
                </div>
                <div>
                    <label for="board_id_filter" class="block text-sm font-medium text-gray-700">Project</label>
                    <select name="board_id" id="board_id_filter" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="0">All My Projects</option>
                        <?php foreach ($all_my_boards_for_filter as $board_filter_item): ?>
                            <option value="<?= $board_filter_item['board_id'] ?>" <?= (($filter_board_id_from_url ?? 0) == $board_filter_item['board_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($board_filter_item['board_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="user_id_filter" class="block text-sm font-medium text-gray-700">User</label>
                    <select name="user_id" id="user_id_filter" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="0">All Users</option>
                         <?php foreach ($all_users_for_filter as $user_filter_item): ?>
                            <option value="<?= $user_filter_item['user_id'] ?>" <?= (($filter_user_id ?? 0) == $user_filter_item['user_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user_filter_item['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="activity_type_filter" class="block text-sm font-medium text-gray-700">Activity Type</label>
                    <select name="activity_type" id="activity_type_filter" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">All Types</option>
                        <?php foreach ($all_activity_types_for_filter as $type_filter_item): ?>
                            <option value="<?= htmlspecialchars($type_filter_item) ?>" <?= (($filter_activity_type ?? '') == $type_filter_item) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $type_filter_item))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($filter_date_from ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($filter_date_to ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
            </div>
            <div class="mt-4 flex justify-end space-x-2">
                <a href="activity_log_all.php" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">Reset Filters</a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#e63946] hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">Apply Filters</button>
            </div>
        </form>

        <!-- Activity List -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="divide-y divide-gray-200">
                <?php if (empty($activities)): ?>
                    <p class="p-6 text-gray-500 text-center">No activities found matching your criteria.</p>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                        <?php
                            $icon_class = 'fas fa-info-circle text-blue-500';
                            $activity_type_key = strtolower($activity['activity_type']);
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
                            $timestamp_from_db = $activity['created_at'];
                            try {
                                $activity_date_obj = new DateTime($timestamp_from_db);
                                $formatted_activity_date = $activity_date_obj->format('M d, Y H:i');
                            } catch (Exception $e) {
                                error_log("Error parsing date for activity log (All Activities): " . $e->getMessage() . " - Timestamp: " . $timestamp_from_db);
                                $formatted_activity_date = "Invalid date";
                            }
                        ?>
                        <div class="p-4 hover:bg-gray-50 flex items-start">
                            <div class="mr-4 mt-1 flex-shrink-0"><i class="<?= $icon_class ?> text-lg"></i></div>
                            <div class="flex-grow">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="font-semibold text-gray-800"><?= htmlspecialchars($activity['actor_username']) ?></span>
                                        <span class="text-gray-600 ml-1"><?= htmlspecialchars($activity['activity_description']) ?></span>
                                    </div>
                                    <div class="text-xs text-gray-500 whitespace-nowrap ml-2"><?= $formatted_activity_date ?></div>
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    On project:
                                    <a href="project_settings.php?board_id=<?= $activity['board_id'] ?>" class="text-blue-600 hover:underline">
                                        <?= htmlspecialchars($activity['board_name']) ?>
                                    </a>
                                    <?php if ($activity['related_entity_type'] == 'task' && $activity['related_entity_id']): ?>
                                        | <a href="kanban.php?board_id=<?= $activity['board_id'] ?>&task_id=<?= $activity['related_entity_id'] ?>" class="text-blue-600 hover:underline">View Task</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if (($total_pages ?? 0) > 1): ?>
        <div class="mt-8 flex justify-center items-center space-x-1">
            <?php
            $query_params = $_GET;
            unset($query_params['page']);
            $base_query_string = http_build_query($query_params);
            if (!empty($base_query_string)) $base_query_string .= '&';
            ?>
            <?php if ($current_page > 1): ?>
                <a href="?<?= $base_query_string ?>page=<?= $current_page - 1 ?>" class="px-3 py-1 border border-gray-300 rounded-md bg-white text-sm text-gray-600 hover:bg-gray-50">Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $current_page): ?>
                    <span class="px-3 py-1 border border-transparent rounded-md bg-[#e63946] text-white text-sm"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= $base_query_string ?>page=<?= $i ?>" class="px-3 py-1 border border-gray-300 rounded-md bg-white text-sm text-gray-600 hover:bg-gray-50"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="?<?= $base_query_string ?>page=<?= $current_page + 1 ?>" class="px-3 py-1 border border-gray-300 rounded-md bg-white text-sm text-gray-600 hover:bg-gray-50">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="text-center text-gray-600 mt-12 py-4 border-t border-gray-200">
            <p>© <?= date("Y") ?> Planner+. All rights reserved.</p>
        </div>
    </div>
</body>
</html>