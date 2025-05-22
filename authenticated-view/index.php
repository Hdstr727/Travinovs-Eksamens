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

// For now, we'll use placeholder values for tasks and deadlines
$completed_tasks = 0;
$upcoming_deadlines = 0;

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

while ($board = $own_boards_result->fetch_assoc()) {
    $page = ($board['board_type'] === 'kanban') ? 'kanban.php' : 'kanban.php'; // Default to kanban
    $updated_time = strtotime($board['updated_at']);
    $time_diff = time() - $updated_time;
    $days_ago = floor($time_diff / (60 * 60 * 24));
    if ($days_ago == 0) $last_updated = "Updated today";
    elseif ($days_ago == 1) $last_updated = "Updated yesterday";
    else $last_updated = "Updated $days_ago days ago";
    $boards[] = ['id' => $board['board_id'], 'name' => $board['board_name'], 'page' => $page, 'updated' => $last_updated, 'access_type' => $board['access_type']];
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
    $updated_time = strtotime($board['updated_at']);
    $time_diff = time() - $updated_time;
    $days_ago = floor($time_diff / (60 * 60 * 24));
    if ($days_ago == 0) $last_updated = "Updated today";
    elseif ($days_ago == 1) $last_updated = "Updated yesterday";
    else $last_updated = "Updated $days_ago days ago";
    $boards[] = ['id' => $board['board_id'], 'name' => $board['board_name'], 'page' => $page, 'updated' => $last_updated, 'access_type' => $board['access_type'], 'owner_name' => $board['owner_name']];
}
$shared_boards_stmt->close();

