<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { GitBranch, Play, RefreshCw, Save, Search, Tags } from '@lucide/vue';

import DataTable from '../components/DataTable.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { metadataApi } from '../services/api';
import { itemsFrom, paginationFrom } from '../services/http';

const tabs = [
    { key: 'catalog', label: '元数据目录' },
    { key: 'lineage', label: '数据血缘' },
    { key: 'impact', label: '影响分析' },
    { key: 'tags', label: '资产标签' },
    { key: 'usage', label: '使用统计' },
];

const assetTypes = [
    'data_source',
    'physical_table',
    'physical_column',
    'dataset',
    'dataset_field',
    'dimension',
    'metric',
    'chart',
    'dashboard',
    'acceleration_profile',
    'aggregate_definition',
    'materialized_view',
];

const active = ref('catalog');
const assets = ref([]);
const tags = ref([]);
const usageStats = ref([]);
const selected = ref(null);
const lineage = ref(null);
const impact = ref(null);
const usageSummary = ref({ low_frequency_assets: [], high_slow_assets: [] });
const pagination = ref({ page: 1, page_size: 20, total: 0 });
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');

const filters = reactive({
    asset_type: '',
    keyword: '',
    tag: '',
    status: '',
    page_size: 20,
});

const lineageForm = reactive({
    asset_type: '',
    asset_id: '',
    depth: 3,
});

const impactForm = reactive({
    asset_type: '',
    asset_id: '',
    change_type: 'delete',
});

const tagForm = reactive({
    id: null,
    name: '',
    color: '#2563eb',
    description: '',
});

const attachForm = reactive({
    asset_type: '',
    asset_id: '',
    tag_id: '',
});

const usageFilters = reactive({
    asset_type: '',
    usage_date: '',
    high_slow: '',
    low_frequency: '',
    page_size: 20,
});

const assetColumns = [
    { key: 'name', label: '资产' },
    { key: 'asset_type', label: '类型' },
    { key: 'code', label: '编码' },
    { key: 'dataset_id', label: '数据集' },
    { key: 'data_source_id', label: '数据源' },
    { key: 'status', label: '状态' },
    { key: 'tags', label: '标签' },
    { key: 'actions', label: '操作' },
];

const relationColumns = [
    { key: 'source_key', label: '来源' },
    { key: 'relation_type', label: '关系' },
    { key: 'target_key', label: '目标' },
    { key: 'confidence', label: '置信度' },
];

const tagColumns = [
    { key: 'name', label: '标签' },
    { key: 'color', label: '颜色' },
    { key: 'description', label: '说明' },
    { key: 'actions', label: '操作' },
];

const usageColumns = [
    { key: 'asset_type', label: '类型' },
    { key: 'asset_id', label: '资产 ID' },
    { key: 'usage_date', label: '日期' },
    { key: 'query_count', label: '查询' },
    { key: 'avg_duration_ms', label: '平均 ms' },
    { key: 'slow_query_count', label: '慢查询' },
    { key: 'last_used_at', label: '最近使用' },
];

const selectedLabel = computed(() => selected.value ? `${selected.value.asset_type}:${selected.value.asset_id} ${selected.value.name}` : '未选择资产');
const lineageRelations = computed(() => {
    if (lineage.value?.relations) {
        return lineage.value.relations;
    }

    return (lineage.value?.edges ?? []).map((edge) => ({
        source_key: edge.source,
        target_key: edge.target,
        relation_type: edge.relation,
        confidence: edge.confidence,
    }));
});

function cleanParams(source) {
    return Object.fromEntries(Object.entries(source).filter(([, value]) => value !== '' && value !== null));
}

function selectAsset(row) {
    selected.value = row;
    Object.assign(lineageForm, {
        asset_type: row.asset_type,
        asset_id: row.asset_id,
        depth: lineageForm.depth,
    });
    Object.assign(impactForm, {
        asset_type: row.asset_type,
        asset_id: row.asset_id,
        change_type: impactForm.change_type,
    });
    Object.assign(attachForm, {
        asset_type: row.asset_type,
        asset_id: row.asset_id,
        tag_id: attachForm.tag_id,
    });
}

