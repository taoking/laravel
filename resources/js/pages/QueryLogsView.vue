<script setup>
import { onMounted, reactive, ref } from 'vue';
import { Search } from '@lucide/vue';

import DataTable from '../components/DataTable.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { auditApi } from '../services/api';
import { itemsFrom, paginationFrom } from '../services/http';

const filters = reactive({
    status: '',
    dataset_id: '',
    chart_id: '',
    dashboard_id: '',
    engine_type: '',
    page_size: 20,
});

const rows = ref([]);
const pagination = ref({ page: 1, page_size: 20, total: 0 });
const loading = ref(false);
const error = ref('');

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'user_id', label: '用户' },
    { key: 'dataset_id', label: '数据集' },
    { key: 'chart_id', label: '图表' },
    { key: 'dashboard_id', label: '仪表盘' },
    { key: 'engine_type', label: '引擎' },
    { key: 'data_source_type', label: '数据源' },
    { key: 'elapsed_ms', label: '耗时 ms' },
    { key: 'row_count', label: '行数' },
    { key: 'cached', label: '缓存' },
    { key: 'is_slow', label: '慢查询' },
    { key: 'status', label: '状态' },
    { key: 'created_at', label: '时间' },
    { key: 'sql', label: 'SQL' },
];

function params() {
    return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== null));
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const result = await auditApi.queryLogs(params());
        rows.value = itemsFrom(result);
        pagination.value = paginationFrom(result);
    } catch (exception) {
        error.value = exception.message ?? '查询日志加载失败';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <PageHeader title="查询日志" subtitle="按数据集、图表、仪表盘和状态筛选 Query Engine 执行记录。">
            <button class="primary-button" type="button" @click="load">
                <Search :size="16" />
                <span>查询</span>
            </button>
        </PageHeader>

        <form class="filter-bar" @submit.prevent="load">
            <input v-model="filters.dataset_id" type="number" min="1" placeholder="数据集 ID">
            <input v-model="filters.chart_id" type="number" min="1" placeholder="图表 ID">
            <input v-model="filters.dashboard_id" type="number" min="1" placeholder="仪表盘 ID">
            <select v-model="filters.engine_type">
                <option value="">全部引擎</option>
                <option value="mysql">mysql</option>
                <option value="starrocks">starrocks</option>
                <option value="doris">doris</option>
                <option value="clickhouse">clickhouse</option>
            </select>
            <select v-model="filters.status">
                <option value="">全部状态</option>
                <option value="success">success</option>
                <option value="failed">failed</option>
            </select>
            <select v-model="filters.page_size">
                <option :value="20">20 条</option>
                <option :value="50">50 条</option>
                <option :value="100">100 条</option>
            </select>
        </form>

        <p v-if="error" class="form-error">{{ error }}</p>

        <section class="surface">
            <div class="section-heading">
                <h3>日志列表</h3>
                <span class="muted">共 {{ pagination.total }} 条</span>
            </div>
            <DataTable :columns="columns" :rows="rows" :loading="loading">
                <template #cell-cached="{ value }">
                    <StatusBadge :value="value ? 'cached' : 'miss'" />
                </template>
                <template #cell-is_slow="{ value }">
                    <StatusBadge :value="value ? 'slow' : 'normal'" />
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :value="value" />
                </template>
                <template #cell-engine_type="{ value }">
                    <StatusBadge :value="value ?? '-'" />
                </template>
                <template #cell-data_source_type="{ value }">
                    <StatusBadge :value="value ?? '-'" />
                </template>
                <template #cell-sql="{ value }">
                    <code class="inline-code">{{ String(value ?? '').slice(0, 140) }}</code>
                </template>
            </DataTable>
        </section>
    </div>
</template>
