<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
$user_avatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=e63946&color=fff";
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
    
    <!-- Шапка -->
    <header class="bg-white shadow-md p-4 flex justify-between items-center">
        <h1 class="text-xl font-bold text-[#e63946]">Plānotājs+</h1>
        <nav class="flex gap-4">
            <a href="index.php" class="text-gray-700 hover:text-[#e63946]">Galvenā</a>
            <a href="kanban.php" class="text-gray-700 hover:text-[#e63946]">Kanban</a>
            <a href="calendar.php" class="text-gray-700 hover:text-[#e63946]">Kalendārs</a>
        </nav>
        <div class="flex items-center gap-4">
            <img src="<?= $user_avatar ?>" class="w-10 h-10 rounded-full border" alt="Avatar">
            <span class="font-semibold"><?= $username ?></span>
            <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-700">Iziet</a>
        </div>
    </header>

    <!-- Контент страницы -->
    <main class="flex-grow container mx-auto p-6">
        <?php include $content; ?>
    </main>

    <!-- Футер -->
    <footer class="bg-gray-200 text-center p-4 text-gray-600">
        &copy; <?= date("Y") ?> Plānotājs+. Visas tiesības aizsargātas.
    </footer>

</body>
</html>