async function loadAssets() {
    loading.value = true;
    error.value = '';

    try {
        const result = await metadataApi.assets.list(cleanParams(filters));
        assets.value = itemsFrom(result);
        pagination.value = paginationFrom(result);

        if (!selected.value && assets.value.length > 0) {
            selectAsset(assets.value[0]);
        }
    } catch (exception) {
        error.value = exception.message ?? '元数据目录加载失败';
    } finally {
        loading.value = false;
    }
}

async function syncMetadata(dryRun = false) {
    saving.value = true;
    error.value = '';

    try {
        const result = await metadataApi.sync({ scope: 'all', dry_run: dryRun });
        notice.value = dryRun ? `模拟同步：${result.data.assets_synced} 个资产` : `已同步 ${result.data.assets_synced} 个资产`;
        if (!dryRun) {
            await loadAssets();
        }
    } catch (exception) {
        error.value = exception.message ?? '元数据同步失败';
    } finally {
        saving.value = false;
    }
}

async function loadLineage(direction = 'graph') {
    if (!lineageForm.asset_type || !lineageForm.asset_id) {
        error.value = '请先选择资产';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const params = { depth: Number(lineageForm.depth || 3) };
        const result = direction === 'upstream'
            ? await metadataApi.lineage.upstream(lineageForm.asset_type, lineageForm.asset_id, params)
            : direction === 'downstream'
                ? await metadataApi.lineage.downstream(lineageForm.asset_type, lineageForm.asset_id, params)
                : await metadataApi.lineage.graph(lineageForm.asset_type, lineageForm.asset_id, params);
        lineage.value = result.data;
        active.value = 'lineage';
    } catch (exception) {
        error.value = exception.message ?? '血缘加载失败';
    } finally {
        loading.value = false;
    }
}

async function rebuildSelectedLineage() {
    if (!lineageForm.asset_type || !lineageForm.asset_id) {
        error.value = '请先选择资产';
        return;
    }

    saving.value = true;
    error.value = '';

    try {
        const result = await metadataApi.lineage.sync(lineageForm.asset_type, lineageForm.asset_id);
        notice.value = `已重建 ${result.data.relations_synced} 条血缘`;
        await loadLineage('graph');
    } catch (exception) {
        error.value = exception.message ?? '血缘重建失败';
    } finally {
        saving.value = false;
    }
}

async function analyzeImpact() {
    if (!impactForm.asset_type || !impactForm.asset_id) {
        error.value = '请先选择资产';
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const result = await metadataApi.impact.analyze({
            asset_type: impactForm.asset_type,
            asset_id: Number(impactForm.asset_id),
            change_type: impactForm.change_type,
        });
        impact.value = result.data;
        active.value = 'impact';
    } catch (exception) {
        error.value = exception.message ?? '影响分析失败';
    } finally {
        loading.value = false;
    }
}

async function loadTags() {
    try {
        const result = await metadataApi.tags.list({ page_size: 100 });
        tags.value = itemsFrom(result);
    } catch (exception) {
        error.value = exception.message ?? '标签加载失败';
    }
}

function editTag(row) {
    Object.assign(tagForm, {
        id: row.id,
        name: row.name,
        color: row.color ?? '#2563eb',
        description: row.description ?? '',
    });
}

function resetTagForm() {
    Object.assign(tagForm, { id: null, name: '', color: '#2563eb', description: '' });
}

async function saveTag() {
    saving.value = true;
    error.value = '';

    try {
        const payload = {
            name: tagForm.name,
            color: tagForm.color || null,
            description: tagForm.description || null,
        };
        if (tagForm.id) {
            await metadataApi.tags.update(tagForm.id, payload);
            notice.value = '标签已更新';
        } else {
            await metadataApi.tags.create(payload);
            notice.value = '标签已创建';
        }
        resetTagForm();
        await loadTags();
        await loadAssets();
    } catch (exception) {
        error.value = exception.message ?? '标签保存失败';
    } finally {
        saving.value = false;
    }
}

