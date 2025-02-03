<template>
    <div class="p-6 text-center">
        <h1 class="text-4xl font-bold text-red-500">Welcome to Plānotājs+</h1>
        <p class="text-lg text-gray-600 mt-2">The best project management tool.</p>
        <router-link to="/kanban" class="mt-4 px-6 py-2 bg-red-500 text-white rounded-lg text-lg">Go to Kanban Board</router-link>
    </div>
</template>

<script>
export default {
    name: "Home",
};
</script>

<style scoped>
a {
    display: inline-block;
    margin-top: 10px;
    text-decoration: none;
}
</style>
