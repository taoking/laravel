<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Plus, RefreshCw, Save, Shield, Trash2 } from '@lucide/vue';

import DataTable from '../components/DataTable.vue';
import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { permissionApi } from '../services/api';
import { itemsFrom } from '../services/http';

const tabs = [
    { key: 'users', label: '用户' },
    { key: 'roles', label: '角色' },
    { key: 'permissions', label: '权限' },
    { key: 'resources', label: '资源权限' },
    { key: 'dataRules', label: '行级规则' },
    { key: 'columnRules', label: '列级规则' },
];

const apiMap = {
    users: permissionApi.users,
    roles: permissionApi.roles,
    permissions: permissionApi.permissions,
    resources: permissionApi.resources,
    dataRules: permissionApi.dataRules,
    columnRules: permissionApi.columnRules,
};

const rows = reactive({
    users: [],
    roles: [],
    permissions: [],
    resources: [],
    dataRules: [],
    columnRules: [],
});

const active = ref('users');
const loading = ref(false);
const error = ref('');
const notice = ref('');

const form = reactive({
    id: null,
    name: '',
    email: '',
    password: '',
    status: 'active',
    role_ids: [],
    code: '',
    guard_name: 'sanctum',
    group: '',
    description: '',
    is_system: false,
    permission_ids: [],
    resource_type: 'dataset',
    resource_id: '',
    subject_type: 'role',
    subject_id: '',
    permission_type: 'view',
    dataset_id: '',
    field_name: '',
    operator: '=',
    value_type: 'static',
    value_json: '',
});

const columns = computed(() => ({
    users: [
        { key: 'name', label: '姓名' },
        { key: 'email', label: '邮箱' },
        { key: 'status', label: '状态' },
        { key: 'roles', label: '角色' },
        { key: 'actions', label: '操作' },
    ],
    roles: [
        { key: 'name', label: '名称' },
        { key: 'code', label: '编码' },
        { key: 'is_system', label: '系统角色' },
        { key: 'permissions', label: '权限数' },
        { key: 'actions', label: '操作' },
    ],
    permissions: [
        { key: 'name', label: '名称' },
        { key: 'code', label: '编码' },
        { key: 'group', label: '分组' },
        { key: 'actions', label: '操作' },
    ],
    resources: [
        { key: 'resource_type', label: '资源类型' },
        { key: 'resource_id', label: '资源 ID' },
        { key: 'subject_type', label: '主体类型' },
        { key: 'subject_id', label: '主体 ID' },
        { key: 'permission_type', label: '权限' },
        { key: 'actions', label: '操作' },
    ],
    dataRules: [
        { key: 'dataset_id', label: '数据集' },
        { key: 'subject_type', label: '主体类型' },
        { key: 'subject_id', label: '主体 ID' },
        { key: 'field_name', label: '字段' },
        { key: 'operator', label: '操作符' },
        { key: 'status', label: '状态' },
        { key: 'actions', label: '操作' },
    ],
    columnRules: [
        { key: 'dataset_id', label: '数据集' },
        { key: 'subject_type', label: '主体类型' },
        { key: 'subject_id', label: '主体 ID' },
        { key: 'field_name', label: '字段' },
        { key: 'permission_type', label: '列权限' },
        { key: 'actions', label: '操作' },
    ],
}[active.value]));

function resetForm() {
    Object.assign(form, {
        id: null,
        name: '',
        email: '',
        password: '',
        status: 'active',
        role_ids: [],
        code: '',
        guard_name: 'sanctum',
        group: '',
        description: '',
        is_system: false,
        permission_ids: [],
        resource_type: 'dataset',
        resource_id: '',
        subject_type: 'role',
        subject_id: '',
        permission_type: 'view',
        dataset_id: '',
        field_name: '',
        operator: '=',
        value_type: 'static',
        value_json: '',
    });
}

async function load(tab = active.value) {
    loading.value = true;
    error.value = '';

    try {
        const result = await apiMap[tab].list({ page_size: 100 });
        rows[tab] = itemsFrom(result);

        if (tab !== 'roles' && rows.roles.length === 0) {
            const rolesResult = await permissionApi.roles.list({ page_size: 100 });
            rows.roles = itemsFrom(rolesResult);
        }

        if (tab !== 'permissions' && rows.permissions.length === 0) {
            const permissionsResult = await permissionApi.permissions.list({ page_size: 100 });
            rows.permissions = itemsFrom(permissionsResult);
        }
    } catch (exception) {
        error.value = exception.message ?? '权限数据加载失败';
    } finally {
        loading.value = false;
    }
}

async function switchTab(tab) {
    active.value = tab;
    resetForm();
    await load(tab);
}

