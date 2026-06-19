import axios from 'axios';

export const http = axios.create({
    baseURL: '/api',
    timeout: 30000,
    headers: {
        Accept: 'application/json',
    },
});

http.interceptors.request.use((config) => {
    const token = localStorage.getItem('bi_token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

http.interceptors.response.use(
    (response) => response.data,
    (error) => {
        const response = error.response;
        const payload = response?.data ?? {};
        const normalized = {
            status: response?.status ?? 0,
            code: payload.code ?? response?.status ?? 0,
            message: payload.message ?? error.message ?? 'Request failed',
            errors: payload.errors ?? {},
        };

        if (normalized.status === 401) {
            window.dispatchEvent(new CustomEvent('auth:expired'));
        }

        return Promise.reject(normalized);
    },
);

export function itemsFrom(result) {
    const data = result?.data ?? result;

    if (Array.isArray(data)) {
        return data;
    }

    return data?.items ?? [];
}

export function paginationFrom(result) {
    return result?.data?.pagination ?? { page: 1, page_size: 20, total: 0 };
}
