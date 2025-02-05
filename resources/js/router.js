import { createRouter, createWebHistory } from 'vue-router';
import Home from './components/Home.vue';
import KanbanBoard from './components/KanbanBoard.vue';
import Login from './Pages/Auth/Login.vue';
import Register from './Pages/Auth/Register.vue';

const routes = [
    { path: '/', component: Home },
    { path: '/kanban', component: KanbanBoard },
    { path: '/login', component: Login },
    { path: '/register', component: Register }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
