<?php
// core/layout.php
session_start();

// Determine project root path dynamically for consistency
$project_root = dirname(dirname(dirname(__FILE__))); // This should point to Travinovs-Eksamens directory

require_once $project_root . '/config.php'; // Use dynamic project root
if (!isset($_SESSION['user_id'])) {
    header("Location:" . LOGIN_URL);
    exit();
}


if (isset($_GET['board_id'])) {
    $_SESSION['last_board_id'] = $_GET['board_id'];
}
// Create the Kanban URL with the last board ID if available
$kanban_url = "kanban.php";
if (isset($_SESSION['last_board_id'])) {
    $kanban_url .= "?board_id=" . $_SESSION['last_board_id'];
}

// Use dynamic project root for database connection as well
require_once $project_root . '/admin/database/connection.php';

// Get user info from database including profile picture
$user_id = $_SESSION['user_id'];
$sql = "SELECT username, profile_picture FROM Planner_Users WHERE user_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Set username from database or session
$username = $user['username'] ?? ($_SESSION['username'] ?? 'User');

// Get the path stored in the database (e.g., "uploads/profile_pictures/image.jpg")
$db_profile_picture_path = $user['profile_picture'];

// For file_exists(), construct the full server path.
// __DIR__ is the directory of layout.php (authenticated-view/core/)
$full_server_path_to_picture = __DIR__ . '/' . $db_profile_picture_path;

if (!empty($db_profile_picture_path) && file_exists($full_server_path_to_picture)) {
    $user_avatar = 'core/' . $db_profile_picture_path;
} else {
    $user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=e63946&color=fff";
}

$chat_url = "chat.php";
if (isset($_SESSION['last_board_id'])) {
    $chat_url .= "?board_id=" . $_SESSION['last_board_id'];
}

// Get unread notifications count
$unread_notifications_count = 0;
if (isset($connection)) {
    $stmt_count_notif = $connection->prepare("SELECT COUNT(*) as count FROM Planner_Notifications WHERE user_id = ? AND is_read = 0");
    if ($stmt_count_notif) {
        $stmt_count_notif->bind_param("i", $user_id);
        $stmt_count_notif->execute();
        $count_result_notif = $stmt_count_notif->get_result()->fetch_assoc();
        if ($count_result_notif) {
            $unread_notifications_count = $count_result_notif['count'];
        }
        $stmt_count_notif->close();
    } else {
        error_log("Layout.php: Failed to prepare statement for unread notifications count: " . $connection->error);
    }
} else {
    error_log("Layout.php: Database connection not available for notification count.");
}

