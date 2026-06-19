<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import {
    DatasetComponent,
    GridComponent,
    LegendComponent,
    TitleComponent,
    TooltipComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
    BarChart,
    LineChart,
    PieChart,
    DatasetComponent,
    GridComponent,
    LegendComponent,
    TitleComponent,
    TooltipComponent,
    CanvasRenderer,
]);

const props = defineProps({
    type: { type: String, default: 'bar' },
    data: { type: Object, default: () => ({ columns: [], rows: [] }) },
    styleConfig: { type: Object, default: () => ({}) },
});

const el = ref(null);
let chart = null;

const columns = computed(() => props.data.columns ?? props.data?.meta?.columns ?? []);
const rows = computed(() => props.data.rows ?? props.data?.data?.rows ?? []);
const dimensionKey = computed(() => columns.value[0]?.field ?? columns.value[0]?.name ?? columns.value[0]?.key);
const metricKey = computed(() => columns.value[1]?.field ?? columns.value[1]?.name ?? columns.value[1]?.key ?? dimensionKey.value);

const tableColumns = computed(() => columns.value.map((column) => ({
    key: column.field ?? column.name ?? column.key,
    label: column.label ?? column.display_name ?? column.field ?? column.name ?? column.key,
})));

const metricValue = computed(() => {
    const first = rows.value[0] ?? {};
    const key = metricKey.value ?? Object.keys(first)[0];

    return key ? first[key] : '-';
});

function buildOption() {
    const labels = rows.value.map((row, index) => row[dimensionKey.value] ?? `#${index + 1}`);
    const values = rows.value.map((row) => Number(row[metricKey.value] ?? 0));
    const title = props.styleConfig?.title ?? '';

    if (props.type === 'pie') {
        return {
            title: { text: title, left: 'center', textStyle: { fontSize: 14, fontWeight: 600 } },
            tooltip: { trigger: 'item' },
            legend: { bottom: 0, type: 'scroll' },
            series: [
                {
                    type: 'pie',
                    radius: ['38%', '68%'],
                    center: ['50%', '45%'],
                    data: labels.map((label, index) => ({ name: label, value: values[index] })),
                },
            ],
        };
    }

    return {
        title: { text: title, textStyle: { fontSize: 14, fontWeight: 600 } },
        tooltip: { trigger: 'axis' },
        grid: { left: 40, right: 20, top: 50, bottom: 40 },
        xAxis: { type: 'category', data: labels, axisLabel: { color: '#5f6b7a' } },
        yAxis: { type: 'value', axisLabel: { color: '#5f6b7a' } },
        series: [
            {
                type: props.type === 'line' ? 'line' : 'bar',
                smooth: props.type === 'line',
                data: values,
                itemStyle: { color: props.type === 'line' ? '#2f6fed' : '#1f8a70' },
                lineStyle: { width: 3 },
            },
        ],
    };
}

function render() {
    if (!el.value || ['metric_card', 'table'].includes(props.type)) {
        return;
    }

    if (!chart) {
        chart = echarts.init(el.value);
    }

    chart.setOption(buildOption(), true);
    chart.resize();
}

function resize() {
    chart?.resize();
}

onMounted(async () => {
    await nextTick();
    render();
    window.addEventListener('resize', resize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', resize);
    chart?.dispose();
});

watch(() => [props.type, props.data, props.styleConfig], () => nextTick(render), { deep: true });
</script>

<template>
    <div class="chart-renderer">
        <div v-if="type === 'metric_card'" class="metric-preview">
            <span>{{ styleConfig.title || '指标值' }}</span>
            <strong>{{ metricValue }}</strong>
        </div>
        <div v-else-if="type === 'table'" class="table-wrap compact-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th v-for="column in tableColumns" :key="column.key">{{ column.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in rows" :key="index">
                        <td v-for="column in tableColumns" :key="column.key">{{ row[column.key] ?? '-' }}</td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td :colspan="tableColumns.length || 1" class="table-state">暂无预览数据</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else ref="el" class="echarts-canvas" />
    </div>
</template>
