<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import { apiDelete, apiGet, apiPost, apiPut, errorMessage } from '../../api';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useI18n } from '../../i18n';

const { t } = useI18n();

const roles = ref([]);
const permissions = ref([]);
const loading = ref(false);
const notice = ref('');
const error = ref('');
const editingRole = ref(null);

const form = reactive({
    name: '',
    code: '',
    data_scope: 'self',
    description: '',
    permission_ids: [],
});

function resetForm() {
    editingRole.value = null;
    form.name = '';
    form.code = '';
    form.data_scope = 'self';
    form.description = '';
    form.permission_ids = [];
}

function editRole(role) {
    editingRole.value = role;
    form.name = role.name;
    form.code = role.code;
    form.data_scope = role.data_scope;
    form.description = role.description ?? '';
    form.permission_ids = (role.permissions ?? []).map((permission) => String(permission.id));
}

function rolePayload() {
    return {
        name: form.name,
        code: form.code,
        data_scope: form.data_scope,
        description: form.description,
    };
}

async function loadRoles() {
    loading.value = true;
    error.value = '';

    try {
        const payload = await apiGet('/api/v1/roles');
        roles.value = payload.data;
    } catch (requestError) {
        error.value = errorMessage(requestError);
    } finally {
        loading.value = false;
    }
}

async function loadCatalog() {
    const payload = await apiGet('/api/v1/permissions/catalog');

    permissions.value = payload.data.permissions;
}

async function saveRole() {
    error.value = '';
    notice.value = '';

    try {
        let role = editingRole.value;

        if (role) {
            const payload = await apiPut(`/api/v1/roles/${role.id}`, rolePayload());
            role = payload.data.role;
        } else {
            const payload = await apiPost('/api/v1/roles', {
                ...rolePayload(),
                permission_ids: form.permission_ids.map((id) => Number(id)),
            });
            role = payload.data.role;
        }

        if (role.code !== 'super_admin') {
            await apiPut(`/api/v1/roles/${role.id}/permissions`, {
                permission_ids: form.permission_ids.map((id) => Number(id)),
            });
        }

        notice.value = t('message.saved');
        resetForm();
        await loadRoles();
    } catch (requestError) {
        error.value = errorMessage(requestError);
    }
}

async function deleteRole(role) {
    if (! window.confirm(t('message.confirm_delete'))) {
        return;
    }

    error.value = '';
    notice.value = '';

    try {
        await apiDelete(`/api/v1/roles/${role.id}`);
        notice.value = t('message.deleted');
        await loadRoles();
    } catch (requestError) {
        error.value = errorMessage(requestError);
    }
}

onMounted(async () => {
    await Promise.all([loadRoles(), loadCatalog()]);
});
</script>

<template>
    <Head :title="t('page.roles')" />

    <AdminLayout>
        <div class="grid gap-4 xl:grid-cols-[1fr_420px]">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-950">{{ t('page.roles') }}</h2>
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" @click="loadRoles">
                        {{ t('action.refresh') }}
                    </button>
                </div>
                <div v-if="notice" class="border-b border-emerald-100 bg-emerald-50 px-5 py-3 text-sm text-emerald-700">{{ notice }}</div>
                <div v-if="error" class="border-b border-red-100 bg-red-50 px-5 py-3 text-sm text-red-700">{{ error }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.role') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.code') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.data_scope') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.system') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">{{ t('table.action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="loading">
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('message.loading') }}</td>
                            </tr>
                            <tr v-else-if="roles.length === 0">
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('empty.no_rows') }}</td>
                            </tr>
                            <tr v-for="role in roles" v-else :key="role.id">
                                <td class="px-5 py-4 text-sm font-medium text-slate-950">{{ role.name }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ role.code }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ t(`data_scope.${role.data_scope}`) }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ role.is_system ? t('boolean.yes') : t('boolean.no') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm">
                                    <button class="font-medium text-blue-700 hover:text-blue-800" @click="editRole(role)">{{ t('action.edit') }}</button>
                                    <button
                                        class="ml-3 font-medium text-red-700 hover:text-red-800 disabled:text-slate-300"
                                        :disabled="role.is_system"
                                        @click="deleteRole(role)"
                                    >
                                        {{ t('action.delete') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-950">{{ editingRole ? t('form.edit_role') : t('form.new_role') }}</h3>
                <div class="mt-4 grid gap-3">
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.name') }}</span>
                        <input v-model="form.name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.code') }}</span>
                        <input v-model="form.code" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.data_scope') }}</span>
                        <select v-model="form.data_scope" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            <option value="all">{{ t('data_scope.all') }}</option>
                            <option value="department">{{ t('data_scope.department') }}</option>
                            <option value="self">{{ t('data_scope.self') }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.description') }}</span>
                        <textarea v-model="form.description" class="mt-1 min-h-20 w-full rounded-md border border-slate-300 px-3 py-2"></textarea>
                    </label>
                    <fieldset class="rounded-md border border-slate-200 p-3">
                        <legend class="px-1 text-sm font-medium text-slate-700">{{ t('field.permissions') }}</legend>
                        <div class="mt-2 grid max-h-64 gap-2 overflow-y-auto text-sm">
                            <label v-for="permission in permissions" :key="permission.id" class="flex items-start gap-2 text-slate-700">
                                <input v-model="form.permission_ids" type="checkbox" :value="String(permission.id)" class="mt-1">
                                <span>
                                    <span class="font-medium">{{ permission.name }}</span>
                                    <span class="block text-xs text-slate-500">{{ permission.code }}</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>
                </div>
                <div class="mt-5 flex gap-2">
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="saveRole">
                        {{ editingRole ? t('action.update') : t('action.create') }}
                    </button>
                    <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="resetForm">
                        {{ t('action.cancel') }}
                    </button>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
