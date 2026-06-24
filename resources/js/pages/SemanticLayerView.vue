<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { CheckCircle2, GitBranch, Plus, RefreshCw, Save, Trash2 } from '@lucide/vue';

import DataTable from '../components/DataTable.vue';
import JsonTextarea from '../components/JsonTextarea.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { datasetApi, semanticApi } from '../services/api';
import { itemsFrom } from '../services/http';

const datasets = ref([]);
const categories = ref([]);
const metrics = ref([]);
const dimensions = ref([]);
const impact = ref(null);
const formulaResult = ref(null);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const grainError = ref('');

const filters = reactive({
    dataset_id: '',
    status: '',
});

const categoryForm = reactive({
    id: null,
    name: '',
    parent_id: '',
    sort_order: 0,
    description: '',
});

const metricForm = reactive({
    id: null,
    dataset_id: '',
    category_id: '',
    name: '',
    code: '',
    metric_type: 'base',
    aggregate_function: 'sum',
    source_field: '',
    formula: '',
    unit: '',
    precision: 2,
    format_type: 'number',
    description: '',
    status: 'draft',
});

const dimensionForm = reactive({
    id: null,
    dataset_id: '',
    name: '',
    code: '',
    field_name: '',
    dimension_type: 'string',
    time_grain_options_json: [],
    description: '',
    status: 'active',
});

const categoryColumns = [
    { key: 'name', label: '分类' },
    { key: 'parent_id', label: '父级' },
    { key: 'sort_order', label: '排序' },
    { key: 'actions', label: '操作' },
];

const metricColumns = [
    { key: 'name', label: '指标' },
    { key: 'code', label: '编码' },
    { key: 'dataset_id', label: '数据集' },
    { key: 'metric_type', label: '类型' },
    { key: 'aggregate_function', label: '聚合' },
    { key: 'status', label: '状态' },
    { key: 'version', label: '版本' },
    { key: 'actions', label: '操作' },
];

const dimensionColumns = [
    { key: 'name', label: '维度' },
    { key: 'code', label: '编码' },
    { key: 'field_name', label: '字段' },
    { key: 'dimension_type', label: '类型' },
    { key: 'status', label: '状态' },
    { key: 'actions', label: '操作' },
];

const selectedDatasetName = computed(() => datasets.value.find((dataset) => dataset.id === Number(filters.dataset_id))?.name ?? '全部数据集');

function params() {
    return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== null));
}

function resetCategory() {
    Object.assign(categoryForm, { id: null, name: '', parent_id: '', sort_order: 0, description: '' });
}

function resetMetric() {
    Object.assign(metricForm, {
        id: null,
        dataset_id: filters.dataset_id || '',
        category_id: '',
        name: '',
        code: '',
        metric_type: 'base',
        aggregate_function: 'sum',
        source_field: '',
        formula: '',
        unit: '',
        precision: 2,
        format_type: 'number',
        description: '',
        status: 'draft',
    });
    formulaResult.value = null;
}

function resetDimension() {
    Object.assign(dimensionForm, {
        id: null,
        dataset_id: filters.dataset_id || '',
        name: '',
        code: '',
        field_name: '',
        dimension_type: 'string',
        time_grain_options_json: [],
        description: '',
        status: 'active',
    });
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [datasetResult, categoryResult, metricResult, dimensionResult] = await Promise.all([
            datasetApi.list({ page_size: 100 }),
            semanticApi.categories.list({ page_size: 100 }),
            semanticApi.metrics.list({ ...params(), page_size: 100 }),
            semanticApi.dimensions.list({ ...params(), page_size: 100 }),
        ]);
        datasets.value = itemsFrom(datasetResult);
        categories.value = itemsFrom(categoryResult);
        metrics.value = itemsFrom(metricResult);
        dimensions.value = itemsFrom(dimensionResult);
    } catch (exception) {
        error.value = exception.message ?? '语义层加载失败';
    } finally {
        loading.value = false;
    }
}

async function saveCategory() {
    saving.value = true;
    error.value = '';

    try {
        const payload = {
            name: categoryForm.name,
            parent_id: categoryForm.parent_id ? Number(categoryForm.parent_id) : null,
            sort_order: Number(categoryForm.sort_order || 0),
            description: categoryForm.description || null,
        };
        if (categoryForm.id) {
            await semanticApi.categories.update(categoryForm.id, payload);
            notice.value = '分类已更新';
        } else {
            await semanticApi.categories.create(payload);
            notice.value = '分类已创建';
        }
        resetCategory();
        await load();
    } catch (exception) {
        error.value = exception.message ?? '分类保存失败';
    } finally {
        saving.value = false;
    }
}

