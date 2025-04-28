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
$board_count = $board_stmt->get_result()->fetch_assoc()['count'];
$board_stmt->close();

// For now, we'll use placeholder values for tasks and deadlines
// You would implement these when you have the tasks table
$completed_tasks = 0;
$upcoming_deadlines = 0;

// Fetch user information from the session
$username = $user['username'] ?? $_SESSION['username'];

// Check if user has a profile picture, otherwise use the UI Avatars API
if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
    $user_avatar = $user['profile_picture'];
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

// Create empty array for boards
$boards = [];

// Fetch user's own boards from database
$own_boards_sql = "SELECT board_id, board_name, board_type, updated_at, 'owner' as access_type 
                  FROM Planotajs_Boards 
                  WHERE user_id = ? AND is_deleted = 0";
$own_boards_stmt = $connection->prepare($own_boards_sql);
$own_boards_stmt->bind_param("i", $user_id);
$own_boards_stmt->execute();
$own_boards_result = $own_boards_stmt->get_result();

// Add owner's boards to the boards array
while ($board = $own_boards_result->fetch_assoc()) {
    // Determine the correct page based on board type
    switch ($board['board_type']) {
        case 'kanban':
            $page = 'kanban.php';
            break;
        default:
            $page = 'kanban.php'; // Default to kanban
    }
    
    // Calculate how long ago the board was updated
    $updated_time = strtotime($board['updated_at']);
    $time_diff = time() - $updated_time;
    $days_ago = floor($time_diff / (60 * 60 * 24));
    
    if ($days_ago == 0) {
        $last_updated = "Updated today";
    } elseif ($days_ago == 1) {
        $last_updated = "Updated yesterday";
    } else {
        $last_updated = "Updated $days_ago days ago";
    }
    
    // Add board to array with updated info
    $boards[] = [
        'id' => $board['board_id'],
        'name' => $board['board_name'],
        'page' => $page,
        'updated' => $last_updated,
        'access_type' => $board['access_type']
    ];
}
$own_boards_stmt->close();

// Fetch shared boards the user has access to
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

// Add shared boards to the boards array
while ($board = $shared_boards_result->fetch_assoc()) {
    // Determine the correct page based on board type
    switch ($board['board_type']) {
        case 'kanban':
            $page = 'kanban.php';
            break;
        default:
            $page = 'kanban.php'; // Default to kanban
    }
    
    // Calculate how long ago the board was updated
    $updated_time = strtotime($board['updated_at']);
    $time_diff = time() - $updated_time;
    $days_ago = floor($time_diff / (60 * 60 * 24));
    
    if ($days_ago == 0) {
        $last_updated = "Updated today";
    } elseif ($days_ago == 1) {
        $last_updated = "Updated yesterday";
    } else {
        $last_updated = "Updated $days_ago days ago";
    }
    
    // Add board to array with updated info
    $boards[] = [
        'id' => $board['board_id'],
        'name' => $board['board_name'],
        'page' => $page,
        'updated' => $last_updated,
        'access_type' => $board['access_type'],
        'owner_name' => $board['owner_name']
    ];
}
$shared_boards_stmt->close();

