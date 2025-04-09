<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

// Set username from database or session
$username = $user['username'] ?? $_SESSION['username'];

// Check if user has a profile picture, otherwise use the UI Avatars API
if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
    $user_avatar = $user['profile_picture'];
} else {
    $user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=e63946&color=fff";
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? "Plānotājs+" ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col">
   
    <!-- Šapka -->
    <header class="bg-white shadow-md p-4 flex justify-between items-center">
        <h1 class="text-xl font-bold text-[#e63946]">Plānotājs+</h1>
        <nav class="flex gap-4">
            <a href="index.php" class="text-gray-700 hover:text-[#e63946]">Galvenā</a>
            <a href="<?= $kanban_url ?>" class="text-gray-700 hover:text-[#e63946]">Kanban</a>
            <a href="calendar.php" class="text-gray-700 hover:text-[#e63946]">Kalendārs</a>
            <a href="project_settings.php" class="text-gray-700 hover:text-[#e63946]">Iestatījumi</a>
        </nav>
        <div class="flex items-center gap-4">
            <a href="profile.php" class="relative group">
                <img src="<?= $user_avatar ?>" class="w-10 h-10 rounded-full border group-hover:opacity-90 transition-opacity" alt="Avatar">
                <div class="absolute opacity-0 group-hover:opacity-100 transition-opacity text-xs bg-black text-white px-2 py-1 rounded -bottom-8 left-1/2 transform -translate-x-1/2 whitespace-nowrap">
                    Rediģēt profilu
                </div>
            </a>
            <span class="font-semibold"><?= $username ?></span>
            <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-700">Iziet</a>
        </div>
    </header>
    <!-- Kontents -->
    <main class="flex-grow container mx-auto p-6">
        <?php include $content; ?>
    </main>
    <!-- Footers -->
    <footer class="bg-gray-200 text-center p-4 text-gray-600">
        &copy; <?= date("Y") ?> Plānotājs+. Visas tiesības aizsargātas.
    </footer>
</body>
</html>