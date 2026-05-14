<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import { apiDelete, apiGet, apiPost, apiPut, errorMessage } from '../../api';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useI18n } from '../../i18n';

const { t } = useI18n();

const metrics = ref([]);
const categories = ref([]);
const frequencies = ref([]);
const meta = ref({ total: 0, current_page: 1, per_page: 10 });
const loading = ref(false);
const notice = ref('');
const error = ref('');
const editingMetric = ref(null);

const filters = reactive({
    keyword: '',
    status: '',
    category_id: '',
    frequency_code: '',
    sort: 'id',
    direction: 'desc',
});

const form = reactive({
    metric_category_id: '',
    name: '',
    code: '',
    unit: '',
    status: 'active',
    description: '',
});

function resetForm() {
    editingMetric.value = null;
    form.metric_category_id = categories.value[0]?.id ? String(categories.value[0].id) : '';
    form.name = '';
    form.code = '';
    form.unit = '';
    form.status = 'active';
    form.description = '';
}

function editMetric(metric) {
    editingMetric.value = metric;
    form.metric_category_id = metric.category?.id ? String(metric.category.id) : '';
    form.name = metric.name;
    form.code = metric.code;
    form.unit = metric.unit ?? '';
    form.status = metric.status;
    form.description = metric.description ?? '';
}

function metricPayload() {
    return {
        metric_category_id: Number(form.metric_category_id),
        name: form.name,
        code: form.code,
        unit: form.unit,
        status: form.status,
        description: form.description,
    };
}

async function loadMetrics(page = 1) {
    loading.value = true;
    error.value = '';

    try {
        const payload = await apiGet('/api/v1/metrics', {
            ...filters,
            page,
            per_page: meta.value.per_page,
        });

        metrics.value = payload.data;
        meta.value = payload.meta;
    } catch (requestError) {
        error.value = errorMessage(requestError);
    } finally {
        loading.value = false;
    }
}

async function loadDimensions() {
    const [categoryPayload, frequencyPayload] = await Promise.all([
        apiGet('/api/v1/metric-categories'),
        apiGet('/api/v1/dimensions/frequencies'),
    ]);

    categories.value = categoryPayload.data;
    frequencies.value = frequencyPayload.data;
    resetForm();
}

async function saveMetric() {
    error.value = '';
    notice.value = '';

    try {
        if (editingMetric.value) {
            await apiPut(`/api/v1/metrics/${editingMetric.value.id}`, metricPayload());
        } else {
            await apiPost('/api/v1/metrics', metricPayload());
        }

        notice.value = t('message.saved');
        resetForm();
        await loadMetrics(meta.value.current_page);
    } catch (requestError) {
        error.value = errorMessage(requestError);
    }
}

async function deleteMetric(metric) {
    if (! window.confirm(t('message.confirm_delete'))) {
        return;
    }

    error.value = '';
    notice.value = '';

    try {
        await apiDelete(`/api/v1/metrics/${metric.id}`);
        notice.value = t('message.deleted');
        await loadMetrics(meta.value.current_page);
    } catch (requestError) {
        error.value = errorMessage(requestError);
    }
}

onMounted(async () => {
    await loadDimensions();
    await loadMetrics();
});
</script>

<template>
    <Head :title="t('page.metrics')" />

    <AdminLayout>
        <div class="grid gap-4 xl:grid-cols-[1fr_380px]">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">{{ t('page.metrics') }}</h2>
                            <p class="mt-1 text-xs text-slate-500">{{ t('message.total_count', { total: meta.total ?? 0 }) }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <input v-model="filters.keyword" class="w-44 rounded-md border border-slate-300 px-3 py-2 text-sm" :placeholder="t('field.keyword')">
                            <select v-model="filters.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">{{ t('field.status') }}</option>
                                <option value="active">{{ t('status.active') }}</option>
                                <option value="disabled">{{ t('status.disabled') }}</option>
                            </select>
                            <select v-model="filters.category_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">{{ t('field.category') }}</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                            <select v-model="filters.frequency_code" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">{{ t('field.frequency') }}</option>
                                <option v-for="frequency in frequencies" :key="frequency.id" :value="frequency.code">{{ frequency.name }}</option>
                            </select>
                            <button class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white" @click="loadMetrics(1)">
                                {{ t('action.search') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="notice" class="border-b border-emerald-100 bg-emerald-50 px-5 py-3 text-sm text-emerald-700">{{ notice }}</div>
                <div v-if="error" class="border-b border-red-100 bg-red-50 px-5 py-3 text-sm text-red-700">{{ error }}</div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.name') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.category') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.latest_value') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.status') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">{{ t('table.action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="loading">
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('message.loading') }}</td>
                            </tr>
                            <tr v-else-if="metrics.length === 0">
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('empty.no_rows') }}</td>
                            </tr>
                            <tr v-for="metric in metrics" v-else :key="metric.id">
                                <td class="px-5 py-4 text-sm">
                                    <div class="font-medium text-slate-950">{{ metric.name }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ metric.code }}</div>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ metric.category?.name ?? '-' }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">
                                    <span v-if="metric.latest_value">{{ metric.latest_value.value }} {{ metric.unit ?? '' }}</span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ t(`status.${metric.status}`) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm">
                                    <button class="font-medium text-blue-700 hover:text-blue-800" @click="editMetric(metric)">{{ t('action.edit') }}</button>
                                    <button class="ml-3 font-medium text-red-700 hover:text-red-800" @click="deleteMetric(metric)">{{ t('action.delete') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3 text-sm text-slate-600">
                    <button
                        class="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-50"
                        :disabled="(meta.current_page ?? 1) <= 1"
                        @click="loadMetrics((meta.current_page ?? 1) - 1)"
                    >
                        ‹
                    </button>
                    <span>{{ meta.current_page ?? 1 }} / {{ Math.max(1, Math.ceil((meta.total ?? 0) / (meta.per_page ?? 10))) }}</span>
                    <button
                        class="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-50"
                        :disabled="(meta.current_page ?? 1) >= Math.ceil((meta.total ?? 0) / (meta.per_page ?? 10))"
                        @click="loadMetrics((meta.current_page ?? 1) + 1)"
                    >
                        ›
                    </button>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-950">{{ editingMetric ? t('form.edit_metric') : t('form.new_metric') }}</h3>
                <div class="mt-4 grid gap-3">
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.category') }}</span>
                        <select v-model="form.metric_category_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.name') }}</span>
                        <input v-model="form.name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.code') }}</span>
                        <input v-model="form.code" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.unit') }}</span>
                        <input v-model="form.unit" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.status') }}</span>
                        <select v-model="form.status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            <option value="active">{{ t('status.active') }}</option>
                            <option value="disabled">{{ t('status.disabled') }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.description') }}</span>
                        <textarea v-model="form.description" class="mt-1 min-h-24 w-full rounded-md border border-slate-300 px-3 py-2"></textarea>
                    </label>
                </div>
                <div class="mt-5 flex gap-2">
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="saveMetric">
                        {{ editingMetric ? t('action.update') : t('action.create') }}
                    </button>
                    <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="resetForm">
                        {{ t('action.cancel') }}
                    </button>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
