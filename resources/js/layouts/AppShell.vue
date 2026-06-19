<script setup>
import { computed, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { LogOut, Menu, RefreshCw, X } from '@lucide/vue';

import { navigationItems } from '../navigation';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const sidebarOpen = ref(false);

const currentPage = computed(() => navigationItems.find((item) => item.path === route.path)?.name ?? '管理端');

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="app-shell">
        <aside :class="['sidebar', { 'sidebar-open': sidebarOpen }]">
            <div class="brand">
                <div class="brand-mark">BI</div>
                <div>
                    <strong>Laravel BI</strong>
                    <span>Management Console</span>
                </div>
                <button class="icon-button sidebar-close" type="button" title="关闭菜单" @click="sidebarOpen = false">
                    <X :size="18" />
                </button>
            </div>

            <nav class="nav-list">
                <RouterLink
                    v-for="item in navigationItems"
                    :key="item.path"
                    :to="item.path"
                    class="nav-item"
                    @click="sidebarOpen = false"
                >
                    <component :is="item.icon" :size="18" />
                    <span>{{ item.name }}</span>
                </RouterLink>
            </nav>
        </aside>

        <div v-if="sidebarOpen" class="sidebar-backdrop" @click="sidebarOpen = false" />

        <main class="workspace">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="icon-button mobile-menu" type="button" title="打开菜单" @click="sidebarOpen = true">
                        <Menu :size="20" />
                    </button>
                    <div>
                        <p class="eyebrow">BI 管理端</p>
                        <h1>{{ currentPage }}</h1>
                    </div>
                </div>
                <div class="topbar-actions">
                    <button class="tool-button" type="button" title="刷新当前页面" @click="router.go(0)">
                        <RefreshCw :size="16" />
                        <span>刷新</span>
                    </button>
                    <div class="user-chip">
                        <span>{{ auth.displayName }}</span>
                    </div>
                    <button class="icon-button" type="button" title="退出登录" @click="logout">
                        <LogOut :size="18" />
                    </button>
                </div>
            </header>

            <section class="page-body">
                <RouterView />
            </section>
        </main>
    </div>
</template>
