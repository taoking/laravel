<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { BarChart3, Eye, Plus, RefreshCw, Save, Trash2 } from '@lucide/vue';

import ChartRenderer from '../components/ChartRenderer.vue';
import DataTable from '../components/DataTable.vue';
import JsonTextarea from '../components/JsonTextarea.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { chartApi, datasetApi, semanticApi } from '../services/api';
import { itemsFrom } from '../services/http';

const charts = ref([]);
const datasets = ref([]);
const fields = ref([]);
const semanticMetrics = ref([]);
const semanticDimensions = ref([]);
const previewResult = ref({ columns: [], rows: [] });
const loading = ref(false);
const previewing = ref(false);
const error = ref('');
const notice = ref('');
const configError = ref('');
const styleError = ref('');

const quick = reactive({
    dimension: '',
    metric: '',
    aggregate: 'sum',
    semanticDimension: '',
    semanticMetric: '',
});

const form = reactive({
    id: null,
    name: '',
    description: '',
    dataset_id: '',
    chart_type: 'bar',
    config_json: {
        dimensions: [],
        metrics: [],
        filters: [],
        sorts: [],
        limit: 100,
        use_cache: true,
    },
    style_json: { title: '' },
    status: 'active',
});

const columns = [
    { key: 'name', label: '名称' },
    { key: 'chart_type', label: '类型' },
    { key: 'dataset_id', label: '数据集' },
    { key: 'status', label: '状态' },
    { key: 'updated_at', label: '更新时间' },
    { key: 'actions', label: '操作' },
];

const fieldOptions = computed(() => fields.value.map((field) => ({
    value: field.field_alias || field.field_name,
    label: `${field.display_name || field.field_name} (${field.field_name})`,
    isDimension: field.is_dimension,
    isMetric: field.is_metric,
    aggregate: field.default_aggregate === 'none' ? 'sum' : (field.default_aggregate || 'sum'),
})));

const dimensionOptions = computed(() => fieldOptions.value.filter((field) => field.isDimension));
const metricOptions = computed(() => fieldOptions.value.filter((field) => field.isMetric));
const semanticDimensionOptions = computed(() => semanticDimensions.value.filter((dimension) => dimension.status === 'active'));
const semanticMetricOptions = computed(() => semanticMetrics.value.filter((metric) => ['active', 'deprecated'].includes(metric.status)));

function resetForm() {
    Object.assign(form, {
        id: null,
        name: '',
        description: '',
        dataset_id: '',
        chart_type: 'bar',
        config_json: {
            dimensions: [],
            metrics: [],
            filters: [],
            sorts: [],
            limit: 100,
            use_cache: true,
        },
        style_json: { title: '' },
        status: 'active',
    });
    Object.assign(quick, { dimension: '', metric: '', aggregate: 'sum', semanticDimension: '', semanticMetric: '' });
    fields.value = [];
    semanticMetrics.value = [];
    semanticDimensions.value = [];
    previewResult.value = { columns: [], rows: [] };
}

function edit(row) {
    Object.assign(form, {
        id: row.id,
        name: row.name,
        description: row.description ?? '',
        dataset_id: row.dataset_id,
        chart_type: row.chart_type ?? 'bar',
        config_json: row.config_json ?? {},
        style_json: row.style_json ?? {},
        status: row.status ?? 'active',
    });
    loadFields(row.dataset_id);
}

function applyQuickConfig() {
    const metricAlias = quick.metric ? `${quick.metric}_${quick.aggregate}` : '';

    form.config_json = {
        ...form.config_json,
        dimensions: quick.dimension ? [{ field: quick.dimension }] : [],
        metrics: quick.metric ? [{ field: quick.metric, aggregate: quick.aggregate, alias: metricAlias }] : [],
        limit: form.config_json.limit ?? 100,
        use_cache: form.config_json.use_cache ?? true,
    };
}

