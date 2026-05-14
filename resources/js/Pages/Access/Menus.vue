<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import { apiGet, apiPut, errorMessage } from '../../api';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useI18n } from '../../i18n';

const { t } = useI18n();

const menus = ref([]);
const loading = ref(false);
const notice = ref('');
const error = ref('');
const editingMenu = ref(null);

const form = reactive({
    title: '',
    path: '',
    sort_order: 0,
    is_visible: true,
});

const menuTree = computed(() => {
    const byParent = new Map();

    menus.value.forEach((menu) => {
        const key = menu.parent_id ?? 0;
        byParent.set(key, [...(byParent.get(key) ?? []), menu]);
    });

    const walk = (parentId = 0, depth = 0) => (byParent.get(parentId) ?? []).flatMap((menu) => [
        { ...menu, depth },
        ...walk(menu.id, depth + 1),
    ]);

    return walk();
});

async function loadMenus() {
    loading.value = true;
    error.value = '';

    try {
        const payload = await apiGet('/api/v1/permissions/catalog');
        menus.value = payload.data.menus;
    } catch (requestError) {
        error.value = errorMessage(requestError);
    } finally {
        loading.value = false;
    }
}

function editMenu(menu) {
    editingMenu.value = menu;
    form.title = menu.title;
    form.path = menu.path;
    form.sort_order = menu.sort_order ?? 0;
    form.is_visible = Boolean(menu.is_visible);
}

function resetForm() {
    editingMenu.value = null;
    form.title = '';
    form.path = '';
    form.sort_order = 0;
    form.is_visible = true;
}

async function saveMenu() {
    if (! editingMenu.value) {
        return;
    }

    error.value = '';
    notice.value = '';

    try {
        await apiPut(`/api/v1/menus/${editingMenu.value.id}`, {
            title: form.title,
            path: form.path,
            sort_order: Number(form.sort_order),
            is_visible: form.is_visible,
        });

        notice.value = t('message.saved');
        resetForm();
        await loadMenus();
    } catch (requestError) {
        error.value = errorMessage(requestError);
    }
}

onMounted(loadMenus);
</script>

<template>
    <Head :title="t('page.menus')" />

    <AdminLayout>
        <div class="grid gap-4 xl:grid-cols-[1fr_340px]">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-950">{{ t('page.menus') }}</h2>
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" @click="loadMenus">
                        {{ t('action.refresh') }}
                    </button>
                </div>
                <div v-if="notice" class="border-b border-emerald-100 bg-emerald-50 px-5 py-3 text-sm text-emerald-700">{{ notice }}</div>
                <div v-if="error" class="border-b border-red-100 bg-red-50 px-5 py-3 text-sm text-red-700">{{ error }}</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.title') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.path') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.permission') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('field.sort_order') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">{{ t('table.visible') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">{{ t('table.action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="loading">
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('message.loading') }}</td>
                            </tr>
                            <tr v-else-if="menuTree.length === 0">
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-slate-500">{{ t('empty.no_rows') }}</td>
                            </tr>
                            <tr v-for="menu in menuTree" v-else :key="menu.id">
                                <td class="px-5 py-4 text-sm font-medium text-slate-950">
                                    <span :style="{ paddingLeft: `${menu.depth * 20}px` }">{{ menu.title }}</span>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ menu.path }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ menu.permission ?? '-' }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ menu.sort_order }}</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ menu.is_visible ? t('boolean.yes') : t('boolean.no') }}</td>
                                <td class="px-5 py-4 text-right text-sm">
                                    <button class="font-medium text-blue-700 hover:text-blue-800" @click="editMenu(menu)">
                                        {{ t('action.edit') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-950">{{ t('page.menus') }}</h3>
                <div class="mt-4 grid gap-3">
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('table.title') }}</span>
                        <input v-model="form.title" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('table.path') }}</span>
                        <input v-model="form.path" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">{{ t('field.sort_order') }}</span>
                        <input v-model="form.sort_order" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_visible" type="checkbox">
                        {{ t('table.visible') }}
                    </label>
                </div>
                <div class="mt-5 flex gap-2">
                    <button
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="!editingMenu"
                        @click="saveMenu"
                    >
                        {{ t('action.update') }}
                    </button>
                    <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="resetForm">
                        {{ t('action.cancel') }}
                    </button>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
