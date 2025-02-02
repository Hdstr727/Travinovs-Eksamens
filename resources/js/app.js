import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './components/App.vue';
import Home from './components/Home.vue';

// Define routes
const routes = [
    { path: '/', component: Home },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Create Vue app
createApp(App).use(router).mount('#app');
