<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Database, Play, Plus, RefreshCw, Save, Trash2 } from '@lucide/vue';

import DataTable from '../components/DataTable.vue';
import JsonTextarea from '../components/JsonTextarea.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { dataSourceApi } from '../services/api';
import { itemsFrom } from '../services/http';

const rows = ref([]);
const tables = ref([]);
const fields = ref([]);
const selected = ref(null);
const selectedTable = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const jsonError = ref('');

const form = reactive({
    id: null,
    name: '',
    type: 'mysql',
    host: '127.0.0.1',
    port: 3306,
    database_name: '',
    username: '',
    password: '',
    charset: 'utf8mb4',
    timezone: '+00:00',
    options_json: { timeout: 5 },
    status: 'active',
});

const columns = [
    { key: 'name', label: '名称' },
    { key: 'type', label: '类型' },
    { key: 'host', label: '主机' },
    { key: 'database_name', label: '数据库' },
    { key: 'status', label: '状态' },
    { key: 'last_test_result', label: '测试结果' },
    { key: 'updated_at', label: '更新时间' },
    { key: 'actions', label: '操作' },
];

const tableColumns = [
    { key: 'table_name', label: '表名' },
    { key: 'table_comment', label: '说明' },
    { key: 'table_type', label: '类型' },
    { key: 'row_count_estimate', label: '估算行数' },
    { key: 'actions', label: '操作' },
];

const fieldColumns = [
    { key: 'field_name', label: '字段' },
    { key: 'field_comment', label: '说明' },
    { key: 'data_type', label: '类型' },
    { key: 'normalized_type', label: '标准类型' },
    { key: 'is_nullable', label: '可空' },
    { key: 'is_primary_key', label: '主键' },
];

const selectedTitle = computed(() => selected.value ? `${selected.value.name} 元数据` : '请选择数据源查看元数据');

function resetForm() {
    Object.assign(form, {
        id: null,
        name: '',
        type: 'mysql',
        host: '127.0.0.1',
        port: 3306,
        database_name: '',
        username: '',
        password: '',
        charset: 'utf8mb4',
        timezone: '+00:00',
        options_json: { timeout: 5 },
        status: 'active',
    });
}

function edit(row) {
    Object.assign(form, {
        id: row.id,
        name: row.name,
        type: row.type ?? 'mysql',
        host: row.host,
        port: row.port ?? 3306,
        database_name: row.database_name,
        username: row.username,
        password: '',
        charset: row.charset ?? 'utf8mb4',
        timezone: row.timezone ?? '+00:00',
        options_json: row.options_json ?? { timeout: 5 },
        status: row.status ?? 'active',
    });
    select(row);
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const result = await dataSourceApi.list({ page_size: 100 });
        rows.value = itemsFrom(result);
    } catch (exception) {
        error.value = exception.message ?? '数据源加载失败';
    } finally {
        loading.value = false;
    }
}

function payload() {
    const data = {
        name: form.name,
        type: form.type,
        host: form.host,
        port: Number(form.port),
        database_name: form.database_name,
        username: form.username,
        password: form.password || null,
        charset: form.charset,
        timezone: form.timezone,
        options_json: form.options_json,
        status: form.status,
    };

    if (form.id && !form.password) {
        delete data.password;
    }

    return data;
}

async function save() {
    saving.value = true;
    error.value = '';
    notice.value = '';

    try {
        if (form.id) {
            await dataSourceApi.update(form.id, payload());
            notice.value = '数据源已更新';
        } else {
            await dataSourceApi.create(payload());
            notice.value = '数据源已创建';
        }
        resetForm();
        await load();
    } catch (exception) {
        error.value = exception.message ?? '保存失败';
    } finally {
        saving.value = false;
    }
}

async function remove(row) {
    if (!window.confirm(`确认删除数据源 ${row.name}？`)) {
        return;
    }

    await dataSourceApi.remove(row.id);
    if (selected.value?.id === row.id) {
        selected.value = null;
        tables.value = [];
        fields.value = [];
    }
    await load();
}

async function test(row) {
    error.value = '';
    notice.value = '';

    try {
        const result = await dataSourceApi.test(row.id);
        notice.value = `连接测试：${result.data.status ?? 'completed'}`;
        await load();
    } catch (exception) {
        error.value = exception.message ?? '测试失败';
    }
}