async function removeTag(row) {
    if (!window.confirm(`删除标签 ${row.name}？`)) {
        return;
    }

    saving.value = true;
    error.value = '';

    try {
        await metadataApi.tags.remove(row.id);
        notice.value = '标签已删除';
        await loadTags();
        await loadAssets();
    } catch (exception) {
        error.value = exception.message ?? '标签删除失败';
    } finally {
        saving.value = false;
    }
}

async function attachTag() {
    if (!attachForm.asset_type || !attachForm.asset_id || !attachForm.tag_id) {
        error.value = '请选择资产和标签';
        return;
    }

    saving.value = true;
    error.value = '';

    try {
        await metadataApi.assets.attachTag(attachForm.asset_type, attachForm.asset_id, { tag_id: Number(attachForm.tag_id) });
        notice.value = '标签已绑定';
        await loadAssets();
    } catch (exception) {
        error.value = exception.message ?? '绑定标签失败';
    } finally {
        saving.value = false;
    }
}

async function loadUsageStats() {
    loading.value = true;
    error.value = '';

    try {
        const result = await metadataApi.usageStats(cleanParams(usageFilters));
        usageStats.value = itemsFrom(result);
        usageSummary.value = result.data?.summary ?? { low_frequency_assets: [], high_slow_assets: [] };
    } catch (exception) {
        error.value = exception.message ?? '使用统计加载失败';
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await Promise.all([loadAssets(), loadTags(), loadUsageStats()]);
});
</script>

