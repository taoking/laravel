<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { LockKeyhole, LogIn } from '@lucide/vue';

import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const form = reactive({
    email: '',
    password: '',
    device_name: 'vue-admin',
});

const loading = ref(false);
const error = ref('');

async function submit() {
    loading.value = true;
    error.value = '';

    try {
        await auth.login(form);
        router.push(route.query.redirect || '/');
    } catch (exception) {
        error.value = exception.message || '登录失败';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main class="login-page">
        <section class="login-panel">
            <div class="login-brand">
                <div class="brand-mark">BI</div>
                <div>
                    <p class="eyebrow">Laravel BI Platform</p>
                    <h1>管理端登录</h1>
                </div>
            </div>

            <form class="login-form" @submit.prevent="submit">
                <label>
                    <span>邮箱</span>
                    <input v-model="form.email" type="email" autocomplete="email" required placeholder="admin@example.com">
                </label>
                <label>
                    <span>密码</span>
                    <input v-model="form.password" type="password" autocomplete="current-password" required>
                </label>
                <label>
                    <span>设备名称</span>
                    <input v-model="form.device_name" type="text">
                </label>
                <p v-if="error" class="form-error">{{ error }}</p>
                <button class="primary-button full-width" type="submit" :disabled="loading">
                    <LogIn :size="18" />
                    <span>{{ loading ? '登录中...' : '登录' }}</span>
                </button>
            </form>
        </section>

        <section class="login-side">
            <LockKeyhole :size="44" />
            <h2>Token 认证的 BI 管理工作台</h2>
            <p>登录后可管理数据源、数据集、图表、仪表盘、导入导出任务、权限和系统健康状态。</p>
        </section>
    </main>
</template>