async function saveMetric() {
    saving.value = true;
    error.value = '';

    try {
        const payload = {
            dataset_id: Number(metricForm.dataset_id),
            category_id: metricForm.category_id ? Number(metricForm.category_id) : null,
            name: metricForm.name,
            code: metricForm.code,
            metric_type: metricForm.metric_type,
            aggregate_function: metricForm.aggregate_function,
            source_field: metricForm.source_field || null,
            formula: metricForm.formula || null,
            unit: metricForm.unit || null,
            precision: Number(metricForm.precision ?? 2),
            format_type: metricForm.format_type,
            description: metricForm.description || null,
            status: metricForm.status,
        };
        if (metricForm.id) {
            await semanticApi.metrics.update(metricForm.id, payload);
            notice.value = '指标已更新';
        } else {
            await semanticApi.metrics.create(payload);
            notice.value = '指标已创建';
        }
        resetMetric();
        await load();
    } catch (exception) {
        error.value = exception.message ?? '指标保存失败';
    } finally {
        saving.value = false;
    }
}

async function saveDimension() {
    saving.value = true;
    error.value = '';

    try {
        const payload = {
            dataset_id: Number(dimensionForm.dataset_id),
            name: dimensionForm.name,
            code: dimensionForm.code,
            field_name: dimensionForm.field_name,
            dimension_type: dimensionForm.dimension_type,
            time_grain_options_json: dimensionForm.time_grain_options_json,
            description: dimensionForm.description || null,
            status: dimensionForm.status,
        };
        if (dimensionForm.id) {
            await semanticApi.dimensions.update(dimensionForm.id, payload);
            notice.value = '维度已更新';
        } else {
            await semanticApi.dimensions.create(payload);
            notice.value = '维度已创建';
        }
        resetDimension();
        await load();
    } catch (exception) {
        error.value = exception.message ?? '维度保存失败';
    } finally {
        saving.value = false;
    }
}

function editCategory(row) {
    Object.assign(categoryForm, {
        id: row.id,
        name: row.name,
        parent_id: row.parent_id ?? '',
        sort_order: row.sort_order ?? 0,
        description: row.description ?? '',
    });
}

function editMetric(row) {
    Object.assign(metricForm, {
        id: row.id,
        dataset_id: row.dataset_id,
        category_id: row.category_id ?? '',
        name: row.name,
        code: row.code,
        metric_type: row.metric_type,
        aggregate_function: row.aggregate_function,
        source_field: row.source_field ?? '',
        formula: row.formula ?? '',
        unit: row.unit ?? '',
        precision: row.precision ?? 2,
        format_type: row.format_type ?? 'number',
        description: row.description ?? '',
        status: row.status ?? 'draft',
    });
}

function editDimension(row) {
    Object.assign(dimensionForm, {
        id: row.id,
        dataset_id: row.dataset_id,
        name: row.name,
        code: row.code,
        field_name: row.field_name,
        dimension_type: row.dimension_type,
        time_grain_options_json: row.time_grain_options_json ?? [],
        description: row.description ?? '',
        status: row.status ?? 'active',
    });
}

async function removeCategory(row) {
    await semanticApi.categories.remove(row.id);
    await load();
}

async function removeMetric(row) {
    await semanticApi.metrics.remove(row.id);
    await load();
}

async function removeDimension(row) {
    await semanticApi.dimensions.remove(row.id);
    await load();
}

async function transitionMetric(row, action) {
    const result = await semanticApi.metrics[action](row.id);
    notice.value = `指标状态：${result.data.status}`;
    await load();
}

async function validateFormula() {
    if (!metricForm.dataset_id || !metricForm.formula) {
        return;
    }
    const result = await semanticApi.metrics.validateFormula({
        dataset_id: Number(metricForm.dataset_id),
        formula: metricForm.formula,
    });
    formulaResult.value = result.data;
}

async function analyzeImpact(row) {
    const result = await semanticApi.metrics.impact(row.id);
    impact.value = result.data;
}

async function initMetrics() {
    if (!filters.dataset_id) {
        error.value = '请先选择数据集';
        return;
    }
    await semanticApi.initMetricsFromFields(filters.dataset_id);
    notice.value = '指标草稿已初始化';
    await load();
}

async function initDimensions() {
    if (!filters.dataset_id) {
        error.value = '请先选择数据集';
        return;
    }
    await semanticApi.dimensions.initFromFields(filters.dataset_id);
    notice.value = '维度已初始化';
    await load();
}

