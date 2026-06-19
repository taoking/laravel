<script setup>
import { onMounted, ref } from 'vue';
import { Activity, RefreshCw } from '@lucide/vue';

import PageHeader from '../components/PageHeader.vue';
import StatusBadge from '../components/StatusBadge.vue';
import { monitorApi } from '../services/api';

const health = ref(null);
const checks = ref({});
const metrics = ref('');
const loading = ref(false);
const error = ref('');

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [healthResult, database, redis, storage, queue, metricsResult] = await Promise.all([
            monitorApi.health(),
            monitorApi.database(),
            monitorApi.redis(),
            monitorApi.storage(),
            monitorApi.queue(),
            monitorApi.metrics(),
        ]);

        health.value = healthResult.data;
        checks.value = {
            database: database.data,
            redis: redis.data,
            storage: storage.data,
            queue: queue.data,
        };
        metrics.value = String(metricsResult);
    } catch (exception) {
        error.value = exception.message ?? '监控数据加载失败';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="page-stack">
        <PageHeader title="系统监控" subtitle="展示数据库、Redis、存储、队列健康检查和 Prometheus 指标。">
            <button class="primary-button" type="button" @click="load">
                <RefreshCw :size="16" />
                <span>{{ loading ? '刷新中...' : '刷新' }}</span>
            </button>
        </PageHeader>

        <p v-if="error" class="form-error">{{ error }}</p>

        <section class="surface">
            <div class="section-heading">
                <h3>整体状态</h3>
                <StatusBadge :value="health?.status ?? 'unknown'" />
            </div>
            <div class="health-grid">
                <article v-for="(check, key) in checks" :key="key" class="health-card">
                    <Activity :size="20" />
                    <div>
                        <strong>{{ key }}</strong>
                        <span>{{ check.elapsed_ms }} ms</span>
                    </div>
                    <StatusBadge :value="check.status" />
                    <p v-if="check.message" class="form-error">{{ check.message }}</p>
                </article>
            </div>
        </section>

        <section class="surface">
            <div class="section-heading">
                <h3>Prometheus Metrics</h3>
                <span class="muted">/api/metrics</span>
            </div>
            <pre class="code-block tall">{{ metrics || '暂无指标数据' }}</pre>
        </section>
    </div>
</template>
