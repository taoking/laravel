<script setup>
import { computed, onMounted, ref } from 'vue';
import { Activity, BarChart3, Database, Download, LayoutDashboard, ScrollText, Table2, Upload } from '@lucide/vue';

import DataTable from '../components/DataTable.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { chartApi, dashboardApi, dataSourceApi, datasetApi, importTaskApi, exportTaskApi, auditApi, monitorApi } from '../services/api';
import { itemsFrom } from '../services/http';

const loading = ref(false);
const error = ref('');
const health = ref(null);
const metricsText = ref('');
const dataSources = ref([]);
const datasets = ref([]);
const charts = ref([]);
const dashboards = ref([]);
const imports = ref([]);
const exports = ref([]);
const logs = ref([]);

const stats = computed(() => [
    { label: '数据源', value: dataSources.value.length, icon: Database },
    { label: '数据集', value: datasets.value.length, icon: Table2 },
    { label: '图表', value: charts.value.length, icon: BarChart3 },
    { label: '仪表盘', value: dashboards.value.length, icon: LayoutDashboard },
    { label: '导入任务', value: imports.value.length, icon: Upload },
    { label: '导出任务', value: exports.value.length, icon: Download },
    { label: '查询日志', value: logs.value.length, icon: ScrollText },
    { label: '健康状态', value: health.value?.status ?? '-', icon: Activity, status: true },
]);

const logColumns = [
    { key: 'id', label: 'ID' },
    { key: 'dataset_id', label: '数据集' },
    { key: 'elapsed_ms', label: '耗时 ms' },
    { key: 'row_count', label: '行数' },
    { key: 'cached', label: '缓存' },
    { key: 'status', label: '状态' },
    { key: 'created_at', label: '时间' },
];

async function load() {
    loading.value = true;
    error.value = '';

    const calls = await Promise.allSettled([
        monitorApi.health(),
        monitorApi.metrics(),
        dataSourceApi.list({ page_size: 100 }),
        datasetApi.list({ page_size: 100 }),
        chartApi.list({ page_size: 100 }),
        dashboardApi.list({ page_size: 100 }),
        importTaskApi.list({ page_size: 100 }),
        exportTaskApi.list({ page_size: 100 }),
        auditApi.queryLogs({ page_size: 8 }),
    ]);

    if (calls[0].status === 'fulfilled') health.value = calls[0].value.data;
    if (calls[1].status === 'fulfilled') metricsText.value = String(calls[1].value).slice(0, 1600);
    if (calls[2].status === 'fulfilled') dataSources.value = itemsFrom(calls[2].value);
    if (calls[3].status === 'fulfilled') datasets.value = itemsFrom(calls[3].value);
    if (calls[4].status === 'fulfilled') charts.value = itemsFrom(calls[4].value);
    if (calls[5].status === 'fulfilled') dashboards.value = itemsFrom(calls[5].value);
    if (calls[6].status === 'fulfilled') imports.value = itemsFrom(calls[6].value);
    if (calls[7].status === 'fulfilled') exports.value = itemsFrom(calls[7].value);
    if (calls[8].status === 'fulfilled') logs.value = itemsFrom(calls[8].value);

    const rejected = calls.find((call) => call.status === 'rejected');
    if (rejected) {
        error.value = rejected.reason?.message ?? '部分数据加载失败';
    }

    loading.value = false;
}

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <PageHeader title="首页概览" subtitle="集中查看平台资源数量、健康状态、近期查询和 Prometheus 指标。">
            <button class="tool-button" type="button" @click="load">
                <Activity :size="16" />
                <span>刷新状态</span>
            </button>
        </PageHeader>

        <p v-if="error" class="form-error">{{ error }}</p>

        <section class="stat-grid">
            <article v-for="stat in stats" :key="stat.label" class="stat-card">
                <component :is="stat.icon" :size="22" />
                <div>
                    <span>{{ stat.label }}</span>
                    <strong v-if="stat.status"><StatusBadge :value="stat.value" /></strong>
                    <strong v-else>{{ stat.value }}</strong>
                </div>
            </article>
        </section>

        <section class="split-grid">
            <div class="surface">
                <div class="section-heading">
                    <h3>健康检查</h3>
                    <StatusBadge :value="health?.status ?? 'unknown'" />
                </div>
                <div class="health-list">
                    <div v-for="(check, key) in health?.checks ?? {}" :key="key" class="health-row">
                        <span>{{ key }}</span>
                        <StatusBadge :value="check.status" />
                        <small>{{ check.elapsed_ms }} ms</small>
                    </div>
                    <p v-if="!health" class="muted">{{ loading ? '加载中...' : '暂无健康数据' }}</p>
                </div>
            </div>

            <div class="surface">
                <div class="section-heading">
                    <h3>指标快照</h3>
                    <span class="muted">Prometheus</span>
                </div>
                <pre class="code-block">{{ metricsText || '暂无指标数据' }}</pre>
            </div>
        </section>

        <section class="surface">
            <div class="section-heading">
                <h3>近期查询</h3>
                <RouterLink class="text-link" to="/query-logs">查看全部</RouterLink>
            </div>
            <DataTable :columns="logColumns" :rows="logs" :loading="loading">
                <template #cell-cached="{ value }">
                    <StatusBadge :value="value ? 'cached' : 'miss'" />
                </template>
                <template #cell-status="{ value }">
                    <StatusBadge :value="value" />
                </template>
            </DataTable>
        </section>
    </div>
</template>
