<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
                        <a href="profile.php" class="block mt-2 text-[#e63946] hover:underline">View Profile</a>
                    </div>
                </div>
                <!-- Logout Button -->
                <a href="logout.php" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Logout</a>
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
                <p class="text-lg font-semibold text-[#e63946]">5</p>
                <p class="text-gray-600">Total Boards</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-lg font-semibold text-[#e63946]">12</p>
                <p class="text-gray-600">Tasks Completed</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-lg font-semibold text-[#e63946]">3</p>
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

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                // Example data for user boards
                $boards = [
                    ["Project Alpha", "kanban.php"],
                    ["Marketing Plan", "gantt_chart.php"],
                    ["Personal Goals", "goal_tracker.php"]
                ];
                foreach ($boards as $board) {
                    echo "<a href='{$board[1]}' class='bg-white p-6 rounded-lg shadow-md hover-scale'>
                            <h4 class='text-lg font-semibold text-[#e63946] mb-2'>{$board[0]}</h4>
                            <p class='text-gray-600'>Last updated 2 days ago</p>
                          </a>";
                }
                ?>
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