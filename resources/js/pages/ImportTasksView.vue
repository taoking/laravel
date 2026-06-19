<script setup>
import { onMounted, ref } from 'vue';
import { FileUp, RefreshCw, RotateCcw, Trash2, Upload } from '@lucide/vue';

import DataTable from '../components/DataTable.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { importTaskApi } from '../services/api';
import { itemsFrom } from '../services/http';

const rows = ref([]);
const detail = ref(null);
const file = ref(null);
const loading = ref(false);
const uploading = ref(false);
const error = ref('');
const notice = ref('');

const columns = [
    { key: 'id', label: 'ID' },
    { key: 'file_name', label: '文件' },
    { key: 'file_type', label: '类型' },
    { key: 'status', label: '状态' },
    { key: 'progress', label: '进度' },
    { key: 'total_rows', label: '总行数' },
    { key: 'success_rows', label: '成功' },
    { key: 'failed_rows', label: '失败' },
    { key: 'created_at', label: '创建时间' },
    { key: 'actions', label: '操作' },
];

const logColumns = [
    { key: 'row_number', label: '行号' },
    { key: 'status', label: '状态' },
    { key: 'message', label: '消息' },
    { key: 'created_at', label: '时间' },
];

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const result = await importTaskApi.list({ page_size: 100 });
        rows.value = itemsFrom(result);
    } catch (exception) {
        error.value = exception.message ?? '导入任务加载失败';
    } finally {
        loading.value = false;
    }
}

async function uploadFile() {
    if (!file.value) {
        error.value = '请选择 CSV、TXT 或 XLSX 文件';
        return;
    }

    uploading.value = true;
    error.value = '';
    notice.value = '';

    try {
        await importTaskApi.upload(file.value);
        notice.value = '文件已提交导入任务';
        file.value = null;
        await load();
    } catch (exception) {
        error.value = exception.message ?? '上传失败';
    } finally {
        uploading.value = false;
    }
}

async function retry(row) {
    await importTaskApi.retry(row.id);
    await load();
}

async function remove(row) {
    if (!window.confirm(`确认删除导入任务 ${row.id}？`)) {
        return;
    }

    await importTaskApi.remove(row.id);
    if (detail.value?.id === row.id) {
        detail.value = null;
    }
    await load();
}

async function show(row) {
    try {
        const result = await importTaskApi.show(row.id);
        detail.value = result.data;
    } catch (exception) {
        error.value = exception.message ?? '任务详情加载失败';
    }
}

function handleFile(event) {
    file.value = event.target.files?.[0] ?? null;
}

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <PageHeader title="导入任务" subtitle="上传 CSV、TXT、XLSX 文件并跟踪异步导入、建表和数据集生成进度。">
            <button class="tool-button" type="button" @click="load">
                <RefreshCw :size="16" />
                <span>刷新</span>
            </button>
        </PageHeader>

        <p v-if="error" class="form-error">{{ error }}</p>
        <p v-if="notice" class="form-notice">{{ notice }}</p>

        <section class="surface">
            <div class="section-heading">
                <h3>上传文件</h3>
                <span class="muted">最大 50 MB</span>
            </div>
            <form class="inline-form" @submit.prevent="uploadFile">
                <label class="file-picker">
                    <FileUp :size="18" />
                    <span>{{ file?.name ?? '选择文件' }}</span>
                    <input type="file" accept=".csv,.txt,.xlsx" @change="handleFile">
                </label>
                <button class="primary-button" type="submit" :disabled="uploading">
                    <Upload :size="16" />
                    <span>{{ uploading ? '提交中...' : '提交导入' }}</span>
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
                        <button class="icon-button" type="button" title="详情" @click="show(row)"><FileUp :size="16" /></button>
                        <button class="icon-button" type="button" title="重试" @click="retry(row)"><RotateCcw :size="16" /></button>
                        <button class="icon-button danger" type="button" title="删除" @click="remove(row)"><Trash2 :size="16" /></button>
                    </div>
                </template>
            </DataTable>
        </section>

        <section class="surface">
            <div class="section-heading">
                <h3>任务详情</h3>
                <span class="muted">{{ detail ? `#${detail.id}` : '未选择任务' }}</span>
            </div>
            <div v-if="detail" class="detail-grid">
                <div><span>文件</span><strong>{{ detail.file_name }}</strong></div>
                <div><span>状态</span><StatusBadge :value="detail.status" /></div>
                <div><span>进度</span><strong>{{ detail.progress }}%</strong></div>
                <div><span>错误</span><strong>{{ detail.error_message || '-' }}</strong></div>
            </div>
            <DataTable :columns="logColumns" :rows="detail?.logs ?? []">
                <template #cell-status="{ value }"><StatusBadge :value="value" /></template>
            </DataTable>
        </section>
    </div>
</template>