// Sort all boards by last updated (most recent first)
usort($boards, function($a, $b) {
    return strcmp($b['updated'], $a['updated']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Planotajs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .hover-scale {
            transition: transform 0.2s ease;
        }
        .hover-scale:hover {
            transform: scale(1.05);
        }
        .dark-mode {
            background-color: #1a202c;
            color: #e2e8f0;
        }
        .dark-mode .card {
            background-color: #2d3748;
            color: #e2e8f0;
        }
        .dark-mode .bg-white {
            background-color: #2d3748;
            color: #e2e8f0;
        }
        .dark-mode #profile-dropdown, 
        .dark-mode #notifications-dropdown, 
        .dark-mode #add-board-modal {
            background-color: #2d3748;
            color: #e2e8f0;
        }
        .badge {
            font-size: 0.65rem;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-[#e63946]">Planotajs</h1>
            <div class="flex items-center space-x-4">
                <!-- Notifications Bell -->
                <div class="relative">
                    <button id="notifications-toggle" class="bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition">
                        🔔
                    </button>
                    <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-md p-4">
                        <p class="text-sm text-gray-600">No new notifications.</p>
                    </div>
                </div>
                <!-- Dark Mode Toggle -->
                <button id="dark-mode-toggle" class="bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition">
                    🌙
                </button>

                <!-- Profile Icon -->
                <div class="relative">
                    <button id="profile-toggle" class="relative">
                        <img src="<?= $user_avatar ?>" class="w-10 h-10 rounded-full border hover:opacity-90 transition-opacity" alt="Avatar">
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
            <h2 class="text-2xl font-semibold text-gray-700"><?php echo $greeting; ?>, <?php echo htmlspecialchars($username); ?>!</h2>
            <p class="text-gray-600">Here's what's happening with your boards today.</p>
        </div>

        <!-- Quick Stats -->
        <div class="mb-8 grid md:grid-cols-3 gap-6">
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-lg font-semibold text-[#e63946]"><?php echo $board_count; ?></p>
                <p class="text-gray-600">Total Boards</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-lg font-semibold text-[#e63946]"><?php echo $completed_tasks; ?></p>
                <p class="text-gray-600">Tasks Completed</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
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
            <!-- Button to open modal -->
            <button id="add-board-btn" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition mb-4">
                Add Board
            </button>
            <?php
            if (count($boards) > 0) {
                echo "<div class='grid md:grid-cols-3 gap-6'>";
                foreach ($boards as $board) {
                    // Set badge color based on access level
                    $badgeColor = "bg-blue-100 text-blue-800"; // Default for shared boards
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
                    
                    echo "<a href='{$board['page']}?board_id={$board['id']}' class='bg-white p-6 rounded-lg shadow-md hover-scale'>
                            <div class='flex justify-between items-start mb-2'>
                                <h4 class='text-lg font-semibold text-[#e63946]'>" . htmlspecialchars($board['name']) . "</h4>
                                <span class='badge $badgeColor'>" . $badgeText . "</span>
                            </div>
                            <p class='text-gray-600'>" . htmlspecialchars($board['updated']) . "</p>
                        </a>";
                }
                echo "</div>";
            } else {
                echo "<div class='col-span-3 text-center p-8 bg-white rounded-lg shadow-md'>
                        <p class='text-gray-600'>You haven't created any boards yet. Click 'Add Board' to get started!</p>
                    </div>";
            }
            ?>
            <!-- Modal -->
            <div id="add-board-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center">
                <div class="bg-white p-6 rounded-lg shadow-lg w-96">
                    <h2 class="text-xl font-semibold mb-4">Create New Board</h2>
                    <input type="text" id="board-name" placeholder="Enter board name..." class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#e63946]">
                    
                    <!-- Template Selection -->
                    <div class="mt-4">
                        <p class="text-gray-600">Select a template:</p>
                        <select id="board-template" class="w-full p-2 border border-gray-300 rounded-lg mt-2 focus:ring-2 focus:ring-[#e63946]">
                            <option value="kanban">Kanban</option>
                            <option value="gantt">Gantt Chart</option>
                            <option value="goal-tracker">Goal Tracker</option>
                        </select>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button id="close-modal" class="mr-2 text-gray-600 hover:text-gray-800">Cancel</button>
                        <button id="create-board" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Create</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Deadlines -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Upcoming Deadlines</h3>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600">Complete Task 3 for <span class="text-[#e63946]">Project Alpha</span></p>
                        <p class="text-xs text-gray-500">Due in 2 days</p>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600">Submit Marketing Plan</p>
                        <p class="text-xs text-gray-500">Due in 5 days</p>
                    </div>
                </div>
            </div>
        </div>
        <?php echo "Debug - Avatar path: " . $user_avatar; ?>
        <!-- Recent Activity Section -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Recent Activity</h3>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="space-y-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center overflow-hidden">
                            <img src="<?= $user_avatar ?>" class="w-full h-full object-cover" alt="User Avatar">
                        </div>
                        <div>
                            <p class="text-sm text-gray-600"><span class="font-semibold"><?= htmlspecialchars($username) ?></span> created a new board: <span class="text-[#e63946]">Project Alpha</span></p>
                            <p class="text-xs text-gray-500">2 hours ago</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center overflow-hidden">
                            <img src="<?= $user_avatar ?>" class="w-full h-full object-cover" alt="User Avatar">
                        </div>
                        <div>
                            <p class="text-sm text-gray-600"><span class="font-semibold"><?= htmlspecialchars($username) ?></span> commented on <span class="text-[#e63946]">Task 3</span>.</p>
                            <p class="text-xs text-gray-500">5 hours ago</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-gray-600 mt-8">
            <p>&copy; <?= date("Y") ?> Planotajs. All rights reserved.</p>
            <div class="mt-2">
                <a href="#" class="text-[#e63946] hover:underline">About</a> |
                <a href="#" class="text-[#e63946] hover:underline">Contact</a> |
                <a href="#" class="text-[#e63946] hover:underline">Privacy Policy</a>
            </div>
        </div>
    </div>

    <!-- Notifications Dropdown Script -->
    <script>
        const notificationsToggle = document.getElementById('notifications-toggle');
        const notificationsDropdown = document.getElementById('notifications-dropdown');

        notificationsToggle.addEventListener('click', () => {
            notificationsDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!notificationsToggle.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                notificationsDropdown.classList.add('hidden');
            }
        });
    </script>

    <!-- Profile Dropdown Script -->
    <script>
        const profileToggle = document.getElementById('profile-toggle');
        const profileDropdown = document.getElementById('profile-dropdown');

        profileToggle.addEventListener('click', () => {
            profileDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });
    </script>

    <!-- Board Modal Script -->
    <script>
        document.getElementById('add-board-btn').addEventListener('click', function() {
            document.getElementById('add-board-modal').classList.remove('hidden');
        });

        document.getElementById('close-modal').addEventListener('click', function() {
            document.getElementById('add-board-modal').classList.add('hidden');
        });

        document.getElementById('create-board').addEventListener('click', function() {
            const boardName = document.getElementById('board-name').value.trim();
            const boardTemplate = document.getElementById('board-template').value;
            
            if (boardName) {
                // Sending the data (board name and template type) to the server
                fetch('create_board.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `board_name=${encodeURIComponent(boardName)}&board_template=${encodeURIComponent(boardTemplate)}`
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        location.reload(); // Reload the page to show the new board
                    } else {
                        alert('Error creating board.');
                    }
                });
            }
        });

        // Close modal when clicking outside
        document.getElementById('add-board-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>

    <!-- Dark Mode Toggle Script -->
    <script>
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const body = document.body;

        // Check for saved dark mode preference
        if (localStorage.getItem('darkMode') === 'true') {
            body.classList.add('dark-mode');
        }

        darkModeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            // Save preference to localStorage
            localStorage.setItem('darkMode', body.classList.contains('dark-mode'));
        });
    </script>
</body>
</html>