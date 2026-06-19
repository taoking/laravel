<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Eye, Plus, RefreshCw, Save, Table2, Trash2 } from '@lucide/vue';

import DataTable from '../components/DataTable.vue';
import JsonTextarea from '../components/JsonTextarea.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { dataSourceApi, datasetApi } from '../services/api';
import { itemsFrom } from '../services/http';

const datasets = ref([]);
const dataSources = ref([]);
const fields = ref([]);
const preview = ref({ columns: [], rows: [] });
const selected = ref(null);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const jsonError = ref('');

const form = reactive({
    id: null,
    name: '',
    description: '',
    data_source_id: '',
    dataset_type: 'single_table',
    main_table: '',
    table_alias: '',
    config_json: {},
    status: 'active',
});

const columns = [
    { key: 'name', label: '名称' },
    { key: 'data_source_id', label: '数据源' },
    { key: 'main_table', label: '主表' },
    { key: 'dataset_type', label: '类型' },
    { key: 'status', label: '状态' },
    { key: 'fields_count', label: '字段数' },
    { key: 'updated_at', label: '更新时间' },
    { key: 'actions', label: '操作' },
];

const fieldColumns = [
    { key: 'field_name', label: '字段' },
    { key: 'display_name', label: '展示名' },
    { key: 'normalized_type', label: '类型' },
    { key: 'semantic_type', label: '语义' },
    { key: 'default_aggregate', label: '聚合' },
    { key: 'flags', label: '属性' },
    { key: 'actions', label: '操作' },
];

const previewColumns = computed(() => {
    const columnsFromApi = preview.value.columns ?? [];

    if (columnsFromApi.length > 0) {
        return columnsFromApi.map((column) => ({
            key: column.field ?? column.name ?? column.key,
            label: column.label ?? column.display_name ?? column.field ?? column.name ?? column.key,
        }));
    }

    return Object.keys(preview.value.rows?.[0] ?? {}).map((key) => ({ key, label: key }));
});

function resetForm() {
    Object.assign(form, {
        id: null,
        name: '',
        description: '',
        data_source_id: '',
        dataset_type: 'single_table',
        main_table: '',
        table_alias: '',
        config_json: {},
        status: 'active',
    });
}

function edit(row) {
    selected.value = row;
    Object.assign(form, {
        id: row.id,
        name: row.name,
        description: row.description ?? '',
        data_source_id: row.data_source_id,
        dataset_type: row.dataset_type ?? 'single_table',
        main_table: row.main_table,
        table_alias: row.table_alias ?? '',
        config_json: row.config_json ?? {},
        status: row.status ?? 'active',
    });
    loadFields(row);
}

function payload() {
    return {
        name: form.name,
        description: form.description || null,
        data_source_id: Number(form.data_source_id),
        dataset_type: form.dataset_type,
        main_table: form.main_table,
        table_alias: form.table_alias || null,
        config_json: form.config_json,
        status: form.status,
    };
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [datasetResult, dataSourceResult] = await Promise.all([
            datasetApi.list({ page_size: 100 }),
            dataSourceApi.list({ page_size: 100 }),
        ]);
        datasets.value = itemsFrom(datasetResult);
        dataSources.value = itemsFrom(dataSourceResult);
    } catch (exception) {
        error.value = exception.message ?? '数据集加载失败';
    } finally {
        loading.value = false;
    }
}

async function save() {
    error.value = '';
    notice.value = '';

    try {
        if (form.id) {
            await datasetApi.update(form.id, payload());
            notice.value = '数据集已更新';
        } else {
            await datasetApi.create(payload());
            notice.value = '数据集已创建';
        }
        resetForm();
        await load();
    } catch (exception) {
        error.value = exception.message ?? '保存失败';
    }
}

async function remove(row) {
    if (!window.confirm(`确认删除数据集 ${row.name}？`)) {
        return;
    }

    await datasetApi.remove(row.id);
    if (selected.value?.id === row.id) {
        selected.value = null;
        fields.value = [];
        preview.value = { columns: [], rows: [] };
    }
    await load();
}

async function syncFields(row) {
    error.value = '';
    notice.value = '';

    try {
        await datasetApi.syncFields(row.id);
        notice.value = '字段同步完成';
        await loadFields(row);
        await load();
    } catch (exception) {
        error.value = exception.message ?? '字段同步失败';
    }
}

async function loadFields(row) {
    selected.value = row;

    try {
        const result = await datasetApi.fields(row.id);
        fields.value = result.data ?? [];
    } catch (exception) {
        error.value = exception.message ?? '字段加载失败';
    }
}

async function updateField(field) {
    if (!selected.value) return;

    try {
        await datasetApi.updateField(selected.value.id, field.id, {
            display_name: field.display_name,
            field_alias: field.field_alias,
            normalized_type: field.normalized_type,
            semantic_type: field.semantic_type,
            is_dimension: Boolean(field.is_dimension),
            is_metric: Boolean(field.is_metric),
            is_visible: Boolean(field.is_visible),
            is_filterable: Boolean(field.is_filterable),
            default_aggregate: field.default_aggregate,
            sort_order: Number(field.sort_order ?? 0),
        });
        notice.value = '字段配置已保存';
        await loadFields(selected.value);
    } catch (exception) {
        error.value = exception.message ?? '字段保存失败';
    }
}