function edit(row) {
    resetForm();
    Object.assign(form, row);
    form.password = '';
    form.role_ids = row.roles?.map((role) => role.id) ?? [];
    form.permission_ids = row.permissions?.map((permission) => permission.id) ?? [];
    form.value_json = typeof row.value_json === 'string' ? row.value_json : JSON.stringify(row.value_json ?? '');
}

function payload() {
    if (active.value === 'users') {
        const data = {
            name: form.name,
            email: form.email,
            password: form.password,
            status: form.status,
            role_ids: form.role_ids.map(Number),
        };

        if (form.id && !form.password) delete data.password;
        return data;
    }

    if (active.value === 'roles') {
        return {
            name: form.name,
            code: form.code,
            guard_name: form.guard_name,
            description: form.description || null,
            is_system: Boolean(form.is_system),
            permission_ids: form.permission_ids.map(Number),
        };
    }

    if (active.value === 'permissions') {
        return {
            name: form.name,
            code: form.code,
            guard_name: form.guard_name,
            group: form.group || null,
            description: form.description || null,
        };
    }

    if (active.value === 'resources') {
        return {
            resource_type: form.resource_type,
            resource_id: Number(form.resource_id),
            subject_type: form.subject_type,
            subject_id: Number(form.subject_id),
            permission_type: form.permission_type,
        };
    }

    if (active.value === 'dataRules') {
        return {
            dataset_id: Number(form.dataset_id),
            subject_type: form.subject_type,
            subject_id: Number(form.subject_id),
            field_name: form.field_name,
            operator: form.operator,
            value_type: 'static',
            value_json: form.value_json,
            status: form.status,
        };
    }

    return {
        dataset_id: Number(form.dataset_id),
        subject_type: form.subject_type,
        subject_id: Number(form.subject_id),
        field_name: form.field_name,
        permission_type: form.permission_type,
    };
}

async function save() {
    error.value = '';
    notice.value = '';

    try {
        if (form.id) {
            await apiMap[active.value].update(form.id, payload());
            notice.value = '记录已更新';
        } else {
            await apiMap[active.value].create(payload());
            notice.value = '记录已创建';
        }
        resetForm();
        await load(active.value);
    } catch (exception) {
        error.value = exception.message ?? '保存失败';
    }
}

async function remove(row) {
    if (!window.confirm(`确认删除记录 ${row.id}？`)) {
        return;
    }

    await apiMap[active.value].remove(row.id);
    await load(active.value);
}

onMounted(() => load('users'));
</script>

