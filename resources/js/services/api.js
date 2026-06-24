import { http } from './http';

function list(path, params = {}) {
    return http.get(path, { params });
}

function resource(path) {
    return {
        list: (params) => list(path, params),
        show: (id) => http.get(`${path}/${id}`),
        create: (payload) => http.post(path, payload),
        update: (id, payload) => http.put(`${path}/${id}`, payload),
        remove: (id) => http.delete(`${path}/${id}`),
    };
}

export const authApi = {
    login: (payload) => http.post('/auth/login', payload),
    me: () => http.get('/auth/me'),
    logout: () => http.post('/auth/logout'),
};

export const dataSourceApi = {
    ...resource('/data-sources'),
    test: (id) => http.post(`/data-sources/${id}/test`),
    sync: (id) => http.post(`/data-sources/${id}/sync`),
    databases: (id) => http.get(`/data-sources/${id}/databases`),
    tables: (id) => http.get(`/data-sources/${id}/tables`),
    views: (id) => http.get(`/data-sources/${id}/views`),
    fields: (id, table) => http.get(`/data-sources/${id}/tables/${encodeURIComponent(table)}/fields`),
    previewTable: (id, table, payload = {}) => http.post(`/data-sources/${id}/tables/${encodeURIComponent(table)}/preview`, payload),
    materializedViews: (id) => http.get(`/data-sources/${id}/materialized-views`),
    materializedView: (id, name) => http.get(`/data-sources/${id}/materialized-views/${encodeURIComponent(name)}`),
    refreshMaterializedView: (id, name) => http.post(`/data-sources/${id}/materialized-views/${encodeURIComponent(name)}/refresh`),
};

export const datasetApi = {
    ...resource('/datasets'),
    syncFields: (id) => http.post(`/datasets/${id}/sync-fields`),
    fields: (id) => http.get(`/datasets/${id}/fields`),
    updateField: (datasetId, fieldId, payload) => http.put(`/datasets/${datasetId}/fields/${fieldId}`, payload),
    preview: (id, payload) => http.post(`/datasets/${id}/preview`, payload),
    explain: (id, payload) => http.post(`/datasets/${id}/explain`, payload),
};

export const chartApi = {
    ...resource('/charts'),
    preview: (payload) => http.post('/charts/preview', payload),
    data: (id, payload) => http.post(`/charts/${id}/data`, payload),
    explain: (id, payload) => http.post(`/charts/${id}/explain`, payload),
};

export const semanticApi = {
    categories: resource('/metric-categories'),
    metrics: {
        ...resource('/semantic-metrics'),
        activate: (id) => http.post(`/semantic-metrics/${id}/activate`),
        deprecate: (id) => http.post(`/semantic-metrics/${id}/deprecate`),
        archive: (id) => http.post(`/semantic-metrics/${id}/archive`),
        versions: (id) => http.get(`/semantic-metrics/${id}/versions`),
        dependencies: (id) => http.get(`/semantic-metrics/${id}/dependencies`),
        usages: (id) => http.get(`/semantic-metrics/${id}/usages`),
        impact: (id) => http.get(`/semantic-metrics/${id}/impact`),
        validateFormula: (payload) => http.post('/semantic-metrics/validate-formula', payload),
    },
    dimensions: {
        ...resource('/dimensions'),
        dataset: (datasetId) => http.get(`/datasets/${datasetId}/dimensions`),
        initFromFields: (datasetId) => http.post(`/datasets/${datasetId}/dimensions/init-from-fields`),
    },
    datasetMetrics: (datasetId) => http.get(`/datasets/${datasetId}/metrics`),
    initMetricsFromFields: (datasetId) => http.post(`/datasets/${datasetId}/metrics/init-from-fields`),
    semanticLayer: (datasetId) => http.get(`/datasets/${datasetId}/semantic-layer`),
};

export const dashboardApi = {
    ...resource('/dashboards'),
    createWidget: (dashboardId, payload) => http.post(`/dashboards/${dashboardId}/widgets`, payload),
    updateWidget: (dashboardId, widgetId, payload) => http.put(`/dashboards/${dashboardId}/widgets/${widgetId}`, payload),
    removeWidget: (dashboardId, widgetId) => http.delete(`/dashboards/${dashboardId}/widgets/${widgetId}`),
    data: (dashboardId, payload) => http.post(`/dashboards/${dashboardId}/data`, payload),
    share: (dashboardId, payload) => http.post(`/dashboards/${dashboardId}/share`, payload),
};

export const importTaskApi = {
    ...resource('/import-tasks'),
    upload: (file) => {
        const form = new FormData();
        form.append('file', file);

        return http.post('/import-tasks', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
    retry: (id) => http.post(`/import-tasks/${id}/retry`),
};

export const exportTaskApi = {
    ...resource('/export-tasks'),
    retry: (id) => http.post(`/export-tasks/${id}/retry`),
    downloadUrl: (id) => `/api/export-tasks/${id}/download`,
};

export const permissionApi = {
    users: resource('/users'),
    roles: resource('/roles'),
    permissions: resource('/permissions'),
    resources: resource('/resource-permissions'),
    dataRules: resource('/data-permission-rules'),
    columnRules: resource('/column-permission-rules'),
};

export const auditApi = {
    queryLogs: (params) => list('/query-logs', params),
};

export const monitorApi = {
    health: () => http.get('/health'),
    database: () => http.get('/health/database'),
    redis: () => http.get('/health/redis'),
    storage: () => http.get('/health/storage'),
    queue: () => http.get('/health/queue'),
    metrics: () => http.get('/metrics'),
};
