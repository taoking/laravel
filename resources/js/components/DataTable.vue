<script setup>
defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    emptyText: { type: String, default: '暂无数据' },
});
</script>

<template>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th v-for="column in columns" :key="column.key">
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="loading">
                    <td :colspan="columns.length" class="table-state">加载中...</td>
                </tr>
                <tr v-else-if="rows.length === 0">
                    <td :colspan="columns.length" class="table-state">{{ emptyText }}</td>
                </tr>
                <tr v-for="row in rows" v-else :key="row.id ?? JSON.stringify(row)">
                    <td v-for="column in columns" :key="column.key">
                        <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                            {{ row[column.key] ?? '-' }}
                        </slot>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