function applySemanticConfig() {
    form.config_json = {
        ...form.config_json,
        dimensions: [],
        metrics: [],
        semantic_dimensions: quick.semanticDimension ? [{ dimension_code: quick.semanticDimension }] : [],
        semantic_metrics: quick.semanticMetric ? [{ metric_code: quick.semanticMetric }] : [],
        limit: form.config_json.limit ?? 100,
        use_cache: form.config_json.use_cache ?? true,
    };
}

function payload() {
    return {
        name: form.name,
        description: form.description || null,
        dataset_id: Number(form.dataset_id),
        chart_type: form.chart_type,
        config_json: form.config_json,
        style_json: form.style_json,
        status: form.status,
    };
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [chartResult, datasetResult] = await Promise.all([
            chartApi.list({ page_size: 100 }),
            datasetApi.list({ page_size: 100 }),
        ]);
        charts.value = itemsFrom(chartResult);
        datasets.value = itemsFrom(datasetResult);
    } catch (exception) {
        error.value = exception.message ?? '图表加载失败';
    } finally {
        loading.value = false;
    }
}

async function loadFields(datasetId) {
    if (!datasetId) {
        fields.value = [];
        semanticMetrics.value = [];
        semanticDimensions.value = [];
        return;
    }

    try {
        const [fieldResult, semanticResult] = await Promise.all([
            datasetApi.fields(datasetId),
            semanticApi.semanticLayer(datasetId).catch(() => ({ data: { metrics: [], dimensions: [] } })),
        ]);
        fields.value = fieldResult.data ?? [];
        semanticMetrics.value = semanticResult.data?.metrics ?? [];
        semanticDimensions.value = semanticResult.data?.dimensions ?? [];
    } catch (exception) {
        error.value = exception.message ?? '字段加载失败';
    }
}

async function save() {
    error.value = '';
    notice.value = '';

    try {
        if (form.id) {
            await chartApi.update(form.id, payload());
            notice.value = '图表已更新';
        } else {
            await chartApi.create(payload());
            notice.value = '图表已创建';
        }
        await load();
    } catch (exception) {
        error.value = exception.message ?? '保存失败';
    }
}

async function remove(row) {
    if (!window.confirm(`确认删除图表 ${row.name}？`)) {
        return;
    }

    await chartApi.remove(row.id);
    await load();
}

async function preview() {
    previewing.value = true;
    error.value = '';

    try {
        const result = await chartApi.preview({
            dataset_id: Number(form.dataset_id),
            chart_type: form.chart_type,
            config_json: form.config_json,
            style_json: form.style_json,
        });
        previewResult.value = result.data ?? { columns: [], rows: [] };
    } catch (exception) {
        error.value = exception.message ?? '预览失败';
    } finally {
        previewing.value = false;
    }
}

