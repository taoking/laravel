function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function queryString(params = {}) {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
            return;
        }

        search.append(key, value);
    });

    const value = search.toString();

    return value ? `?${value}` : '';
}

async function parseResponse(response) {
    const text = await response.text();

    if (!text) {
        return {};
    }

    try {
        return JSON.parse(text);
    } catch {
        return { message: text };
    }
}

async function request(method, url, options = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...options.headers,
    };

    let body;

    if (options.formData) {
        body = options.formData;
    } else if (options.data !== undefined) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(options.data);
    }

    if (method !== 'GET') {
        headers['X-CSRF-TOKEN'] = csrfToken();
    }

    const response = await fetch(url, {
        method,
        headers,
        body,
    });
    const payload = await parseResponse(response);

    if (!response.ok) {
        const error = new Error(payload.message ?? 'Request failed.');
        error.status = response.status;
        error.errors = payload.errors ?? {};
        error.payload = payload;
        throw error;
    }

    return payload;
}

export function apiGet(url, params = {}) {
    return request('GET', `${url}${queryString(params)}`);
}

export function apiPost(url, data = {}) {
    return request('POST', url, { data });
}

export function apiPut(url, data = {}) {
    return request('PUT', url, { data });
}

export function apiDelete(url) {
    return request('DELETE', url);
}

export function apiUpload(url, formData, headers = {}) {
    return request('POST', url, { formData, headers });
}

export function errorMessage(error) {
    const firstField = Object.values(error.errors ?? {})[0];

    if (Array.isArray(firstField) && firstField.length > 0) {
        return firstField[0];
    }

    return error.message ?? 'Request failed.';
}