async function sync(row) {
    error.value = '';
    notice.value = '';

    try {
        await dataSourceApi.sync(row.id);
        notice.value = '元数据同步完成';
        await load();
        await select(row);
    } catch (exception) {
        error.value = exception.message ?? '同步失败';
    }
}

async function select(row) {
    selected.value = row;
    selectedTable.value = '';
    fields.value = [];

    try {
        const result = await dataSourceApi.tables(row.id);
        tables.value = result.data ?? [];
    } catch (exception) {
        error.value = exception.message ?? '表列表加载失败';
    }
}

async function loadFields(tableName) {
    selectedTable.value = tableName;

    try {
        const result = await dataSourceApi.fields(selected.value.id, tableName);
        fields.value = result.data ?? [];
    } catch (exception) {
        error.value = exception.message ?? '字段加载失败';
    }
}

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <PageHeader title="数据源管理" subtitle="配置 MySQL 数据源，执行连接测试、元数据同步并查看表字段。">
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
                    <h3>{{ form.id ? '编辑数据源' : '新增数据源' }}</h3>
                    <StatusBadge :value="form.status" />
                </div>
                <div class="form-grid two">
                    <label><span>名称</span><input v-model="form.name" required></label>
                    <label><span>类型</span><select v-model="form.type"><option value="mysql">mysql</option></select></label>
                    <label><span>主机</span><input v-model="form.host" required></label>
                    <label><span>端口</span><input v-model.number="form.port" type="number" min="1" max="65535" required></label>
                    <label><span>数据库</span><input v-model="form.database_name" required></label>
                    <label><span>用户名</span><input v-model="form.username" required></label>
                    <label><span>密码</span><input v-model="form.password" type="password" :placeholder="form.id ? '留空则不更新' : ''"></label>
                    <label><span>状态</span><select v-model="form.status"><option value="active">active</option><option value="disabled">disabled</option></select></label>
                    <label><span>字符集</span><input v-model="form.charset"></label>
                    <label><span>时区</span><input v-model="form.timezone"></label>
                </div>
                <label class="full-row">
                    <span>连接选项 JSON</span>
                    <JsonTextarea v-model="form.options_json" :rows="5" @invalid="jsonError = $event" />
                    <small v-if="jsonError" class="form-error">{{ jsonError }}</small>
                </label>
                <div class="button-row">
                    <button class="primary-button" type="submit" :disabled="saving || Boolean(jsonError)">
                        <Save :size="16" />
                        <span>{{ saving ? '保存中...' : '保存' }}</span>
                    </button>
                    <button class="tool-button" type="button" @click="resetForm">清空</button>
                </div>
            </form>

            <section class="surface">
                <div class="section-heading">
                    <h3>数据源列表</h3>
                    <span class="muted">{{ rows.length }} 个</span>
                </div>
                <DataTable :columns="columns" :rows="rows" :loading="loading">
                    <template #cell-status="{ value }">
                        <StatusBadge :value="value" />
                    </template>
                    <template #cell-last_test_result="{ value }">
                        <StatusBadge :value="value?.status ?? value ?? '-'" />
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="row-actions">
                            <button class="icon-button" type="button" title="编辑" @click="edit(row)"><Database :size="16" /></button>
                            <button class="icon-button" type="button" title="测试连接" @click="test(row)"><Play :size="16" /></button>
                            <button class="icon-button" type="button" title="同步元数据" @click="sync(row)"><RefreshCw :size="16" /></button>
                            <button class="icon-button danger" type="button" title="删除" @click="remove(row)"><Trash2 :size="16" /></button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </section>

        <section class="surface">
            <div class="section-heading">
                <h3>{{ selectedTitle }}</h3>
                <span v-if="selectedTable" class="muted">当前表：{{ selectedTable }}</span>
            </div>
            <div class="split-grid">
                <DataTable :columns="tableColumns" :rows="tables">
                    <template #cell-actions="{ row }">
                        <button class="tool-button compact" type="button" @click="loadFields(row.table_name)">字段</button>
                    </template>
                </DataTable>
                <DataTable :columns="fieldColumns" :rows="fields">
                    <template #cell-is_nullable="{ value }">
                        <StatusBadge :value="value ? 'yes' : 'no'" />
                    </template>
                    <template #cell-is_primary_key="{ value }">
                        <StatusBadge :value="value ? 'pk' : 'no'" />
                    </template>
                </DataTable>
            </div>
        </section>
    </div>
</template>
