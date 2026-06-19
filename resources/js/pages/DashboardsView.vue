<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Eye, LayoutDashboard, Plus, RefreshCw, Save, Share2, Trash2 } from '@lucide/vue';

import ChartRenderer from '../components/ChartRenderer.vue';
import DataTable from '../components/DataTable.vue';
import JsonTextarea from '../components/JsonTextarea.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { chartApi, dashboardApi } from '../services/api';
import { itemsFrom } from '../services/http';

const dashboards = ref([]);
const charts = ref([]);
const selected = ref(null);
const detail = ref(null);
const dashboardData = ref(null);
const share = ref(null);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const layoutError = ref('');
const filtersError = ref('');

const form = reactive({
    id: null,
    name: '',
    description: '',
    layout_json: [],
    global_filters_json: [],
    status: 'active',
});

const widgetForm = reactive({
    chart_id: '',
    x: 0,
    y: 0,
    w: 6,
    h: 4,
    sort_order: 0,
    config_json: {},
});

const columns = [
    { key: 'name', label: '名称' },
    { key: 'status', label: '状态' },
    { key: 'widgets_count', label: '组件数' },
    { key: 'updated_at', label: '更新时间' },
    { key: 'actions', label: '操作' },
];

const widgetColumns = [
    { key: 'id', label: 'ID' },
    { key: 'chart_id', label: '图表' },
    { key: 'x', label: 'X' },
    { key: 'y', label: 'Y' },
    { key: 'w', label: 'W' },
    { key: 'h', label: 'H' },
    { key: 'actions', label: '操作' },
];

const previewWidgets = computed(() => {
    const widgets = detail.value?.widgets ?? [];
    const data = dashboardData.value?.widgets ?? [];

    return widgets.map((widget) => ({
        ...widget,
        chartData: data.find((item) => item.widget_id === widget.id)?.data ?? { columns: [], rows: [] },
    }));
});

function resetForm() {
    Object.assign(form, {
        id: null,
        name: '',
        description: '',
        layout_json: [],
        global_filters_json: [],
        status: 'active',
    });
}

function edit(row) {
    Object.assign(form, {
        id: row.id,
        name: row.name,
        description: row.description ?? '',
        layout_json: row.layout_json ?? [],
        global_filters_json: row.global_filters_json ?? [],
        status: row.status ?? 'active',
    });
    select(row);
}

function payload() {
    return {
        name: form.name,
        description: form.description || null,
        layout_json: form.layout_json,
        global_filters_json: form.global_filters_json,
        status: form.status,
    };
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [dashboardResult, chartResult] = await Promise.all([
            dashboardApi.list({ page_size: 100 }),
            chartApi.list({ page_size: 100 }),
        ]);
        dashboards.value = itemsFrom(dashboardResult);
        charts.value = itemsFrom(chartResult);
    } catch (exception) {
        error.value = exception.message ?? '仪表盘加载失败';
    } finally {
        loading.value = false;
    }
}

async function save() {
    error.value = '';
    notice.value = '';

    try {
        const result = form.id
            ? await dashboardApi.update(form.id, payload())
            : await dashboardApi.create(payload());

        notice.value = form.id ? '仪表盘已更新' : '仪表盘已创建';
        form.id = result.data.id;
        await load();
        await select(result.data);
    } catch (exception) {
        error.value = exception.message ?? '保存失败';
    }
}

async function remove(row) {
    if (!window.confirm(`确认删除仪表盘 ${row.name}？`)) {
        return;
    }

    await dashboardApi.remove(row.id);
    if (selected.value?.id === row.id) {
        selected.value = null;
        detail.value = null;
        dashboardData.value = null;
    }
    await load();
}

async function select(row) {
    selected.value = row;
    share.value = null;

    try {
        const result = await dashboardApi.show(row.id);
        detail.value = result.data;
    } catch (exception) {
        error.value = exception.message ?? '仪表盘详情加载失败';
    }
}

async function addWidget() {
    if (!selected.value) {
        error.value = '请先选择或保存仪表盘';
        return;
    }

    try {
        await dashboardApi.createWidget(selected.value.id, {
            chart_id: Number(widgetForm.chart_id),
            widget_type: 'chart',
            x: Number(widgetForm.x),
            y: Number(widgetForm.y),
            w: Number(widgetForm.w),
            h: Number(widgetForm.h),
            sort_order: Number(widgetForm.sort_order),
            config_json: widgetForm.config_json,
        });
        notice.value = '组件已添加';
        await select(selected.value);
        await load();
    } catch (exception) {
        error.value = exception.message ?? '组件添加失败';
    }
}

async function removeWidget(widget) {
    if (!selected.value) return;

    await dashboardApi.removeWidget(selected.value.id, widget.id);
    await select(selected.value);
}