?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? "Plānotājs+") ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/dark-theme.css">
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    <style>
        .badge { font-size: 0.65rem; padding: 0.15rem 0.5rem; border-radius: 9999px; }
        .notification-item > a, .notification-item > div[data-id] { cursor: pointer; }

    </style>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col">
   
    <header class="bg-white shadow-md p-4 flex justify-between items-center">
        <a href="index.php" class="text-xl font-bold text-[#e63946]">Planner+</a>
        <nav class="flex gap-4">
            <a href="index.php" class="text-gray-700 hover:text-[#e63946]">Dashboard</a>
            <a href="<?= htmlspecialchars($kanban_url) ?>" class="text-gray-700 hover:text-[#e63946]">Kanban</a>
            <a href="calendar.php" class="text-gray-700 hover:text-[#e63946]">Calendar</a>
            <a href="project_settings.php" class="text-gray-700 hover:text-[#e63946]">Settings</a>
            <a href="<?= htmlspecialchars($chat_url) ?>" class="text-gray-700 hover:text-[#e63946]">Chat</a>
        </nav>
        <div class="flex items-center space-x-4">
            <div class="relative">
                <button id="notifications-toggle" title="Notifications" class="relative bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition">
                    🔔
                    <?php if ($unread_notifications_count > 0): ?>
                        <span id="notification-count-badge" class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
                            <?= $unread_notifications_count ?>
                        </span>
                    <?php else: ?>
                        <span id="notification-count-badge" class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center" style="display: none;"></span>
                    <?php endif; ?>
                </button>
                <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-md p-4 z-50 max-h-96 overflow-y-auto">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-lg font-semibold">Notifications</h3>
                        <a href="#" id="mark-all-read" class="text-sm text-[#e63946] hover:underline">Mark all as read</a>
                    </div>
                    <div id="notifications-list">
                        <p class="text-sm text-gray-600">Loading notifications...</p>
                    </div>
                </div>
            </div>

            <button id="dark-mode-toggle" title="Toggle dark mode" class="bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition">
                🌙
            </button>

            <a href="core/profile.php" class="relative group" title="Edit Profile">
                <img src="<?= htmlspecialchars($user_avatar) ?>" class="w-10 h-10 rounded-full border group-hover:opacity-90 transition-opacity" alt="Avatar">
                <div class="absolute opacity-0 group-hover:opacity-100 transition-opacity text-xs bg-black text-white px-2 py-1 rounded -bottom-8 left-1/2 transform -translate-x-1/2 whitespace-nowrap">
                    Rediģēt profilu
                </div>
            </a>
            <span class="font-semibold"><?= htmlspecialchars($username) ?></span>
            <a href="core/logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-700">Logout</a>
        </div>
    </header>

    <main class="flex-grow container mx-auto p-6">
        <?php 
        if (isset($content) && file_exists($content)) {
            include $content; 
        } elseif (isset($content)) {
            echo "<p class='text-red-500'>Error: Content file not found at specified path: " . htmlspecialchars($content) . "</p>";
        } else {
            echo "<p>Content variable not set.</p>";
        }
        ?>
    </main>

    <footer class="bg-gray-200 text-center p-4 text-gray-600">
        © <?= date("Y") ?> Planner+. All rights reserved.
    </footer>