async function previewDataset(row = selected.value) {
    if (!row) return;
    selected.value = row;

    try {
        const result = await datasetApi.preview(row.id, { limit: 20 });
        preview.value = result.data ?? { columns: [], rows: [] };
    } catch (exception) {
        error.value = exception.message ?? '预览失败';
    }
}

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <PageHeader title="数据集管理" subtitle="维护数据集主表、字段语义、维度指标和预览结果。">
            <button class="tool-button" type="button" @click="load">
                <RefreshCw :size="16" />
                <span>刷新</span>
            </button>
            <button class="primary-button" type="button" @click="resetForm">
                <Plus :size="16" />
                <span>新增</span>
            </button>
        </PageHeader>

        <p v-if="error" class="form-error">{{ error }}</p>
        <p v-if="notice" class="form-notice">{{ notice }}</p>

        <section class="work-grid">
            <form class="surface form-panel" @submit.prevent="save">
                <div class="section-heading">
                    <h3>{{ form.id ? '编辑数据集' : '新增数据集' }}</h3>
                    <StatusBadge :value="form.status" />
                </div>
                <div class="form-grid two">
                    <label><span>名称</span><input v-model="form.name" required></label>
                    <label>
                        <span>数据源</span>
                        <select v-model="form.data_source_id" required>
                            <option value="">请选择</option>
                            <option v-for="source in dataSources" :key="source.id" :value="source.id">{{ source.name }}</option>
                        </select>
                    </label>
                    <label><span>主表</span><input v-model="form.main_table" required></label>
                    <label><span>表别名</span><input v-model="form.table_alias" placeholder="可选"></label>
                    <label><span>类型</span><select v-model="form.dataset_type"><option value="single_table">single_table</option></select></label>
                    <label><span>状态</span><select v-model="form.status"><option value="active">active</option><option value="disabled">disabled</option></select></label>
                </div>
                <label class="full-row">
                    <span>描述</span>
                    <textarea v-model="form.description" rows="3" />
                </label>
                <label class="full-row">
                    <span>配置 JSON</span>
                    <JsonTextarea v-model="form.config_json" :rows="5" @invalid="jsonError = $event" />
                    <small v-if="jsonError" class="form-error">{{ jsonError }}</small>
                </label>
                <div class="button-row">
                    <button class="primary-button" type="submit" :disabled="Boolean(jsonError)">
                        <Save :size="16" />
                        <span>保存</span>
                    </button>
                    <button class="tool-button" type="button" @click="resetForm">清空</button>
                </div>
            </form>

            <section class="surface">
                <div class="section-heading">
                    <h3>数据集列表</h3>
                    <span class="muted">{{ datasets.length }} 个</span>
                </div>
                <DataTable :columns="columns" :rows="datasets" :loading="loading">
                    <template #cell-status="{ value }"><StatusBadge :value="value" /></template>
                    <template #cell-actions="{ row }">
                        <div class="row-actions">
                            <button class="icon-button" type="button" title="编辑" @click="edit(row)"><Table2 :size="16" /></button>
                            <button class="icon-button" type="button" title="同步字段" @click="syncFields(row)"><RefreshCw :size="16" /></button>
                            <button class="icon-button" type="button" title="预览" @click="previewDataset(row)"><Eye :size="16" /></button>
                            <button class="icon-button danger" type="button" title="删除" @click="remove(row)"><Trash2 :size="16" /></button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </section>

        <section class="surface">
            <div class="section-heading">
                <h3>字段配置</h3>
                <span class="muted">{{ selected?.name ?? '未选择数据集' }}</span>
            </div>
            <DataTable :columns="fieldColumns" :rows="fields">
                <template #cell-display_name="{ row }">
                    <input v-model="row.display_name" class="table-input">
                </template>
                <template #cell-semantic_type="{ row }">
                    <select v-model="row.semantic_type" class="table-input">
                        <option value="normal">normal</option>
                        <option value="time">time</option>
                        <option value="province">province</option>
                        <option value="city">city</option>
                        <option value="amount">amount</option>
                        <option value="count">count</option>
                        <option value="rate">rate</option>
                        <option value="category">category</option>
                        <option value="id">id</option>
                    </select>
                </template>
                <template #cell-default_aggregate="{ row }">
                    <select v-model="row.default_aggregate" class="table-input">
                        <option value="none">none</option>
                        <option value="sum">sum</option>
                        <option value="avg">avg</option>
                        <option value="count">count</option>
                        <option value="count_distinct">count_distinct</option>
                        <option value="max">max</option>
                        <option value="min">min</option>
                    </select>
                </template>
                <template #cell-flags="{ row }">
                    <div class="flag-row">
                        <label><input v-model="row.is_dimension" type="checkbox"> 维度</label>
                        <label><input v-model="row.is_metric" type="checkbox"> 指标</label>
                        <label><input v-model="row.is_visible" type="checkbox"> 可见</label>
                        <label><input v-model="row.is_filterable" type="checkbox"> 过滤</label>
                    </div>
                </template>
                <template #cell-actions="{ row }">
                    <button class="tool-button compact" type="button" @click="updateField(row)">保存</button>
                </template>
            </DataTable>
        </section>

        <section class="surface">
            <div class="section-heading">
                <h3>数据预览</h3>
                <button class="tool-button compact" type="button" :disabled="!selected" @click="previewDataset()">
                    <Eye :size="14" />
                    <span>预览</span>
                </button>
            </div>
            <DataTable :columns="previewColumns" :rows="preview.rows ?? []" />
        </section>
    </div>
</template>
