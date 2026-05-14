<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import { apiGet, errorMessage } from '../../api';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useI18n } from '../../i18n';

const { t } = useI18n();

const logs = ref([]);
const users = ref([]);
const meta = ref({ total: 0, current_page: 1, per_page: 10 });
const loading = ref(false);
const error = ref('');

const filters = reactive({
    action: '',
    user_id: '',
});

async function loadLogs(page = 1) {
    loading.value = true;
    error.value = '';

    try {
        const payload = await apiGet('/api/v1/audit-logs', {
            ...filters,
            page,
            per_page: meta.value.per_page,
        });

        logs.value = payload.data;
        meta.value = payload.meta;
    } catch (requestError) {
        error.value = errorMessage(requestError);
    } finally {
        loading.value = false;
    }
}

async function loadUsers() {
    const payload = await apiGet('/api/v1/users', { per_page: 100 });

    users.value = payload.data;
}

onMounted(async () => {
    await Promise.all([loadLogs(), loadUsers()]);
});
</script>

<template>
    <Head :title="t('page.audit')" />

    <AdminLayout>
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">{{ t('page.audit') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ t('message.total_count', { total: meta.total ?? 0 }) }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <input v-model="filters.action" class="w-48 rounded-md border border-slate-300 px-3 py-2 text-sm" :placeholder="t('field.action')">
                    <select v-model="filters.user_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">{{ t('field.user_id') }}</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                    <button class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white" @click="loadLogs(1)">
                        {{ t('action.search') }}
                    </button>
                </div>
            </div>
            <div v-if="error" class="border-b border-red-100 bg-red-50 px-5 py-3 text-sm text-red-700">{{ error }}</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.action') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.resource') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.trace') }}</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.time') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading">
                            <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('message.loading') }}</td>
                        </tr>
                        <tr v-else-if="logs.length === 0">
                            <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('empty.no_rows') }}</td>
                        </tr>
                        <tr v-for="log in logs" v-else :key="log.id">
                            <td class="px-5 py-4 text-sm font-medium text-slate-950">{{ log.action }}</td>
                            <td class="px-5 py-4 text-sm text-slate-600">{{ log.resource_type }} #{{ log.resource_id }}</td>
                            <td class="px-5 py-4 text-sm text-slate-600">{{ log.trace_id ?? '-' }}</td>
                            <td class="px-5 py-4 text-sm text-slate-600">{{ log.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3 text-sm text-slate-600">
                <button
                    class="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-50"
                    :disabled="(meta.current_page ?? 1) <= 1"
                    @click="loadLogs((meta.current_page ?? 1) - 1)"
                >
                    ‹
                </button>
                <span>{{ meta.current_page ?? 1 }} / {{ Math.max(1, Math.ceil((meta.total ?? 0) / (meta.per_page ?? 10))) }}</span>
                <button
                    class="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-50"
                    :disabled="(meta.current_page ?? 1) >= Math.ceil((meta.total ?? 0) / (meta.per_page ?? 10))"
                    @click="loadLogs((meta.current_page ?? 1) + 1)"
                >
                    ›
                </button>
            </div>
        </section>
    </AdminLayout>
</template>