<template>
    <div class="page-stack">
        <PageHeader title="权限管理" subtitle="管理用户、角色、功能权限，以及资源、行级、列级数据权限基础规则。">
            <button class="tool-button" type="button" @click="load(active)">
                <RefreshCw :size="16" />
                <span>刷新</span>
            </button>
            <button class="primary-button" type="button" @click="resetForm">
                <Plus :size="16" />
                <span>新增</span>
            </button>
        </PageHeader>

        <div class="tabs">
            <button v-for="tab in tabs" :key="tab.key" :class="{ active: active === tab.key }" type="button" @click="switchTab(tab.key)">
                {{ tab.label }}
            </button>
        </div>

        <p v-if="error" class="form-error">{{ error }}</p>
        <p v-if="notice" class="form-notice">{{ notice }}</p>

        <section class="work-grid">
            <form class="surface form-panel" @submit.prevent="save">
                <div class="section-heading">
                    <h3>编辑 {{ tabs.find((tab) => tab.key === active)?.label }}</h3>
                    <Shield :size="18" />
                </div>

                <div v-if="active === 'users'" class="form-grid two">
                    <label><span>姓名</span><input v-model="form.name" required></label>
                    <label><span>邮箱</span><input v-model="form.email" type="email" required></label>
                    <label><span>密码</span><input v-model="form.password" type="password" :required="!form.id"></label>
                    <label><span>状态</span><select v-model="form.status"><option value="active">active</option><option value="disabled">disabled</option></select></label>
                    <label class="full-row">
                        <span>角色</span>
                        <select v-model="form.role_ids" multiple>
                            <option v-for="role in rows.roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
                    </label>
                </div>

                <div v-else-if="active === 'roles'" class="form-grid two">
                    <label><span>名称</span><input v-model="form.name" required></label>
                    <label><span>编码</span><input v-model="form.code" required></label>
                    <label><span>Guard</span><input v-model="form.guard_name" required></label>
                    <label><span>系统角色</span><select v-model="form.is_system"><option :value="false">false</option><option :value="true">true</option></select></label>
                    <label class="full-row"><span>描述</span><textarea v-model="form.description" rows="3" /></label>
                    <label class="full-row">
                        <span>权限</span>
                        <select v-model="form.permission_ids" multiple>
                            <option v-for="permission in rows.permissions" :key="permission.id" :value="permission.id">{{ permission.name }} / {{ permission.code }}</option>
                        </select>
                    </label>
                </div>

                <div v-else-if="active === 'permissions'" class="form-grid two">
                    <label><span>名称</span><input v-model="form.name" required></label>
                    <label><span>编码</span><input v-model="form.code" required></label>
                    <label><span>Guard</span><input v-model="form.guard_name" required></label>
                    <label><span>分组</span><input v-model="form.group"></label>
                    <label class="full-row"><span>描述</span><textarea v-model="form.description" rows="3" /></label>
                </div>

                <div v-else-if="active === 'resources'" class="form-grid two">
                    <label><span>资源类型</span><select v-model="form.resource_type"><option value="data_source">data_source</option><option value="dataset">dataset</option><option value="chart">chart</option><option value="dashboard">dashboard</option></select></label>
                    <label><span>资源 ID</span><input v-model="form.resource_id" type="number" min="1" required></label>
                    <label><span>主体类型</span><select v-model="form.subject_type"><option value="user">user</option><option value="role">role</option><option value="department">department</option><option value="organization">organization</option></select></label>
                    <label><span>主体 ID</span><input v-model="form.subject_id" type="number" min="1" required></label>
                    <label><span>权限</span><select v-model="form.permission_type"><option value="view">view</option><option value="edit">edit</option><option value="delete">delete</option><option value="manage">manage</option></select></label>
                </div>

                <div v-else-if="active === 'dataRules'" class="form-grid two">
                    <label><span>数据集 ID</span><input v-model="form.dataset_id" type="number" min="1" required></label>
                    <label><span>字段</span><input v-model="form.field_name" required></label>
                    <label><span>主体类型</span><select v-model="form.subject_type"><option value="user">user</option><option value="role">role</option><option value="department">department</option><option value="organization">organization</option></select></label>
                    <label><span>主体 ID</span><input v-model="form.subject_id" type="number" min="1" required></label>
                    <label><span>操作符</span><select v-model="form.operator"><option value="=">=</option><option value="!=">!=</option><option value=">">&gt;</option><option value=">=">&gt;=</option><option value="<">&lt;</option><option value="<=">&lt;=</option><option value="in">in</option><option value="not_in">not_in</option><option value="like">like</option><option value="between">between</option><option value="is_null">is_null</option><option value="is_not_null">is_not_null</option></select></label>
                    <label><span>状态</span><select v-model="form.status"><option value="active">active</option><option value="disabled">disabled</option></select></label>
                    <label class="full-row"><span>值 JSON</span><textarea v-model="form.value_json" rows="3" /></label>
                </div>

                <div v-else class="form-grid two">
                    <label><span>数据集 ID</span><input v-model="form.dataset_id" type="number" min="1" required></label>
                    <label><span>字段</span><input v-model="form.field_name" required></label>
                    <label><span>主体类型</span><select v-model="form.subject_type"><option value="user">user</option><option value="role">role</option><option value="department">department</option><option value="organization">organization</option></select></label>
                    <label><span>主体 ID</span><input v-model="form.subject_id" type="number" min="1" required></label>
                    <label><span>列权限</span><select v-model="form.permission_type"><option value="visible">visible</option><option value="hidden">hidden</option><option value="masked">masked</option></select></label>
                </div>

                <div class="button-row">
                    <button class="primary-button" type="submit">
                        <Save :size="16" />
                        <span>保存</span>
                    </button>
                    <button class="tool-button" type="button" @click="resetForm">清空</button>
                </div>
            </form>

            <section class="surface">
                <div class="section-heading">
                    <h3>列表</h3>
                    <span class="muted">{{ rows[active].length }} 条</span>
                </div>
                <DataTable :columns="columns" :rows="rows[active]" :loading="loading">
                    <template #cell-status="{ value }"><StatusBadge :value="value" /></template>
                    <template #cell-roles="{ value }">{{ value?.map((role) => role.name).join(', ') || '-' }}</template>
                    <template #cell-permissions="{ value }">{{ Array.isArray(value) ? value.length : '-' }}</template>
                    <template #cell-is_system="{ value }"><StatusBadge :value="value ? 'yes' : 'no'" /></template>
                    <template #cell-actions="{ row }">
                        <div class="row-actions">
                            <button class="icon-button" type="button" title="编辑" @click="edit(row)"><Shield :size="16" /></button>
                            <button class="icon-button danger" type="button" title="删除" @click="remove(row)"><Trash2 :size="16" /></button>
                        </div>
                    </template>
                </DataTable>
            </section>
        </section>
    </div>
</template>