// Sort boards: a more robust way is to sort by the actual timestamp before formatting
usort($boards, function($a, $b) {
    // This assumes 'updated' still holds a string like "Updated X days ago"
    // For a more accurate sort, you'd need to sort on the raw 'updated_at' timestamp
    // before it's converted to the "X days ago" string, or convert these strings back to comparable values.
    // For simplicity, keeping your current sort logic.
    return strcmp($b['updated'], $a['updated']);
});

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
// $connection->close(); // Don't close connection if other parts of the page might need it. Usually closed at script end.
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
        /* Ensure notification items are clickable */
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
                            <!-- Ensure badge element exists for JS even if count is 0, but hidden -->
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
                <p class="text-lg font-semibold text-[#e63946]"><?php echo $completed_tasks; ?></p>
                <p class="text-gray-600">Tasks Completed</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center card">
                <p class="text-lg font-semibold text-[#e63946]"><?php echo $upcoming_deadlines; ?></p>
                <p class="text-gray-600">Upcoming Deadlines</p>
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
                    $badgeColor = "bg-blue-100 text-blue-800";
                    $badgeText = isset($board['owner_name']) ? "Shared by " . htmlspecialchars($board['owner_name']) : "Shared";
                    if (!isset($board['owner_name'])) {
                        $badgeColor = "bg-green-100 text-green-800";
                        $badgeText = "Owner";
                    } elseif ($board['access_type'] == 'editor') {
                        $badgeColor = "bg-yellow-100 text-yellow-800";
                        $badgeText = "Editor • " . $badgeText;
                    } elseif ($board['access_type'] == 'viewer') {
                        $badgeColor = "bg-gray-100 text-gray-800";
                        $badgeText = "Viewer • " . $badgeText;
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
                            <!-- <option value="gantt">Gantt Chart</option>
                            <option value="goal-tracker">Goal Tracker</option> -->
                        </select>
                    </div>
                    <div class="flex justify-end mt-6">
                        <button id="close-modal-btn" class="mr-2 text-gray-600 hover:text-gray-800 py-2 px-4 rounded-lg border border-gray-300">Cancel</button>
                        <button id="create-board-btn" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Create</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Deadlines (Placeholder) -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Upcoming Deadlines</h3>
            <div class="bg-white p-6 rounded-lg shadow-md card">
                <div class="space-y-4">
                    <p class="text-sm text-gray-500">No upcoming deadlines.</p>
                </div>
            </div>
        </div>

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
            const htmlElement = document.documentElement; // Target <html> element

            function setDarkMode(isDark) {
                if (isDark) {
                    htmlElement.classList.add('dark-mode');
                    if (darkModeToggle) darkModeToggle.textContent = '☀️'; // Sun icon
                } else {
                    htmlElement.classList.remove('dark-mode');
                    if (darkModeToggle) darkModeToggle.textContent = '🌙'; // Moon icon
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
        profileToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
            notificationsDropdown.classList.add('hidden'); // Close other dropdown
        });

        // Notifications Dropdown Script
        const notificationsToggle = document.getElementById('notifications-toggle');
        const notificationsDropdown = document.getElementById('notifications-dropdown');
        const notificationsList = document.getElementById('notifications-list');
        // const notificationCountBadge = document.getElementById('notification-count-badge'); // Already available via PHP or created by JS
        const markAllReadBtn = document.getElementById('mark-all-read');

        function fetchNotifications() {
            console.log("JS: fetchNotifications called"); // DEBUG
            fetch('ajax_handlers/get_notifications.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("JS: Data received from get_notifications:", data); // DEBUG
                    if (data.success) {
                        renderNotifications(data.notifications);
                        updateUnreadCount(data.unread_count);
                    } else {
                        notificationsList.innerHTML = `<p class="text-sm text-red-500">Error: ${data.error || 'Could not load notifications.'}</p>`;
                        console.error("JS: Error from get_notifications:", data.error); // DEBUG
                    }
                })
                .catch(error => {
                    console.error('JS: Error fetching notifications:', error); // DEBUG
                    notificationsList.innerHTML = '<p class="text-sm text-red-500">Error fetching notifications. Please try again.</p>';
                });
        }

        function renderNotifications(notifications) {
            console.log("JS: renderNotifications called with:", notifications); // DEBUG
            if (!notifications || notifications.length === 0) {
                notificationsList.innerHTML = '<p class="text-sm text-gray-600">No new notifications.</p>';
                return;
            }

            let html = '';
            notifications.forEach(notif => {
                const isUnreadClass = notif.is_read == 0 ? 'font-semibold bg-sky-50' : 'text-gray-700';
                // Ensure message and created_at are treated as strings to prevent XSS if they aren't already escaped server-side
                const messageText = String(notif.message || '');
                const createdAtText = String(notif.formatted_created_at || '');

                const linkHtml = notif.link ?
                    `<a href="${encodeURI(notif.link)}" class="block hover:bg-gray-100 p-2 rounded ${isUnreadClass}" data-id="${notif.notification_id}">` :
                    `<div class="p-2 ${isUnreadClass}" data-id="${notif.notification_id}">`;
                const linkEndHtml = notif.link ? `</a>` : `</div>`;

                html += `
                    <div class="notification-item border-b border-gray-200 last:border-b-0">
                        ${linkHtml}
                            <p class="text-sm ">${messageText.replace(/</g, "<").replace(/>/g, ">")}</p>
                            <p class="text-xs text-gray-500 mt-1">${createdAtText.replace(/</g, "<").replace(/>/g, ">")}</p>
                        ${linkEndHtml}
                    </div>
                `;
            });
            notificationsList.innerHTML = html;

            document.querySelectorAll('.notification-item a, .notification-item div[data-id]').forEach(item => {
                item.addEventListener('click', function(e) {
                    const notificationId = this.dataset.id;
                    const isLink = this.tagName === 'A';
                    // Only mark as read if it's unread (check class or a data attribute)
                    const isCurrentlyUnread = this.classList.contains('font-semibold'); // Or check notif.is_read from original data if needed

                    if (isCurrentlyUnread) {
                        markNotificationAsRead(notificationId, !isLink);
                    }
                    if (!isLink) { // If it's not a link, it's just a div, stop propagation
                        e.stopPropagation();
                    }
                    // If it IS a link, let the default browser navigation happen.
                });
            });
        }

        function updateUnreadCount(count) {
            console.log("JS: updateUnreadCount called with count:", count); // DEBUG
            let badge = document.getElementById('notification-count-badge'); // This ID should exist from PHP
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'flex';
                } else {
                    badge.textContent = '';
                    badge.style.display = 'none';
                }
            } else {
                console.error("JS: Notification count badge element not found!"); // DEBUG
            }
        }

        function markNotificationAsRead(notificationId, refreshList = true) {
            console.log("JS: markNotificationAsRead called for ID:", notificationId, "Refresh list:", refreshList); // DEBUG
            const formData = new FormData();
            formData.append('notification_id', notificationId);

            fetch('ajax_handlers/mark_notification_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log("JS: Response from mark_notification_read:", data); // DEBUG
                if (data.success) {
                    if (refreshList) {
                        fetchNotifications(); // Refresh the list to show it as read and update count
                    } else {
                        // Manually update UI for the clicked item if not refreshing whole list
                        const itemClicked = notificationsList.querySelector(`.notification-item [data-id="${notificationId}"]`);
                        if (itemClicked) {
                            itemClicked.classList.remove('font-semibold', 'bg-sky-50');
                            itemClicked.classList.add('text-gray-700'); // Or whatever your read style is
                        }
                        // Visually decrement count
                        let currentBadge = document.getElementById('notification-count-badge');
                        if (currentBadge) {
                           let currentCount = parseInt(currentBadge.textContent || "0");
                           if (currentCount > 0) {
                               updateUnreadCount(currentCount - 1);
                           }
                        }
                    }
                } else {
                    console.error("JS: Failed to mark notification as read - server error:", data.error); //DEBUG
                }
            })
            .catch(error => console.error('JS: Error marking notification as read:', error)); // DEBUG
        }

        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log("JS: Mark all as read clicked"); // DEBUG
            const formData = new FormData();
            formData.append('mark_all', 'true');

            fetch('ajax_handlers/mark_notification_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log("JS: Response from mark_all_read:", data); // DEBUG
                if (data.success) {
                    fetchNotifications();
                } else {
                    console.error("JS: Failed to mark all as read - server error:", data.error); //DEBUG
                }
            })
            .catch(error => console.error('JS: Error marking all notifications as read:', error)); // DEBUG
        });

        notificationsToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = notificationsDropdown.classList.toggle('hidden');
            profileDropdown.classList.add('hidden'); // Close other dropdown
            if (!isHidden) {
                fetchNotifications();
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!notificationsToggle.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                notificationsDropdown.classList.add('hidden');
            }
            if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });

        // Add Board Modal Script
        const addBoardBtn = document.getElementById('add-board-btn');
        const addBoardModal = document.getElementById('add-board-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const createBoardBtn = document.getElementById('create-board-btn');
        const boardNameModalInput = document.getElementById('board-name-modal');
        const boardTemplateModalSelect = document.getElementById('board-template-modal');

        if (addBoardBtn) {
            addBoardBtn.addEventListener('click', () => addBoardModal.classList.remove('hidden'));
        }
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', () => addBoardModal.classList.add('hidden'));
        }
        if (addBoardModal) {
            addBoardModal.addEventListener('click', function(e) {
                if (e.target === this) this.classList.add('hidden');
            });
        }
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
                    .catch(error => {
                        console.error('Error creating board:', error);
                        alert('An error occurred while creating the board.');
                    });
                } else {
                    alert('Please enter a board name.');
                }
            });
        }

        // Optional: Initial fetch of notifications if you want the badge to be live without a click
        // but only if you don't have polling enabled, to avoid too many initial requests.
        // fetchNotifications(); // Uncomment if you want this behavior.

        // Optional: Polling for notifications
        // setInterval(fetchNotifications, 30000); // e.g., every 30 seconds
    </script>
</body>
</html>