<!-- In authenticated-view/core/layout.php -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const darkModeToggleLayout = document.getElementById('dark-mode-toggle');
        const htmlElementLayout = document.documentElement; 

        function setLayoutDarkMode(isDark) {
            if (isDark) {
                htmlElementLayout.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'true');
                if(darkModeToggleLayout) darkModeToggleLayout.textContent = '☀️';
            } else {
                htmlElementLayout.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'false');
                if(darkModeToggleLayout) darkModeToggleLayout.textContent = '🌙';
            }
        }

        let initialDarkMode = localStorage.getItem('darkMode');
        if (initialDarkMode === 'true') {
            setLayoutDarkMode(true);
        } else if (initialDarkMode === 'false') {
            setLayoutDarkMode(false);
        } else { 
            const prefersDarkSchemeLayout = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            setLayoutDarkMode(prefersDarkSchemeLayout);
        }

        if (darkModeToggleLayout) {
            darkModeToggleLayout.addEventListener('click', () => {
                setLayoutDarkMode(!htmlElementLayout.classList.contains('dark-mode'));
            });
        }
        
        const notificationsToggle = document.getElementById('notifications-toggle');
        const notificationsDropdown = document.getElementById('notifications-dropdown');
        const notificationsList = document.getElementById('notifications-list');
        const markAllReadBtn = document.getElementById('mark-all-read');
        const notificationCountBadgeLayout = document.getElementById('notification-count-badge');

        const NOTIFICATION_POLLING_INTERVAL_LAYOUT = 15000;
        let notificationPollerLayout = null;

        // START: Define handleInvitationAction globally or within this scope
        // This function is copied from dashboard.js
        window.handleLayoutInvitationAction = function(invitationId, action, buttonElement) {
            buttonElement.disabled = true;
            buttonElement.textContent = 'Processing...';
            const otherButton = action === 'accept' ?
                                buttonElement.nextElementSibling :
                                buttonElement.previousElementSibling;
            if(otherButton) otherButton.disabled = true;

            const formData = new FormData();
            formData.append('invitation_id', invitationId);

            let targetUrl = '';
            // Path relative to layout.php (in authenticated-view/core/)
            // to ajax_handlers (in authenticated-view/ajax_handlers/)
            if (action === 'accept') {
                targetUrl = 'ajax_handlers/accept_invitation.php';
            } else if (action === 'decline') {
                targetUrl = 'ajax_handlers/decline_invitation.php';
            } else {
                console.error('Invalid invitation action:', action);
                buttonElement.disabled = false;
                if(otherButton) otherButton.disabled = false;
                buttonElement.textContent = action.charAt(0).toUpperCase() + action.slice(1);
                return;
            }

            fetch(targetUrl, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notificationItemDiv = buttonElement.closest('.notification-item > div'); // Target the div wrapper
                    if (notificationItemDiv) { // Check if the div wrapper exists
                        const actionDiv = buttonElement.parentElement;
                        if(actionDiv) actionDiv.innerHTML = `<p class="text-xs text-gray-600 italic mt-1">Invitation ${action}ed.</p>`;
                    }
                    fetchNotifications(); // Refresh list and counts
                } else {
                    alert('Error: ' + (data.message || 'Could not process invitation.'));
                    buttonElement.disabled = false;
                    if(otherButton) otherButton.disabled = false;
                    buttonElement.textContent = action.charAt(0).toUpperCase() + action.slice(1);
                }
            })
            .catch(error => {
                console.error('Layout Invitation action error:', error);
                alert('An error occurred while processing the invitation.');
                buttonElement.disabled = false;
                if(otherButton) otherButton.disabled = false;
                buttonElement.textContent = action.charAt(0).toUpperCase() + action.slice(1);
            });
        }
        // END: Define handleInvitationAction

        function fetchNotifications() {
            if (!notificationsList) {
                return;
            }
            fetch('ajax_handlers/get_notifications.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        renderNotifications(data.notifications);
                        updateUnreadCount(data.unread_count);
                    } else {
                        notificationsList.innerHTML = `<p class="text-sm text-red-500">Error: ${data.error || 'Could not load notifications.'}</p>`;
                    }
                })
                .catch(error => {
                    console.error("Layout Fetch notifications error:", error);
                    notificationsList.innerHTML = '<p class="text-sm text-red-500">Error fetching notifications. Please try again.</p>';
                });
        }

        function renderNotifications(notifications) {
            if (!notificationsList) return;

            if (!notifications || notifications.length === 0) {
                notificationsList.innerHTML = '<p class="text-sm text-gray-600">No new notifications.</p>';
                return;
            }

            let html = '';
            notifications.forEach(notif => {
                const isUnreadClass = notif.is_read == 0 ? 'font-semibold bg-sky-50' : 'text-gray-700';
                const messageText = String(notif.message || '').replace(/</g, "<").replace(/>/g, ">");
                const createdAtText = String(notif.formatted_created_at || '').replace(/</g, "<").replace(/>/g, ">");
                
                let actionButtonsHtml = ''; // Changed variable name slightly
                if (notif.type === 'invitation' &&
                    notif.related_entity_type === 'invitation' &&
                    notif.related_entity_id &&
                    notif.is_read == 0) { // Only show for unread invitations

                    actionButtonsHtml = `
                        <div class="mt-2 flex space-x-2">
                            <button
                                onclick="handleLayoutInvitationAction(${notif.related_entity_id}, 'accept', this)"
                                class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">
                                Accept
                            </button>
                            <button
                                onclick="handleLayoutInvitationAction(${notif.related_entity_id}, 'decline', this)"
                                class="text-xs bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">
                                Decline
                            </button>
                        </div>`;
                } else if (notif.type === 'invitation' && notif.is_read == 1) {
                    // Optionally, show a message that it was actioned if you want
                    // actionButtonsHtml = `<p class="text-xs text-gray-500 italic mt-1">Invitation already actioned.</p>`;
                }


                const linkHtml = notif.link && !actionButtonsHtml ? // Check actionButtonsHtml now
                    `<a href="${encodeURI(notif.link)}" class="block hover:bg-gray-100 p-2 rounded ${isUnreadClass}" data-id="${notif.notification_id}">` :
                    `<div class="p-2 ${isUnreadClass}" data-id="${notif.notification_id}">`;
                const linkEndHtml = notif.link && !actionButtonsHtml ? `</a>` : `</div>`;

                html += `
                    <div class="notification-item border-b border-gray-200 last:border-b-0">
                        ${linkHtml}
                            <p class="text-sm ">${messageText}</p>
                            <p class="text-xs text-gray-500 mt-1">${createdAtText}</p>
                            ${actionButtonsHtml} 
                        ${linkEndHtml}
                    </div>
                `;
            });
            notificationsList.innerHTML = html;

            document.querySelectorAll('#notifications-list .notification-item > a[data-id], #notifications-list .notification-item > div[data-id]').forEach(item => {
                // Only add general click listener if no action buttons are present for this item
                if (!item.querySelector('button[onclick^="handleLayoutInvitationAction"]')) {
                    item.addEventListener('click', function(e) {
                        const notificationId = this.dataset.id;
                        if (this.classList.contains('font-semibold') || this.classList.contains('bg-sky-50')) {
                            markNotificationAsRead(notificationId, this.tagName !== 'A');
                        }
                    });
                }
            });
        }
    
        function updateUnreadCount(count) {
            if (notificationCountBadgeLayout) {
                if (count > 0) {
                    notificationCountBadgeLayout.textContent = count;
                    notificationCountBadgeLayout.style.display = 'flex'; 
                } else {
                    notificationCountBadgeLayout.textContent = '';
                    notificationCountBadgeLayout.style.display = 'none'; 
                }
            }
        }

        function markNotificationAsRead(notificationId, refreshList = true) {
            const formData = new FormData();
            formData.append('notification_id', notificationId);

            fetch('ajax_handlers/mark_notification_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (refreshList) {
                        fetchNotifications(); 
                    } else {
                        const itemClicked = notificationsList && (notificationsList.querySelector(`.notification-item a[data-id="${notificationId}"]`) || notificationsList.querySelector(`.notification-item div[data-id="${notificationId}"]`));
                        if (itemClicked) {
                            itemClicked.classList.remove('font-semibold', 'bg-sky-50'); 
                            itemClicked.classList.add('text-gray-700');
                        }
                        if (notificationCountBadgeLayout) {
                           let currentCount = parseInt(notificationCountBadgeLayout.textContent || "0");
                           if (currentCount > 0) {
                               updateUnreadCount(currentCount - 1);
                           }
                        }
                    }
                }
            })
            .catch(error => console.error('Layout: Error marking notification as read:', error));
        }

        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault(); 
                e.stopPropagation();
                const formData = new FormData();
                formData.append('mark_all', 'true');

                fetch('ajax_handlers/mark_notification_read.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fetchNotifications(); 
                    }
                })
                .catch(error => console.error('Layout: Error marking all notifications as read:', error));
            });
        }

        if (notificationsToggle && notificationsDropdown) {
            notificationsToggle.addEventListener('click', (e) => {
                e.stopPropagation(); 
                notificationsDropdown.classList.toggle('hidden');
                if (!notificationsDropdown.classList.contains('hidden')) { 
                    fetchNotifications(); 
                }
            });
        }

        document.addEventListener('click', (e) => {
            if (notificationsDropdown && !notificationsDropdown.classList.contains('hidden')) {
                if (!notificationsToggle.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                    notificationsDropdown.classList.add('hidden');
                }
            }
        });

        function startLayoutNotificationPolling() {
            if (notificationPollerLayout) clearInterval(notificationPollerLayout);
            if (notificationsList) { 
                fetchNotifications(); 
                notificationPollerLayout = setInterval(fetchNotifications, NOTIFICATION_POLLING_INTERVAL_LAYOUT);
            }
        }

        function stopLayoutNotificationPolling() {
            if (notificationPollerLayout) clearInterval(notificationPollerLayout);
        }

        startLayoutNotificationPolling();

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopLayoutNotificationPolling();
            } else {
                startLayoutNotificationPolling();
            }
        });

    });
</script>
</body>
</html>