watch(() => filters.dataset_id, () => {
    resetMetric();
    resetDimension();
    load();
});

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <PageHeader title="语义层" :subtitle="`指标库、维度管理、公式校验和影响分析：${selectedDatasetName}`">
            <button class="tool-button" type="button" @click="load">
                <RefreshCw :size="16" />
                <span>刷新</span>
            </button>
            <button class="tool-button" type="button" @click="initDimensions">
                <GitBranch :size="16" />
                <span>初始化维度</span>
            </button>
            <button class="primary-button" type="button" @click="initMetrics">
                <Plus :size="16" />
                <span>初始化指标</span>
            </button>
        </PageHeader>

        <form class="filter-bar" @submit.prevent="load">
            <select v-model="filters.dataset_id">
                <option value="">全部数据集</option>
                <option v-for="dataset in datasets" :key="dataset.id" :value="dataset.id">{{ dataset.name }}</option>
            </select>
            <select v-model="filters.status">
                <option value="">全部状态</option>
                <option value="draft">draft</option>
                <option value="active">active</option>
                <option value="deprecated">deprecated</option>
                <option value="archived">archived</option>
            </select>
        </form>

        <p v-if="error" class="form-error">{{ error }}</p>
        <p v-if="notice" class="form-notice">{{ notice }}</p>

        <section class="work-grid">
            <form class="surface form-panel" @submit.prevent="saveCategory">
                <div class="section-heading">
                    <h3>{{ categoryForm.id ? '编辑分类' : '新增分类' }}</h3>
                </div>
                <div class="form-grid two">
                    <label><span>名称</span><input v-model="categoryForm.name" required></label>
                    <label><span>父级</span><select v-model="categoryForm.parent_id"><option value="">无</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
                    <label><span>排序</span><input v-model.number="categoryForm.sort_order" type="number" min="0"></label>
                </div>
                <label class="full-row"><span>说明</span><textarea v-model="categoryForm.description" rows="3" /></label>
                <div class="button-row">
                    <button class="primary-button" type="submit" :disabled="saving"><Save :size="16" /><span>保存分类</span></button>
                    <button class="tool-button" type="button" @click="resetCategory">清空</button>
                </div>
            </form>

            <section class="surface">
                <div class="section-heading">
                    <h3>指标分类</h3>
                    <span class="muted">{{ categories.length }} 个</span>
                </div>
                <DataTable :columns="categoryColumns" :rows="categories" :loading="loading">
                    <template #cell-actions="{ row }">
                        <div class="row-actions">
                            <button class="icon-button" type="button" title="编辑" @click="editCategory(row)"><GitBranch :size="16" /></button>
                            <button class="icon-button danger" type="button" title="删除" @click="removeCategory(row)"><Trash2 :size="16" /></button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </section>

        <section class="work-grid">
            <form class="surface form-panel" @submit.prevent="saveMetric">
                <div class="section-heading">
                    <h3>{{ metricForm.id ? '编辑指标' : '新增指标' }}</h3>
                    <StatusBadge :value="metricForm.status" />
                </div>
                <div class="form-grid two">
                    <label><span>数据集</span><select v-model="metricForm.dataset_id" required><option value="">请选择</option><option v-for="dataset in datasets" :key="dataset.id" :value="dataset.id">{{ dataset.name }}</option></select></label>
                    <label><span>分类</span><select v-model="metricForm.category_id"><option value="">无</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
                    <label><span>名称</span><input v-model="metricForm.name" required></label>
                    <label><span>编码</span><input v-model="metricForm.code" required></label>
                    <label><span>类型</span><select v-model="metricForm.metric_type"><option value="base">base</option><option value="derived">derived</option><option value="compound">compound</option></select></label>
                    <label><span>聚合</span><select v-model="metricForm.aggregate_function"><option value="sum">sum</option><option value="avg">avg</option><option value="count">count</option><option value="countDistinct">countDistinct</option><option value="min">min</option><option value="max">max</option><option value="expression">expression</option></select></label>
                    <label><span>来源字段</span><input v-model="metricForm.source_field"></label>
                    <label><span>状态</span><select v-model="metricForm.status"><option value="draft">draft</option><option value="active">active</option><option value="deprecated">deprecated</option><option value="archived">archived</option></select></label>
                    <label><span>单位</span><input v-model="metricForm.unit"></label>
                    <label><span>精度</span><input v-model.number="metricForm.precision" type="number" min="0" max="8"></label>
                </div>
                <label class="full-row"><span>公式</span><input v-model="metricForm.formula" placeholder="sales_amount / order_count"></label>
                <label class="full-row"><span>说明</span><textarea v-model="metricForm.description" rows="3" /></label>
                <p v-if="formulaResult" class="form-notice">
                    {{ formulaResult.valid ? `公式有效：${(formulaResult.dependencies || []).join(', ')}` : formulaResult.message }}
                </p>
                <div class="button-row">
                    <button class="primary-button" type="submit" :disabled="saving"><Save :size="16" /><span>保存指标</span></button>
                    <button class="tool-button" type="button" @click="validateFormula"><CheckCircle2 :size="16" /><span>校验公式</span></button>
                    <button class="tool-button" type="button" @click="resetMetric">清空</button>
                </div>
            </form>

            <section class="surface">
                <div class="section-heading">
                    <h3>指标库</h3>
                    <span class="muted">{{ metrics.length }} 个</span>
                </div>
                <DataTable :columns="metricColumns" :rows="metrics" :loading="loading">
                    <template #cell-status="{ value }"><StatusBadge :value="value" /></template>
                    <template #cell-actions="{ row }">
                        <div class="row-actions">
                            <button class="tool-button compact" type="button" @click="editMetric(row)">编辑</button>
                            <button class="tool-button compact" type="button" @click="transitionMetric(row, 'activate')">启用</button>
                            <button class="tool-button compact" type="button" @click="transitionMetric(row, 'deprecate')">废弃</button>
                            <button class="tool-button compact" type="button" @click="analyzeImpact(row)">影响</button>
                            <button class="icon-button danger" type="button" title="删除" @click="removeMetric(row)"><Trash2 :size="16" /></button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </section>

        <section class="work-grid">
            <form class="surface form-panel" @submit.prevent="saveDimension">
                <div class="section-heading">
                    <h3>{{ dimensionForm.id ? '编辑维度' : '新增维度' }}</h3>
                    <StatusBadge :value="dimensionForm.status" />
                </div>
                <div class="form-grid two">
                    <label><span>数据集</span><select v-model="dimensionForm.dataset_id" required><option value="">请选择</option><option v-for="dataset in datasets" :key="dataset.id" :value="dataset.id">{{ dataset.name }}</option></select></label>
                    <label><span>名称</span><input v-model="dimensionForm.name" required></label>
                    <label><span>编码</span><input v-model="dimensionForm.code" required></label>
                    <label><span>字段</span><input v-model="dimensionForm.field_name" required></label>
                    <label><span>类型</span><select v-model="dimensionForm.dimension_type"><option value="string">string</option><option value="number">number</option><option value="date">date</option><option value="datetime">datetime</option><option value="region">region</option><option value="organization">organization</option><option value="user">user</option><option value="enum">enum</option></select></label>
                    <label><span>状态</span><select v-model="dimensionForm.status"><option value="active">active</option><option value="disabled">disabled</option></select></label>
                </div>
                <label class="full-row">
                    <span>时间颗粒 JSON</span>
                    <JsonTextarea v-model="dimensionForm.time_grain_options_json" :rows="3" @invalid="grainError = $event" />
                    <small v-if="grainError" class="form-error">{{ grainError }}</small>
                </label>
                <label class="full-row"><span>说明</span><textarea v-model="dimensionForm.description" rows="3" /></label>
                <div class="button-row">
                    <button class="primary-button" type="submit" :disabled="saving || Boolean(grainError)"><Save :size="16" /><span>保存维度</span></button>
                    <button class="tool-button" type="button" @click="resetDimension">清空</button>
                </div>
            </form>

            <section class="surface">
                <div class="section-heading">
                    <h3>维度管理</h3>
                    <span class="muted">{{ dimensions.length }} 个</span>
                </div>
                <DataTable :columns="dimensionColumns" :rows="dimensions" :loading="loading">
                    <template #cell-status="{ value }"><StatusBadge :value="value" /></template>
                    <template #cell-actions="{ row }">
                        <div class="row-actions">
                            <button class="tool-button compact" type="button" @click="editDimension(row)">编辑</button>
                            <button class="icon-button danger" type="button" title="删除" @click="removeDimension(row)"><Trash2 :size="16" /></button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </section>

        <section v-if="impact" class="surface">
            <div class="section-heading">
                <h3>影响分析</h3>
                <StatusBadge :value="impact.risk_level" />
            </div>
            <pre class="json-preview">{{ JSON.stringify(impact, null, 2) }}</pre>
        </section>
    </div>
</template>
