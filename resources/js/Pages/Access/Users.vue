<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import { apiDelete, apiGet, apiPost, apiPut, errorMessage } from '../../api';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useI18n } from '../../i18n';

const { t } = useI18n();

const users = ref([]);
const roles = ref([]);
const meta = ref({ total: 0, current_page: 1, per_page: 10 });
const loading = ref(false);
const notice = ref('');
const error = ref('');
const editingUser = ref(null);

const filters = reactive({
    keyword: '',
    status: '',
});

const form = reactive({
    name: '',
    email: '',
    password: '',
    status: 'active',
    role_ids: [],
});

function resetForm() {
    editingUser.value = null;
    form.name = '';
    form.email = '';
    form.password = '';
    form.status = 'active';
    form.role_ids = [];
}

function editUser(user) {
    editingUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.status = user.status;
    form.role_ids = (user.roles ?? []).map((role) => String(role.id));
}

function userPayload() {
    const payload = {
        name: form.name,
        email: form.email,
        status: form.status,
        role_ids: form.role_ids.map((id) => Number(id)),
    };

    if (form.password) {
        payload.password = form.password;
    }

    return payload;
}

async function loadUsers(page = 1) {
    loading.value = true;
    error.value = '';

    try {
        const payload = await apiGet('/api/v1/users', {
            keyword: filters.keyword,
            status: filters.status,
            page,
            per_page: meta.value.per_page,
        });

        users.value = payload.data;
        meta.value = payload.meta;
    } catch (requestError) {
        error.value = errorMessage(requestError);
    } finally {
        loading.value = false;
    }
}

async function loadRoles() {
    const payload = await apiGet('/api/v1/roles');

    roles.value = payload.data;
}

async function saveUser() {
    error.value = '';
    notice.value = '';

    try {
        if (editingUser.value) {
            await apiPut(`/api/v1/users/${editingUser.value.id}`, userPayload());
        } else {
            await apiPost('/api/v1/users', userPayload());
        }

        notice.value = t('message.saved');
        resetForm();
        await loadUsers(meta.value.current_page);
    } catch (requestError) {
        error.value = errorMessage(requestError);
    }
}

async function deleteUser(user) {
    if (! window.confirm(t('message.confirm_delete'))) {
        return;
    }

    error.value = '';
    notice.value = '';

    try {
        await apiDelete(`/api/v1/users/${user.id}`);
        notice.value = t('message.deleted');
        await loadUsers(meta.value.current_page);
    } catch (requestError) {
        error.value = errorMessage(requestError);
    }
}

onMounted(async () => {
    await Promise.all([loadUsers(), loadRoles()]);
});
</script>

<template>
    <Head :title="t('page.users')" />

    <AdminLayout>
        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">{{ t('page.users') }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ t('message.total_count', { total: meta.total ?? 0 }) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <input
                            v-model="filters.keyword"
                            class="w-48 rounded-md border border-slate-300 px-3 py-2 text-sm"
                            :placeholder="t('field.keyword')"
                        >
                        <select v-model="filters.status" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                            <option value="">{{ t('field.status') }}</option>
                            <option value="active">{{ t('status.active') }}</option>
                            <option value="disabled">{{ t('status.disabled') }}</option>
                        </select>
                        <button class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white" @click="loadUsers(1)">
                            {{ t('action.search') }}
                        </button>
                    </div>
                </div>

                <div v-if="notice" class="border-b border-emerald-100 bg-emerald-50 px-5 py-3 text-sm text-emerald-700">{{ notice }}</div>
                <div v-if="error" class="border-b border-red-100 bg-red-50 px-5 py-3 text-sm text-red-700">{{ error }}</div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.name') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.email') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.status') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.roles') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">{{ t('table.action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="loading">
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('message.loading') }}</td>
                            </tr>
                            <tr v-else-if="users.length === 0">
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('empty.no_rows') }}</td>
                            </tr>
                            <tr v-for="user in users" v-else :key="user.id">
                                <td class="px-5 py-4 text-sm font-medium text-slate-950">{{ user.name }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ user.email }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ t(`status.${user.status}`) }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ (user.roles ?? []).map((role) => role.name).join(', ') || '-' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm">
                                    <button class="font-medium text-blue-700 hover:text-blue-800" @click="editUser(user)">{{ t('action.edit') }}</button>
                                    <button class="ml-3 font-medium text-red-700 hover:text-red-800" @click="deleteUser(user)">{{ t('action.delete') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200 px-5 py-3 text-sm text-slate-600">
                    <button
                        class="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-50"
                        :disabled="(meta.current_page ?? 1) <= 1"
                        @click="loadUsers((meta.current_page ?? 1) - 1)"
                    >
                        ‹
                    </button>
                    <span>{{ meta.current_page ?? 1 }} / {{ Math.max(1, Math.ceil((meta.total ?? 0) / (meta.per_page ?? 10))) }}</span>
                    <button
                        class="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-50"
                        :disabled="(meta.current_page ?? 1) >= Math.ceil((meta.total ?? 0) / (meta.per_page ?? 10))"
                        @click="loadUsers((meta.current_page ?? 1) + 1)"
                    >
                        ›
                    </button>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-950">{{ editingUser ? t('form.edit_user') : t('form.new_user') }}</h3>
                <div class="mt-4 space-y-3">
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.name') }}</span>
                        <input v-model="form.name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.email') }}</span>
                        <input v-model="form.email" type="email" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.password') }}</span>
                        <input v-model="form.password" type="password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                        <span v-if="editingUser" class="mt-1 block text-xs text-slate-500">{{ t('form.optional_password') }}</span>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.status') }}</span>
                        <select v-model="form.status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                            <option value="active">{{ t('status.active') }}</option>
                            <option value="disabled">{{ t('status.disabled') }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.roles') }}</span>
                        <select v-model="form.role_ids" multiple class="mt-1 h-32 w-full rounded-md border border-slate-300 px-3 py-2">
                            <option v-for="role in roles" :key="role.id" :value="String(role.id)">{{ role.name }}</option>
                        </select>
                    </label>
                </div>
                <div class="mt-5 flex gap-2">
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="saveUser">
                        {{ editingUser ? t('action.update') : t('action.create') }}
                    </button>
                    <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="resetForm">
                        {{ t('action.cancel') }}
                    </button>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
