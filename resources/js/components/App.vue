<template>
    <div>
        <nav class="p-4 bg-gray-800 text-white flex justify-between">
            <router-link to="/" class="mr-4">Home</router-link>
            <router-link to="/kanban">Kanban Board</router-link>
        </nav>
        <router-view></router-view>
    </div>
</template>

<script>
export default {
    name: "App",
};
</script>

<style scoped>
nav a {
    text-decoration: none;
    color: white;
    font-size: 18px;
}
nav a.router-link-active {
    font-weight: bold;
}
</style>
