<script setup>
import { onMounted, reactive, ref } from 'vue';
import { Download, FileDown, Plus, RefreshCw, RotateCcw } from '@lucide/vue';

import DataTable from '../components/DataTable.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { chartApi, dashboardApi, exportTaskApi } from '../services/api';
import { itemsFrom } from '../services/http';

const rows = ref([]);
const charts = ref([]);
const dashboards = ref([]);
const loading = ref(false);
const error = ref('');
const notice = ref('');

const form = reactive({
    export_type: 'csv',
    source_type: 'chart',
    source_id: '',
});

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'export_type', label: '格式' },
    { key: 'source_type', label: '来源' },
    { key: 'source_id', label: '来源 ID' },
    { key: 'status', label: '状态' },
    { key: 'progress', label: '进度' },
    { key: 'file_name', label: '文件' },
    { key: 'created_at', label: '创建时间' },
    { key: 'actions', label: '操作' },
];

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [taskResult, chartResult, dashboardResult] = await Promise.all([
            exportTaskApi.list({ page_size: 100 }),
            chartApi.list({ page_size: 100 }),
            dashboardApi.list({ page_size: 100 }),
        ]);
        rows.value = itemsFrom(taskResult);
        charts.value = itemsFrom(chartResult);
        dashboards.value = itemsFrom(dashboardResult);
    } catch (exception) {
        error.value = exception.message ?? '导出任务加载失败';
    } finally {
        loading.value = false;
    }
}

async function createTask() {
    error.value = '';
    notice.value = '';

    try {
        await exportTaskApi.create({
            export_type: form.export_type,
            source_type: form.source_type,
            source_id: Number(form.source_id),
        });
        notice.value = '导出任务已创建';
        await load();
    } catch (exception) {
        error.value = exception.message ?? '导出任务创建失败';
    }
}

async function retry(row) {
    await exportTaskApi.retry(row.id);
    await load();
}

async function download(row) {
    const token = localStorage.getItem('bi_token');
    const response = await fetch(`/api/export-tasks/${row.id}/download`, {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/octet-stream',
        },
    });

    if (!response.ok) {
        error.value = '文件下载失败';
        return;
    }

    const blob = await response.blob();
    const disposition = response.headers.get('Content-Disposition') ?? '';
    const matched = disposition.match(/filename="?([^"]+)"?/);
    const fileName = matched?.[1] ?? row.file_name ?? `export-${row.id}`;
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    link.click();
    URL.revokeObjectURL(url);
}

function syncExportType() {
    form.export_type = form.source_type === 'dashboard' ? 'pdf' : 'csv';
    form.source_id = '';
}

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <PageHeader title="导出任务" subtitle="创建图表 CSV/XLSX 导出或仪表盘 PDF 导出，并跟踪异步任务进度。">
            <button class="tool-button" type="button" @click="load">
                <RefreshCw :size="16" />
                <span>刷新</span>
            </button>
        </PageHeader>

        <p v-if="error" class="form-error">{{ error }}</p>
        <p v-if="notice" class="form-notice">{{ notice }}</p>

        <section class="surface">
            <div class="section-heading">
                <h3>新建导出</h3>
                <span class="muted">图表支持 CSV/XLSX，仪表盘支持 PDF</span>
            </div>
            <form class="inline-form" @submit.prevent="createTask">
                <select v-model="form.source_type" @change="syncExportType">
                    <option value="chart">chart</option>
                    <option value="dashboard">dashboard</option>
                </select>
                <select v-model="form.export_type">
                    <option v-if="form.source_type === 'chart'" value="csv">csv</option>
                    <option v-if="form.source_type === 'chart'" value="xlsx">xlsx</option>
                    <option v-if="form.source_type === 'dashboard'" value="pdf">pdf</option>
                </select>
                <select v-model="form.source_id" required>
                    <option value="">选择来源</option>
                    <option v-for="chart in charts" v-if="form.source_type === 'chart'" :key="chart.id" :value="chart.id">{{ chart.name }}</option>
                    <option v-for="dashboard in dashboards" v-if="form.source_type === 'dashboard'" :key="dashboard.id" :value="dashboard.id">{{ dashboard.name }}</option>
                </select>
                <button class="primary-button" type="submit">
                    <Plus :size="16" />
                    <span>创建任务</span>
                </button>
            </form>
        </section>

        <section class="surface">
            <div class="section-heading">
                <h3>任务列表</h3>
                <span class="muted">{{ rows.length }} 个</span>
            </div>
            <DataTable :columns="columns" :rows="rows" :loading="loading">
                <template #cell-status="{ value }"><StatusBadge :value="value" /></template>
                <template #cell-progress="{ value }">
                    <div class="progress">
                        <span :style="{ width: `${Number(value ?? 0)}%` }" />
                    </div>
                    <small>{{ value ?? 0 }}%</small>
                </template>
                <template #cell-actions="{ row }">
                    <div class="row-actions">
                        <button class="icon-button" type="button" title="重试" @click="retry(row)"><RotateCcw :size="16" /></button>
                        <button class="icon-button" type="button" title="下载" @click="download(row)"><Download :size="16" /></button>
                        <FileDown :size="16" class="muted-icon" />
                    </div>
                </template>
            </DataTable>
        </section>
    </div>
</template>
