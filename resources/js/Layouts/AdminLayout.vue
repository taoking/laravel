<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '../i18n';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { locale, locales, t } = useI18n();

const navigation = [
    { label: 'nav.dashboard', href: '/admin' },
    { label: 'nav.users', href: '/admin/users' },
    { label: 'nav.roles', href: '/admin/roles' },
    { label: 'nav.metrics', href: '/admin/metrics' },
    { label: 'nav.imports', href: '/admin/imports' },
    { label: 'nav.jobs', href: '#' },
    { label: 'nav.audit', href: '/admin/audit-logs' },
];

const resourceLinks = [
    { label: 'nav.api_docs', href: '/docs/api' },
    { label: 'nav.openapi', href: '/docs/openapi.yaml' },
    { label: 'nav.health', href: '/api/v1/health' },
];

const isActive = (href) => window.location.pathname === href;
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-slate-200 bg-white md:block">
            <div class="flex h-16 items-center border-b border-slate-200 px-6">
                <div>
                    <div class="text-sm font-semibold tracking-wide text-slate-950">{{ t('app.name') }}</div>
                    <div class="text-xs text-slate-500">{{ t('app.subtitle') }}</div>
                </div>
            </div>

            <nav class="space-y-1 px-3 py-4">
                <a
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    class="block rounded-md px-3 py-2 text-sm font-medium"
                    :class="isActive(item.href) ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'"
                >
                    {{ t(item.label) }}
                </a>
            </nav>

            <div class="absolute bottom-0 w-full border-t border-slate-200 p-4">
                <div class="text-xs font-semibold uppercase text-slate-400">{{ t('nav.resources') }}</div>
                <div class="mt-3 space-y-2">
                    <a
                        v-for="link in resourceLinks"
                        :key="link.label"
                        :href="link.href"
                        class="block text-sm text-slate-600 hover:text-blue-700"
                    >
                        {{ t(link.label) }}
                    </a>
                </div>
            </div>
        </aside>

        <div class="md:pl-64">
            <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div>
                        <h1 class="text-base font-semibold text-slate-950">{{ t('app.header') }}</h1>
                        <p class="text-xs text-slate-500">{{ t('app.header.sub') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="sr-only" for="admin-locale">{{ t('locale.label') }}</label>
                        <select
                            id="admin-locale"
                            v-model="locale"
                            class="rounded-md border border-slate-300 bg-white px-2 py-2 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option v-for="item in locales" :key="item.value" :value="item.value">
                                {{ item.label }}
                            </option>
                        </select>
                        <button
                            v-if="user"
                            type="button"
                            @click="logout"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:border-blue-300 hover:text-blue-700"
                        >
                            {{ t('auth.logout') }}
                        </button>
                        <a
                            v-else
                            href="/login"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:border-blue-300 hover:text-blue-700"
                        >
                            {{ t('auth.login') }}
                        </a>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>