async function refreshData() {
    if (!selected.value) return;

    try {
        const result = await dashboardApi.data(selected.value.id, { filters: [] });
        dashboardData.value = result.data;
    } catch (exception) {
        error.value = exception.message ?? '仪表盘数据刷新失败';
    }
}

async function createShare() {
    if (!selected.value) return;

    try {
        const result = await dashboardApi.share(selected.value.id, { share_type: 'public' });
        share.value = result.data;
    } catch (exception) {
        error.value = exception.message ?? '分享创建失败';
    }
}

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <PageHeader title="仪表盘页面" subtitle="创建仪表盘、维护图表组件布局、刷新数据并生成分享信息。">
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
                    <h3>{{ form.id ? '编辑仪表盘' : '新增仪表盘' }}</h3>
                    <StatusBadge :value="form.status" />
                </div>
                <div class="form-grid two">
                    <label><span>名称</span><input v-model="form.name" required></label>
                    <label><span>状态</span><select v-model="form.status"><option value="active">active</option><option value="disabled">disabled</option></select></label>
                </div>
                <label class="full-row"><span>描述</span><textarea v-model="form.description" rows="3" /></label>
                <label class="full-row">
                    <span>布局 JSON</span>
                    <JsonTextarea v-model="form.layout_json" :rows="5" @invalid="layoutError = $event" />
                    <small v-if="layoutError" class="form-error">{{ layoutError }}</small>
                </label>
                <label class="full-row">
                    <span>全局筛选 JSON</span>
                    <JsonTextarea v-model="form.global_filters_json" :rows="5" @invalid="filtersError = $event" />
                    <small v-if="filtersError" class="form-error">{{ filtersError }}</small>
                </label>
                <button class="primary-button" type="submit" :disabled="Boolean(layoutError || filtersError)">
                    <Save :size="16" />
                    <span>保存</span>
                </button>
            </form>

            <section class="surface">
                <div class="section-heading">
                    <h3>仪表盘列表</h3>
                    <span class="muted">{{ dashboards.length }} 个</span>
                </div>
                <DataTable :columns="columns" :rows="dashboards" :loading="loading">
                    <template #cell-status="{ value }"><StatusBadge :value="value" /></template>
                    <template #cell-actions="{ row }">
                        <div class="row-actions">
                            <button class="icon-button" type="button" title="编辑" @click="edit(row)"><LayoutDashboard :size="16" /></button>
                            <button class="icon-button" type="button" title="数据" @click="select(row); refreshData()"><Eye :size="16" /></button>
                            <button class="icon-button danger" type="button" title="删除" @click="remove(row)"><Trash2 :size="16" /></button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </section>

        <section class="surface">
            <div class="section-heading">
                <h3>组件管理</h3>
                <span class="muted">{{ detail?.name ?? '未选择仪表盘' }}</span>
            </div>
            <form class="inline-form" @submit.prevent="addWidget">
                <select v-model="widgetForm.chart_id" required>
                    <option value="">选择图表</option>
                    <option v-for="chart in charts" :key="chart.id" :value="chart.id">{{ chart.name }}</option>
                </select>
                <input v-model.number="widgetForm.x" type="number" min="0" placeholder="x">
                <input v-model.number="widgetForm.y" type="number" min="0" placeholder="y">
                <input v-model.number="widgetForm.w" type="number" min="1" placeholder="w">
                <input v-model.number="widgetForm.h" type="number" min="1" placeholder="h">
                <button class="primary-button" type="submit">添加组件</button>
            </form>
            <DataTable :columns="widgetColumns" :rows="detail?.widgets ?? []">
                <template #cell-actions="{ row }">
                    <button class="icon-button danger" type="button" title="删除组件" @click="removeWidget(row)"><Trash2 :size="16" /></button>
                </template>
            </DataTable>
        </section>

        <section class="surface">
            <div class="section-heading">
                <h3>仪表盘预览</h3>
                <div class="button-row">
                    <button class="tool-button compact" type="button" :disabled="!selected" @click="refreshData">
                        <RefreshCw :size="14" />
                        <span>刷新数据</span>
                    </button>
                    <button class="tool-button compact" type="button" :disabled="!selected" @click="createShare">
                        <Share2 :size="14" />
                        <span>分享</span>
                    </button>
                </div>
            </div>
            <p v-if="share" class="form-notice">分享 Token：{{ share.share_token }}</p>
            <div class="dashboard-preview-grid">
                <article v-for="widget in previewWidgets" :key="widget.id" class="dashboard-widget">
                    <h4>{{ widget.chart?.name ?? `图表 ${widget.chart_id}` }}</h4>
                    <ChartRenderer
                        :type="widget.chart?.chart_type ?? 'bar'"
                        :data="widget.chartData"
                        :style-config="widget.chart?.style_json ?? {}"
                    />
                </article>
                <p v-if="previewWidgets.length === 0" class="muted">暂无组件</p>
            </div>
        </section>
    </div>
</template>
