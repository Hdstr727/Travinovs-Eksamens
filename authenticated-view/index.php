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
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-[#e63946]">Planotajs</h1>
            <div class="flex items-center space-x-4">
                <button id="dark-mode-toggle" class="bg-gray-200 p-2 rounded-full hover:bg-gray-300 transition">
                    🌙
                </button>
                <a href="logout.php" class="bg-[#e63946] text-white py-2 px-4 rounded-lg font-semibold hover:bg-red-700 transition">Logout</a>
            </div>
        </div>

        <!-- Welcome Message -->
        <div class="mb-8">
            <h2 class="text-2xl font-semibold text-gray-700">Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>
            <p class="text-gray-600">Here's what's happening with your boards today.</p>
        </div>

        <!-- Quick Actions -->
        <div class="mb-8 grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <a href="create_board.php" class="bg-white p-6 rounded-lg shadow-md hover-scale flex items-center justify-center text-center">
                <span class="text-[#e63946] font-semibold">+ Create New Board</span>
            </a>
            <a href="templates.php" class="bg-white p-6 rounded-lg shadow-md hover-scale flex items-center justify-center text-center">
                <span class="text-[#e63946] font-semibold">Explore Templates</span>
            </a>
            <a href="team.php" class="bg-white p-6 rounded-lg shadow-md hover-scale flex items-center justify-center text-center">
                <span class="text-[#e63946] font-semibold">Invite Team Members</span>
            </a>
            <a href="upgrade.php" class="bg-[#e63946] text-white p-6 rounded-lg shadow-md hover-scale flex items-center justify-center text-center">
                <span class="font-semibold">Upgrade Plan</span>
            </a>
        </div>

        <!-- Most Popular Templates Section -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Most Popular Templates</h3>
            <p class="text-gray-600 mb-6">Get going faster with a template from the Tinfo community or choose a category.</p>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Template Cards -->
                <div class="bg-white p-6 rounded-lg shadow-md hover-scale">
                    <div class="text-[#e63946] text-2xl mb-4">📋</div>
                    <h4 class="text-lg font-semibold text-[#e63946] mb-2">Project Basic Board</h4>
                    <p class="text-gray-600 mb-4">A simple board to manage your projects.</p>
                    <a href="#" class="text-[#e63946] hover:underline">Use Template</a>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md hover-scale">
                    <div class="text-[#e63946] text-2xl mb-4">⏰</div>
                    <h4 class="text-lg font-semibold text-[#e63946] mb-2">Daily Time Management</h4>
                    <p class="text-gray-600 mb-4">Plan your daily tasks efficiently.</p>
                    <a href="#" class="text-[#e63946] hover:underline">Use Template</a>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md hover-scale">
                    <div class="text-[#e63946] text-2xl mb-4">🗺️</div>
                    <h4 class="text-lg font-semibold text-[#e63946] mb-2">Market Team Map</h4>
                    <p class="text-gray-600 mb-4">Visualize your team's workflow.</p>
                    <a href="#" class="text-[#e63946] hover:underline">Use Template</a>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md hover-scale">
                    <div class="text-[#e63946] text-2xl mb-4">📅</div>
                    <h4 class="text-lg font-semibold text-[#e63946] mb-2">Weekly Planner</h4>
                    <p class="text-gray-600 mb-4">Organize your week with ease.</p>
                    <a href="#" class="text-[#e63946] hover:underline">Use Template</a>
                </div>
            </div>
            <div class="mt-6">
                <a href="#" class="text-[#e63946] hover:underline">Review the full template gallery →</a>
            </div>
        </div>

        <!-- Recently Viewed Section -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Recently Viewed</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-lg shadow-md">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Planetstyle</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Profi</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Basic Board</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Teaching: Weekly Planning</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-600">-</td>
                            <td class="px-6 py-4 text-sm text-gray-600">-</td>
                            <td class="px-6 py-4 text-sm text-gray-600">-</td>
                            <td class="px-6 py-4 text-sm text-gray-600">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Activity Feed -->
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Activity Feed</h3>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="space-y-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">👤</div>
                        <div>
                            <p class="text-sm text-gray-600"><span class="font-semibold">John Doe</span> created a new board: <span class="text-[#e63946]">Project Alpha</span></p>
                            <p class="text-xs text-gray-500">2 hours ago</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">👤</div>
                        <div>
                            <p class="text-sm text-gray-600"><span class="font-semibold">Jane Smith</span> commented on <span class="text-[#e63946]">Task 3</span></p>
                            <p class="text-xs text-gray-500">5 hours ago</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-gray-600 mt-8">
            <p>&copy; 2023 Planotajs. All rights reserved.</p>
            <div class="mt-2">
                <a href="#" class="text-[#e63946] hover:underline">About</a> |
                <a href="#" class="text-[#e63946] hover:underline">Contact</a> |
                <a href="#" class="text-[#e63946] hover:underline">Privacy Policy</a>
            </div>
        </div>
    </div>

    <!-- Dark Mode Toggle Script -->
    <script>
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const body = document.body;

        darkModeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
        });
    </script>
</body>
</html>