watch(() => form.dataset_id, (datasetId) => loadFields(datasetId), { immediate: false });

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <PageHeader title="图表配置与预览" subtitle="配置图表字段映射、查询条件和样式，并用 ECharts 渲染预览。">
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
                    <h3>{{ form.id ? '编辑图表' : '新增图表' }}</h3>
                    <StatusBadge :value="form.status" />
                </div>
                <div class="form-grid two">
                    <label><span>名称</span><input v-model="form.name" required></label>
                    <label>
                        <span>数据集</span>
                        <select v-model="form.dataset_id" required>
                            <option value="">请选择</option>
                            <option v-for="dataset in datasets" :key="dataset.id" :value="dataset.id">{{ dataset.name }}</option>
                        </select>
                    </label>
                    <label>
                        <span>类型</span>
                        <select v-model="form.chart_type">
                            <option value="metric_card">metric_card</option>
                            <option value="bar">bar</option>
                            <option value="line">line</option>
                            <option value="pie">pie</option>
                            <option value="table">table</option>
                        </select>
                    </label>
                    <label><span>状态</span><select v-model="form.status"><option value="active">active</option><option value="disabled">disabled</option></select></label>
                </div>

                <div class="form-grid three quick-config">
                    <label>
                        <span>维度</span>
                        <select v-model="quick.dimension">
                            <option value="">不设置</option>
                            <option v-for="field in dimensionOptions" :key="field.value" :value="field.value">{{ field.label }}</option>
                        </select>
                    </label>
                    <label>
                        <span>指标</span>
                        <select v-model="quick.metric">
                            <option value="">不设置</option>
                            <option v-for="field in metricOptions" :key="field.value" :value="field.value">{{ field.label }}</option>
                        </select>
                    </label>
                    <label>
                        <span>聚合</span>
                        <select v-model="quick.aggregate">
                            <option value="sum">sum</option>
                            <option value="avg">avg</option>
                            <option value="count">count</option>
                            <option value="max">max</option>
                            <option value="min">min</option>
                        </select>
                    </label>
                    <button class="tool-button" type="button" @click="applyQuickConfig">生成配置</button>
                </div>

                <div class="form-grid three quick-config">
                    <label>
                        <span>语义维度</span>
                        <select v-model="quick.semanticDimension">
                            <option value="">不设置</option>
                            <option v-for="dimension in semanticDimensionOptions" :key="dimension.code" :value="dimension.code">{{ dimension.name }} ({{ dimension.code }})</option>
                        </select>
                    </label>
                    <label>
                        <span>语义指标</span>
                        <select v-model="quick.semanticMetric">
                            <option value="">不设置</option>
                            <option v-for="metric in semanticMetricOptions" :key="metric.code" :value="metric.code">{{ metric.name }} v{{ metric.version }}</option>
                        </select>
                    </label>
                    <button class="tool-button" type="button" @click="applySemanticConfig">生成语义配置</button>
                </div>

                <label class="full-row"><span>描述</span><textarea v-model="form.description" rows="3" /></label>
                <label class="full-row">
                    <span>查询配置 JSON</span>
                    <JsonTextarea v-model="form.config_json" :rows="11" @invalid="configError = $event" />
                    <small v-if="configError" class="form-error">{{ configError }}</small>
                </label>
                <label class="full-row">
                    <span>样式 JSON</span>
                    <JsonTextarea v-model="form.style_json" :rows="5" @invalid="styleError = $event" />
                    <small v-if="styleError" class="form-error">{{ styleError }}</small>
                </label>
                <div class="button-row">
                    <button class="primary-button" type="submit" :disabled="Boolean(configError || styleError)">
                        <Save :size="16" />
                        <span>保存</span>
                    </button>
                    <button class="tool-button" type="button" :disabled="previewing || Boolean(configError || styleError)" @click="preview">
                        <Eye :size="16" />
                        <span>{{ previewing ? '预览中...' : '预览' }}</span>
                    </button>
                </div>
            </form>

            <section class="surface">
                <div class="section-heading">
                    <h3>图表列表</h3>
                    <span class="muted">{{ charts.length }} 个</span>
                </div>
                <DataTable :columns="columns" :rows="charts" :loading="loading">
                    <template #cell-status="{ value }"><StatusBadge :value="value" /></template>
                    <template #cell-actions="{ row }">
                        <div class="row-actions">
                            <button class="icon-button" type="button" title="编辑" @click="edit(row)"><BarChart3 :size="16" /></button>
                            <button class="icon-button" type="button" title="预览" @click="edit(row); preview()"><Eye :size="16" /></button>
                            <button class="icon-button danger" type="button" title="删除" @click="remove(row)"><Trash2 :size="16" /></button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </section>

        <section class="surface preview-panel">
            <div class="section-heading">
                <h3>ECharts 预览</h3>
                <span class="muted">
                    {{ previewResult.meta ? `${previewResult.meta.total} rows, ${previewResult.meta.elapsed_ms} ms` : '未执行预览' }}
                </span>
            </div>
            <ChartRenderer :type="form.chart_type" :data="previewResult" :style-config="form.style_json" />
        </section>
    </div>
</template>
