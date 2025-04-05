<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get username from session
$username = $_SESSION['username'] ?? "User"; // Fallback to "User" if not set
$user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=e63946&color=fff";

$title = "Profila rediģēšana - Plānotājs+";
?>

<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen flex flex-col">
   
    <!-- Шапка -->
    <header class="bg-white shadow-md p-4 flex justify-between items-center">
        <h1 class="text-xl font-bold text-[#e63946]">Plānotājs+</h1>
        <nav class="flex gap-4">
            <a href="index.php" class="text-gray-700 hover:text-[#e63946]">Galvenā</a>
            <a href="kanban.php" class="text-gray-700 hover:text-[#e63946]">Kanban</a>
            <a href="calendar.php" class="text-gray-700 hover:text-[#e63946]">Kalendārs</a>
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

    <!-- Контент страницы -->
    <main class="flex-grow container mx-auto p-6">
        <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold mb-6 text-center text-[#e63946]">Profila rediģēšana</h2>
            
            <div class="flex justify-center mb-6">
                <div class="relative group">
                    <img src="<?= $user_avatar ?>" class="w-24 h-24 rounded-full border-4 border-[#e63946]" alt="Avatar">
                </div>
            </div>
            
            <form class="space-y-6">
                <div class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Lietotājvārds</label>
                        <input type="text" id="username" name="username" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border" value="<?= $username ?>">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">E-pasts</label>
                        <input type="email" id="email" name="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    
                    <hr class="my-6">
                    
                    <h3 class="text-lg font-medium">Mainīt paroli</h3>
                    
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700">Pašreizējā parole</label>
                        <input type="password" id="current_password" name="current_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700">Jaunā parole</label>
                        <input type="password" id="new_password" name="new_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Apstiprināt jauno paroli</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-[#e63946] text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        Saglabāt izmaiņas
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Футер -->
    <footer class="bg-gray-200 text-center p-4 text-gray-600">
        &copy; <?= date("Y") ?> Plānotājs+. Visas tiesības aizsargātas.
    </footer>
</body>
</html>