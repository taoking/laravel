import { defineStore } from 'pinia';

import { authApi } from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('bi_token') ?? '',
        user: JSON.parse(localStorage.getItem('bi_user') || 'null'),
        initialized: false,
    }),
    getters: {
        isAuthenticated: (state) => Boolean(state.token),
        displayName: (state) => state.user?.name ?? state.user?.email ?? '未登录',
    },
    actions: {
        persist(token, user) {
            this.token = token;
            this.user = user;
            localStorage.setItem('bi_token', token);
            localStorage.setItem('bi_user', JSON.stringify(user));
        },
        clear() {
            this.token = '';
            this.user = null;
            localStorage.removeItem('bi_token');
            localStorage.removeItem('bi_user');
        },
        async login(payload) {
            const result = await authApi.login(payload);
            this.persist(result.data.token, result.data.user);

            return result.data.user;
        },
        async loadUser() {
            if (!this.token) {
                this.initialized = true;
                return null;
            }

            try {
                const result = await authApi.me();
                this.user = result.data;
                localStorage.setItem('bi_user', JSON.stringify(result.data));
                return result.data;
            } catch (error) {
                this.clear();
                return null;
            } finally {
                this.initialized = true;
            }
        },
        async logout() {
            try {
                if (this.token) {
                    await authApi.logout();
                }
            } finally {
                this.clear();
            }
        },
    },
});
