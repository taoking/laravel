<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../Layouts/AdminLayout.vue';
import { useI18n } from '../i18n';

const { t } = useI18n();

defineProps({
    summary: {
        type: Object,
        required: true,
    },
    stages: {
        type: Array,
        required: true,
    },
});

const modules = [
    { name: 'module.php', scope: 'module.php.scope', phase: 'Phase 1' },
    { name: 'module.laravel', scope: 'module.laravel.scope', phase: 'Phase 1' },
    { name: 'module.auth', scope: 'module.auth.scope', phase: 'Phase 2' },
    { name: 'module.database', scope: 'module.database.scope', phase: 'Phase 3' },
    { name: 'module.queue', scope: 'module.queue.scope', phase: 'Phase 4' },
    { name: 'module.production', scope: 'module.production.scope', phase: 'Phase 5' },
];

const summaryLabel = (key) => t(`summary.${key}`);
const stageArea = (area) => ({
    Scaffold: t('stage.scaffold'),
    'Auth and RBAC': t('stage.access'),
    'Metrics and Import': t('stage.metrics'),
    'Cache and Queue': t('stage.cache_queue'),
}[area] ?? area);
const stageStatus = (status) => t(`status.${status}`);
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AdminLayout>
        <section class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="(value, key) in summary"
                    :key="key"
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="text-sm font-medium text-slate-500">{{ summaryLabel(key) }}</div>
                    <div class="mt-3 text-3xl font-semibold text-slate-950">{{ value }}</div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-950">{{ t('dashboard.modules') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.module') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.scope') }}</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.phase') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="item in modules" :key="item.name">
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-slate-950">{{ t(item.name) }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ t(item.scope) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ item.phase }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-base font-semibold text-slate-950">{{ t('dashboard.stages') }}</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="stage in stages" :key="stage.name" class="px-5 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-slate-950">{{ stage.name }}</div>
                                    <div class="mt-1 text-sm text-slate-500">{{ stageArea(stage.area) }}</div>
                                </div>
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="stage.status === 'in_progress' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ stageStatus(stage.status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </AdminLayout>
</template>
