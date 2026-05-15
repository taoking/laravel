<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import { apiGet, apiPost, apiUpload, errorMessage } from '../../api';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useI18n } from '../../i18n';

const { t } = useI18n();

const tasks = ref([]);
const meta = ref({ total: 0, current_page: 1, per_page: 10 });
const loading = ref(false);
const notice = ref('');
const error = ref('');
const selectedFile = ref(null);

const form = reactive({
    idempotency_key: '',
});

async function loadTasks(page = 1) {
    loading.value = true;
    error.value = '';

    try {
        const payload = await apiGet('/api/v1/imports', {
            page,
            per_page: meta.value.per_page,
        });

        tasks.value = payload.data;
        meta.value = payload.meta;
    } catch (requestError) {
        error.value = errorMessage(requestError);
    } finally {
        loading.value = false;
    }
}

async function uploadImport() {
    if (! selectedFile.value) {
        error.value = t('message.select_file');

        return;
    }

    error.value = '';
    notice.value = '';

    const body = new FormData();
    body.append('file', selectedFile.value);

    if (form.idempotency_key) {
        body.append('idempotency_key', form.idempotency_key);
    }

    try {
        await apiUpload('/api/v1/imports', body, form.idempotency_key ? { 'Idempotency-Key': form.idempotency_key } : {});
        notice.value = t('message.uploaded');
        selectedFile.value = null;
        form.idempotency_key = '';
        await loadTasks(1);
    } catch (requestError) {
        error.value = errorMessage(requestError);
    }
}

async function retryTask(task) {
    error.value = '';
    notice.value = '';

    try {
        await apiPost(`/api/v1/imports/${task.id}/retry`);
        notice.value = t('message.retried');
        await loadTasks(meta.value.current_page);
    } catch (requestError) {
        error.value = errorMessage(requestError);
    }
}

function onFileChange(event) {
    selectedFile.value = event.target.files?.[0] ?? null;
}

onMounted(loadTasks);
</script>

<template>
    <Head :title="t('page.imports')" />

    <AdminLayout>
        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">{{ t('page.imports') }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ t('message.total_count', { total: meta.total ?? 0 }) }}</p>
                    </div>
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" @click="loadTasks(meta.current_page)">
                        {{ t('action.refresh') }}
                    </button>
                </div>
                <div v-if="notice" class="border-b border-emerald-100 bg-emerald-50 px-5 py-3 text-sm text-emerald-700">{{ notice }}</div>
                <div v-if="error" class="border-b border-red-100 bg-red-50 px-5 py-3 text-sm text-red-700">{{ error }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.file') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.status') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.attempts') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.rows') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.failed') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">{{ t('table.action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="loading">
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('message.loading') }}</td>
                            </tr>
                            <tr v-else-if="tasks.length === 0">
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('empty.no_rows') }}</td>
                            </tr>
                            <tr v-for="task in tasks" v-else :key="task.id">
                                <td class="px-5 py-4 text-sm">
                                    <div class="font-medium text-slate-950">{{ task.original_name }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ task.idempotency_key }}</div>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-600">
                                    <div>{{ t(`status.${task.status}`) }}</div>
                                    <div v-if="task.failure_type" class="mt-1 text-xs text-red-600">
                                        {{ t(`failure_type.${task.failure_type}`) }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ task.attempts ?? 0 }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ task.success_rows }} / {{ task.total_rows }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ task.failed_rows }}</td>
                                <td class="px-5 py-4 text-right text-sm">
                                    <button class="font-medium text-blue-700 hover:text-blue-800" @click="retryTask(task)">
                                        {{ t('action.retry') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3 text-sm text-slate-600">
                    <button
                        class="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-50"
                        :disabled="(meta.current_page ?? 1) <= 1"
                        @click="loadTasks((meta.current_page ?? 1) - 1)"
                    >
                        ‹
                    </button>
                    <span>{{ meta.current_page ?? 1 }} / {{ Math.max(1, Math.ceil((meta.total ?? 0) / (meta.per_page ?? 10))) }}</span>
                    <button
                        class="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-50"
                        :disabled="(meta.current_page ?? 1) >= Math.ceil((meta.total ?? 0) / (meta.per_page ?? 10))"
                        @click="loadTasks((meta.current_page ?? 1) + 1)"
                    >
                        ›
                    </button>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-950">{{ t('form.import_file') }}</h3>
                <div class="mt-4 grid gap-3">
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.file') }}</span>
                        <input type="file" accept=".csv,.txt,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" @change="onFileChange">
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.idempotency_key') }}</span>
                        <input v-model="form.idempotency_key" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                </div>
                <button class="mt-5 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="uploadImport">
                    {{ t('action.upload') }}
                </button>
            </section>
        </div>
    </AdminLayout>
</template>
