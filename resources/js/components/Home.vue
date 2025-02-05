<template>
    <div class="min-h-screen bg-gradient-to-br from-red-500 to-gray-800 text-white">
      <!-- 🌟 Navigation Bar -->
      <nav class="flex justify-between items-center px-8 py-4 bg-gray-900 bg-opacity-80 shadow-lg">
        <h1 class="text-2xl font-extrabold tracking-wide">Plānotājs+</h1>
        <div class="space-x-6">
          <router-link to="/" class="nav-link">Sākums</router-link>
          <router-link to="/login" class="nav-link">Pieteikties</router-link>
          <router-link to="/register" class="nav-link">Reģistrēties</router-link>
          <router-link to="/kanban" class="bg-white text-red-500 font-semibold px-4 py-2 rounded-lg shadow-md hover:bg-gray-200 transition">
            🚀 Kanban panelis
          </router-link>
        </div>
      </nav>
  
      <!-- 🌟 Hero Section -->
      <div class="flex flex-col items-center justify-center text-center h-[80vh] px-4">
        <h1 class="text-5xl font-extrabold mb-4 drop-shadow-lg">Laipni lūgti Plānotājs+</h1>
        <p class="text-lg text-gray-200 max-w-xl mb-6">
          Labākais projekta pārvaldības rīks, lai organizētu savu darbu un palielinātu produktivitāti!
        </p>
        <router-link 
          to="/kanban"
          class="bg-white text-red-500 font-semibold px-6 py-3 rounded-lg shadow-lg hover:bg-gray-200 transition duration-300">
          🚀 Dodieties uz Kanban paneli
        </router-link>
      </div>
    </div>
  </template>
  
  <script>
  export default {
    name: "Home",
  };
  </script>
  
  
  