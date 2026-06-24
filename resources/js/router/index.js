import { createRouter, createWebHistory } from 'vue-router';

import AppShell from '../layouts/AppShell.vue';
import ChartsView from '../pages/ChartsView.vue';
import DashboardOverviewView from '../pages/DashboardOverviewView.vue';
import DashboardsView from '../pages/DashboardsView.vue';
import DataGovernanceView from '../pages/DataGovernanceView.vue';
import DataSourcesView from '../pages/DataSourcesView.vue';
import DatasetsView from '../pages/DatasetsView.vue';
import ExportTasksView from '../pages/ExportTasksView.vue';
import ImportTasksView from '../pages/ImportTasksView.vue';
import LoginView from '../pages/LoginView.vue';
import MonitorView from '../pages/MonitorView.vue';
import PermissionsView from '../pages/PermissionsView.vue';
import QueryLogsView from '../pages/QueryLogsView.vue';
import SemanticLayerView from '../pages/SemanticLayerView.vue';
import { useAuthStore } from '../stores/auth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: LoginView,
            meta: { guest: true },
        },
        {
            path: '/',
            component: AppShell,
            meta: { requiresAuth: true },
            children: [
                { path: '', name: 'overview', component: DashboardOverviewView },
                { path: 'data-sources', name: 'data-sources', component: DataSourcesView },
                { path: 'datasets', name: 'datasets', component: DatasetsView },
                { path: 'semantic-layer', name: 'semantic-layer', component: SemanticLayerView },
                { path: 'data-governance', name: 'data-governance', component: DataGovernanceView },
                { path: 'charts', name: 'charts', component: ChartsView },
                { path: 'dashboards', name: 'dashboards', component: DashboardsView },
                { path: 'imports', name: 'imports', component: ImportTasksView },
                { path: 'exports', name: 'exports', component: ExportTasksView },
                { path: 'query-logs', name: 'query-logs', component: QueryLogsView },
                { path: 'permissions', name: 'permissions', component: PermissionsView },
                { path: 'monitor', name: 'monitor', component: MonitorView },
            ],
        },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.initialized) {
        await auth.loadUser();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'overview' };
    }

    return true;
});

window.addEventListener('auth:expired', () => {
    const auth = useAuthStore();
    auth.clear();

    if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login' });
    }
});

export default router;