<template>
    <div class="page-stack">
        <PageHeader title="数据治理" subtitle="统一查看元数据目录、血缘、影响分析、资产标签和使用统计。">
            <button class="tool-button" type="button" :disabled="saving" @click="syncMetadata(true)">
                <Play :size="16" />
                <span>模拟同步</span>
            </button>
            <button class="primary-button" type="button" :disabled="saving" @click="syncMetadata(false)">
                <RefreshCw :size="16" />
                <span>同步元数据</span>
            </button>
        </PageHeader>

        <div class="tabs">
            <button v-for="tab in tabs" :key="tab.key" :class="{ active: active === tab.key }" type="button" @click="active = tab.key">
                {{ tab.label }}
            </button>
        </div>

        <p v-if="error" class="form-error">{{ error }}</p>
        <p v-if="notice" class="form-notice">{{ notice }}</p>

        <template v-if="active === 'catalog'">
            <form class="filter-bar" @submit.prevent="loadAssets">
                <input v-model="filters.keyword" placeholder="搜索名称、编码、描述">
                <select v-model="filters.asset_type">
                    <option value="">全部类型</option>
                    <option v-for="type in assetTypes" :key="type" :value="type">{{ type }}</option>
                </select>
                <select v-model="filters.status">
                    <option value="">全部状态</option>
                    <option value="active">active</option>
                    <option value="deprecated">deprecated</option>
                    <option value="archived">archived</option>
                    <option value="disabled">disabled</option>
                </select>
                <input v-model="filters.tag" placeholder="标签名">
                <select v-model="filters.page_size">
                    <option :value="20">20 条</option>
                    <option :value="50">50 条</option>
                    <option :value="100">100 条</option>
                </select>
                <button class="primary-button" type="submit">
                    <Search :size="16" />
                    <span>查询</span>
                </button>
            </form>

            <section class="surface">
                <div class="section-heading">
                    <h3>资产列表</h3>
                    <span class="muted">共 {{ pagination.total }} 条，当前选择：{{ selectedLabel }}</span>
                </div>
                <DataTable :columns="assetColumns" :rows="assets" :loading="loading">
                    <template #cell-status="{ value }"><StatusBadge :value="value" /></template>
                    <template #cell-tags="{ value }">
                        <span>{{ (value ?? []).map((tag) => tag.name).join('、') || '-' }}</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <button class="tool-button compact" type="button" @click="selectAsset(row)">选择</button>
                        <button class="tool-button compact" type="button" @click="selectAsset(row); loadLineage('graph')">血缘</button>
                        <button class="tool-button compact" type="button" @click="selectAsset(row); analyzeImpact()">影响</button>
                    </template>
                </DataTable>
            </section>
        </template>

        <template v-else-if="active === 'lineage'">
            <section class="surface">
                <div class="section-heading">
                    <h3>血缘查询</h3>
                    <span class="muted">{{ selectedLabel }}</span>
                </div>
                <form class="filter-bar" @submit.prevent="loadLineage('graph')">
                    <select v-model="lineageForm.asset_type">
                        <option value="">资产类型</option>
                        <option v-for="type in assetTypes" :key="type" :value="type">{{ type }}</option>
                    </select>
                    <input v-model="lineageForm.asset_id" type="number" min="1" placeholder="资产 ID">
                    <select v-model="lineageForm.depth">
                        <option :value="1">1 层</option>
                        <option :value="2">2 层</option>
                        <option :value="3">3 层</option>
                        <option :value="4">4 层</option>
                        <option :value="5">5 层</option>
                    </select>
                    <button class="tool-button" type="button" @click="loadLineage('upstream')">上游</button>
                    <button class="tool-button" type="button" @click="loadLineage('downstream')">下游</button>
                    <button class="primary-button" type="submit"><GitBranch :size="16" /><span>图谱</span></button>
                    <button class="tool-button" type="button" :disabled="saving" @click="rebuildSelectedLineage">重建</button>
                </form>
            </section>

            <section class="split-grid">
                <div class="surface">
                    <div class="section-heading">
                        <h3>节点</h3>
                        <span class="muted">{{ lineage?.nodes?.length ?? 0 }} 个</span>
                    </div>
                    <DataTable :columns="[{ key: 'id', label: '节点' }, { key: 'type', label: '类型' }, { key: 'name', label: '名称' }, { key: 'status', label: '状态' }]" :rows="lineage?.nodes ?? []">
                        <template #cell-status="{ value }"><StatusBadge :value="value" /></template>
                    </DataTable>
                </div>
                <div class="surface">
                    <div class="section-heading">
                        <h3>关系</h3>
                        <span class="muted">{{ lineageRelations.length }} 条</span>
                    </div>
                    <DataTable :columns="relationColumns" :rows="lineageRelations" />
                </div>
            </section>
        </template>

        <template v-else-if="active === 'impact'">
            <section class="surface">
                <div class="section-heading">
                    <h3>影响分析</h3>
                    <span class="muted">{{ selectedLabel }}</span>
                </div>
                <form class="filter-bar" @submit.prevent="analyzeImpact">
                    <select v-model="impactForm.asset_type">
                        <option value="">资产类型</option>
                        <option v-for="type in assetTypes" :key="type" :value="type">{{ type }}</option>
                    </select>
                    <input v-model="impactForm.asset_id" type="number" min="1" placeholder="资产 ID">
                    <select v-model="impactForm.change_type">
                        <option value="delete">delete</option>
                        <option value="update">update</option>
                        <option value="deprecate">deprecate</option>
                        <option value="archive">archive</option>
                        <option value="schema_change">schema_change</option>
                    </select>
                    <button class="primary-button" type="submit"><Play :size="16" /><span>分析</span></button>
                </form>
            </section>

            <section v-if="impact" class="surface">
                <div class="section-heading">
                    <h3>风险结果</h3>
                    <StatusBadge :value="impact.risk_level" />
                </div>
                <div class="metric-grid">
                    <div><strong>{{ impact.affected_metrics?.length ?? 0 }}</strong><span>指标</span></div>
                    <div><strong>{{ impact.affected_charts?.length ?? 0 }}</strong><span>图表</span></div>
                    <div><strong>{{ impact.affected_dashboards?.length ?? 0 }}</strong><span>仪表盘</span></div>
                    <div><strong>{{ impact.affected_acceleration_profiles?.length ?? 0 }}</strong><span>加速配置</span></div>
                </div>
                <ul class="plain-list">
                    <li v-for="suggestion in impact.suggestions" :key="suggestion">{{ suggestion }}</li>
                </ul>
            </section>
        </template>

        <template v-else-if="active === 'tags'">
            <section class="split-grid">
                <form class="surface form-panel" @submit.prevent="saveTag">
                    <div class="section-heading">
                        <h3>编辑标签</h3>
                        <button class="tool-button compact" type="button" @click="resetTagForm">清空</button>
                    </div>
                    <div class="form-grid two">
                        <label><span>名称</span><input v-model="tagForm.name" required></label>
                        <label><span>颜色</span><input v-model="tagForm.color" type="color"></label>
                        <label class="full-span"><span>说明</span><input v-model="tagForm.description"></label>
                    </div>
                    <button class="primary-button" type="submit" :disabled="saving"><Save :size="16" /><span>保存标签</span></button>
                </form>

                <form class="surface form-panel" @submit.prevent="attachTag">
                    <div class="section-heading">
                        <h3>绑定标签</h3>
                        <span class="muted">{{ selectedLabel }}</span>
                    </div>
                    <div class="form-grid two">
                        <label><span>资产类型</span><select v-model="attachForm.asset_type"><option value="">选择类型</option><option v-for="type in assetTypes" :key="type" :value="type">{{ type }}</option></select></label>
                        <label><span>资产 ID</span><input v-model="attachForm.asset_id" type="number" min="1"></label>
                        <label class="full-span"><span>标签</span><select v-model="attachForm.tag_id"><option value="">选择标签</option><option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option></select></label>
                    </div>
                    <button class="primary-button" type="submit" :disabled="saving"><Tags :size="16" /><span>绑定</span></button>
                </form>
            </section>

            <section class="surface">
                <div class="section-heading">
                    <h3>标签列表</h3>
                    <span class="muted">{{ tags.length }} 个</span>
                </div>
                <DataTable :columns="tagColumns" :rows="tags">
                    <template #cell-color="{ value }">
                        <span class="color-swatch" :style="{ backgroundColor: value || '#64748b' }" />
                    </template>
                    <template #cell-actions="{ row }">
                        <button class="tool-button compact" type="button" @click="editTag(row)">编辑</button>
                        <button class="tool-button compact" type="button" @click="removeTag(row)">删除</button>
                    </template>
                </DataTable>
            </section>
        </template>

        <template v-else>
            <form class="filter-bar" @submit.prevent="loadUsageStats">
                <select v-model="usageFilters.asset_type">
                    <option value="">全部类型</option>
                    <option v-for="type in assetTypes" :key="type" :value="type">{{ type }}</option>
                </select>
                <input v-model="usageFilters.usage_date" type="date">
                <select v-model="usageFilters.high_slow">
                    <option value="">全部性能</option>
                    <option value="1">高频慢查询</option>
                </select>
                <select v-model="usageFilters.page_size">
                    <option :value="20">20 条</option>
                    <option :value="50">50 条</option>
                    <option :value="100">100 条</option>
                </select>
                <button class="primary-button" type="submit"><Search :size="16" /><span>查询</span></button>
            </form>

            <section class="split-grid">
                <div class="surface">
                    <div class="section-heading">
                        <h3>低频资产</h3>
                        <span class="muted">{{ usageSummary.low_frequency_assets?.length ?? 0 }} 个</span>
                    </div>
                    <DataTable :columns="[{ key: 'name', label: '资产' }, { key: 'asset_type', label: '类型' }, { key: 'asset_id', label: 'ID' }]" :rows="usageSummary.low_frequency_assets ?? []" />
                </div>
                <div class="surface">
                    <div class="section-heading">
                        <h3>高频慢查询</h3>
                        <span class="muted">{{ usageSummary.high_slow_assets?.length ?? 0 }} 个</span>
                    </div>
                    <DataTable :columns="usageColumns" :rows="usageSummary.high_slow_assets ?? []" />
                </div>
            </section>

            <section class="surface">
                <div class="section-heading">
                    <h3>使用统计</h3>
                    <span class="muted">{{ usageStats.length }} 条</span>
                </div>
                <DataTable :columns="usageColumns" :rows="usageStats" :loading="loading" />
            </section>
        </template>
    </div>
</template>
