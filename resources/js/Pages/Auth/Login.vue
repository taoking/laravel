<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from '../../i18n';

const { locale, locales, t } = useI18n();

const form = useForm({
    email: 'admin@example.com',
    password: 'password',
    remember: false,
});

const submit = () => {
    form.post('/login');
};
</script>

<template>
    <Head :title="t('auth.login')" />

    <main class="min-h-screen bg-slate-50 px-4 py-10 text-slate-950">
        <div class="mx-auto flex max-w-5xl justify-end">
            <label class="sr-only" for="login-locale">{{ t('locale.label') }}</label>
            <select
                id="login-locale"
                v-model="locale"
                class="rounded-md border border-slate-300 bg-white px-2 py-2 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
                <option v-for="item in locales" :key="item.value" :value="item.value">
                    {{ item.label }}
                </option>
            </select>
        </div>
        <section class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-5xl items-center gap-8 md:grid-cols-[1fr_420px]">
            <div class="space-y-6">
                <div>
                    <p class="text-sm font-semibold uppercase text-blue-700">Laravel 13</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-normal text-slate-950 sm:text-4xl">
                        {{ t('login.title') }}
                    </h1>
                    <p class="mt-4 max-w-xl text-base leading-7 text-slate-600">
                        {{ t('login.desc') }}
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="text-sm font-semibold text-slate-950">{{ t('login.card.php') }}</div>
                        <div class="mt-1 text-xs leading-5 text-slate-500">{{ t('login.card.php_desc') }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="text-sm font-semibold text-slate-950">{{ t('login.card.laravel') }}</div>
                        <div class="mt-1 text-xs leading-5 text-slate-500">{{ t('login.card.laravel_desc') }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="text-sm font-semibold text-slate-950">{{ t('login.card.interview') }}</div>
                        <div class="mt-1 text-xs leading-5 text-slate-500">{{ t('login.card.interview_desc') }}</div>
                    </div>
                </div>
            </div>

            <form class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">{{ t('auth.sign_in') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ t('auth.welcome') }}</p>
                </div>

                <label class="mt-6 block">
                    <span class="text-sm font-medium text-slate-700">{{ t('auth.email') }}</span>
                    <input
                        type="email"
                        v-model="form.email"
                        class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950 shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                    <span v-if="form.errors.email" class="mt-1 block text-xs text-red-600">{{ form.errors.email }}</span>
                </label>

                <label class="mt-4 block">
                    <span class="text-sm font-medium text-slate-700">{{ t('auth.password') }}</span>
                    <input
                        type="password"
                        v-model="form.password"
                        class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950 shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                    <span v-if="form.errors.password" class="mt-1 block text-xs text-red-600">{{ form.errors.password }}</span>
                </label>

                <label class="mt-4 flex items-center gap-2 text-sm text-slate-600">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    >
                    {{ t('auth.remember') }}
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-6 w-full rounded-md bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                    :class="form.processing ? 'cursor-not-allowed opacity-70' : ''"
                >
                    {{ t('auth.submit') }}
                </button>

                <div class="mt-4 flex items-center justify-between text-sm">
                    <a class="font-medium text-blue-700 hover:text-blue-800" href="/admin">{{ t('auth.dashboard') }}</a>
                    <a class="font-medium text-blue-700 hover:text-blue-800" href="/docs/api">{{ t('auth.docs') }}</a>
                </div>
            </form>
        </section>
    </main>
</template>
