<template>
    <div>
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
