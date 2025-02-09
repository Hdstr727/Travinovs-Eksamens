<?php
session_start();

// Optional: Check if the admin is logged in, otherwise redirect to an admin login page.
// if (!isset($_SESSION['admin_logged_in'])) {
//     header("Location: login.php");
//     exit();
// }
?>
<!DOCTYPE html>
<html lang="lv">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Plānotājs+</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Chart.js for charts -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
</head>
<body class="bg-gray-100 text-gray-800">
  <!-- Header (same red theme as homepage) -->
  <header class="bg-[#e63946] text-white py-6 shadow-md w-full">
    <div class="container mx-auto text-center">
      <h1 class="text-4xl font-bold">Plānotājs+ Admin</h1>
    </div>
  </header>

  <!-- Main container for sidebar and content -->
  <div class="container mx-auto mt-8 px-4">
    <div class="flex flex-col md:flex-row gap-6">
      <!-- Sidebar -->
      <aside class="md:w-64 bg-white border border-gray-200 rounded-lg p-6">
        <nav>
          <ul>
            <li class="mb-4">
              <a href="index.php" class="block text-lg font-semibold text-gray-800 hover:text-[#e63946]">Dashboard</a>
            </li>
            <li class="mb-4">
              <a href="users.php" class="block text-lg font-semibold text-gray-800 hover:text-[#e63946]">User Management</a>
            </li>
            <li class="mb-4">
              <a href="tasks.php" class="block text-lg font-semibold text-gray-800 hover:text-[#e63946]">Task Management</a>
            </li>
            <li class="mb-4">
              <a href="reports.php" class="block text-lg font-semibold text-gray-800 hover:text-[#e63946]">Reports</a>
            </li>
            <li class="mb-4">
              <a href="settings.php" class="block text-lg font-semibold text-gray-800 hover:text-[#e63946]">Settings</a>
            </li>
          </ul>
        </nav>
      </aside>

      <!-- Main Content Area -->
      <main class="flex-1 bg-white border border-gray-200 rounded-lg p-6">
        <!-- Dashboard Header -->
        <div class="flex justify-between items-center border-b pb-4 mb-6">
          <h2 class="text-2xl font-bold">Dashboard</h2>
          <a href="../index.php" class="bg-[#e63946] text-white py-2 px-4 rounded hover:bg-red-700 transition">
            Back to Main Site
          </a>
        </div>

        <!-- Dashboard Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
          <!-- Card 1: Total Users -->
          <div class="bg-gray-50 p-4 rounded-lg shadow">
            <h3 class="text-lg font-semibold">Total Users</h3>
            <p class="text-3xl font-bold">150</p>
          </div>
          <!-- Card 2: Active Tasks -->
          <div class="bg-gray-50 p-4 rounded-lg shadow">
            <h3 class="text-lg font-semibold">Active Tasks</h3>
            <p class="text-3xl font-bold">35</p>
          </div>
          <!-- Card 3: Completed Tasks -->
          <div class="bg-gray-50 p-4 rounded-lg shadow">
            <h3 class="text-lg font-semibold">Completed Tasks</h3>
            <p class="text-3xl font-bold">80</p>
          </div>
          <!-- Card 4: Pending Reviews -->
          <div class="bg-gray-50 p-4 rounded-lg shadow">
            <h3 class="text-lg font-semibold">Pending Reviews</h3>
            <p class="text-3xl font-bold">12</p>
          </div>
        </div>

        <!-- Chart Area -->
        <div class="bg-gray-50 p-4 rounded-lg shadow">
          <h2 class="text-xl font-semibold mb-4">User Registrations</h2>
          <!-- The chart container is constrained in width so it does not overflow -->
          <div class="mx-auto" style="max-width: 600px;">
            <canvas id="registrationChart"></canvas>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Chart.js Initialization -->
  <script>
    const ctx = document.getElementById('registrationChart').getContext('2d');
    const registrationChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['January', 'February', 'March', 'April', 'May', 'June'],
        datasets: [{
          label: 'Registrations',
          data: [10, 20, 30, 25, 40, 50],
          backgroundColor: 'rgba(230, 57, 70, 0.2)',
          borderColor: 'rgba(230, 57, 70, 1)',
          borderWidth: 1,
          fill: true
        }]
      },
      options: {
        scales: {
          yAxes: [{
            ticks: {
              beginAtZero: true
            }
          }]
        }
      }
    });
  </script>
</body>
</html>
