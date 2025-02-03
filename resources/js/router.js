import { createRouter, createWebHistory } from 'vue-router';
import Home from './components/Home.vue';
import KanbanBoard from './components/KanbanBoard.vue';

const routes = [
    { path: '/', component: Home },  // Home.vue is now the default page
    { path: '/kanban', component: KanbanBoard } // Kanban board at /kanban
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
