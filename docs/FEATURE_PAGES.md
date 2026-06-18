# 功能页面文档介绍

本文档按“页面/工作台”的方式描述系统功能。当前项目已完成后端 API，前端页面尚未落地；本文件可作为后续 Vue 3 管理端的页面规划、接口对接和验收说明。

## 页面导航建议

建议后台采用左侧菜单：

```text
首页概览
数据源
数据集
查询分析
图表管理
仪表盘
文件导入
数据导出
权限管理
数据权限
审计日志
系统监控
系统设置
```

## 首页概览

页面目标：展示 BI 平台运行状态和核心业务数据。

建议模块：

- 数据源数量。
- 数据集数量。
- 图表数量。
- 仪表盘数量。
- 今日查询次数。
- 查询缓存命中次数。
- 导入任务状态统计。
- 导出任务状态统计。
- 健康检查状态。

可用接口：

- `GET /api/health`
- `GET /api/metrics`
- `GET /api/query-logs`
- `GET /api/import-tasks`
- `GET /api/export-tasks`

交互说明：

- 点击健康状态进入系统监控页。
- 点击导入/导出异常任务进入任务详情。
- 点击查询次数进入查询日志页。

## 登录页

页面目标：用户输入账号密码，获取 Sanctum token。

字段：

- 邮箱。
- 密码。
- 设备名称，可隐藏或自动生成。

可用接口：

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`

状态说明：

- 登录失败返回 `40001` 和字段错误。
- 未认证访问后台接口返回 `40100`。
- 登录成功后将 token 放入 `Authorization: Bearer <token>`。

## 用户管理页

页面目标：管理系统用户、组织、部门和用户角色绑定。

列表字段：

- 用户 ID。
- 姓名。
- 邮箱。
- 组织。
- 部门。
- 状态。
- 角色。
- 最后登录时间。

主要操作：

- 新增用户。
- 编辑用户。
- 分配角色。
- 禁用/启用用户。
- 删除用户。

可用接口：

- `GET /api/users`
- `POST /api/users`
- `GET /api/users/{user}`
- `PUT /api/users/{user}`
- `DELETE /api/users/{user}`

## 角色与功能权限页

页面目标：配置角色和功能权限，用于用户基础授权。

角色页字段：

- 角色名称。
- 角色编码。
- 描述。
- 是否系统角色。
- 已绑定权限。

权限页字段：

- 权限名称。
- 权限编码。
- 权限分组。
- 描述。

可用接口：

- `apiResource roles`
- `apiResource permissions`

交互说明：

- 创建或更新角色时传入 `permission_ids`。
- 修改角色或权限后会清理相关用户权限缓存。

## 数据源管理页

页面目标：配置外部 MySQL 数据源，测试连接，读取数据库表和字段元数据。

列表字段：

- 名称。
- 类型。
- 主机。
- 端口。
- 数据库名。
- 状态。
- 最近测试结果。
- 最近测试时间。

主要操作：

- 新增数据源。
- 编辑数据源。
- 删除数据源。
- 测试连接。
- 同步元数据。
- 查看表列表。
- 查看字段列表。

可用接口：

- `GET /api/data-sources`
- `POST /api/data-sources`
- `GET /api/data-sources/{data_source}`
- `PUT /api/data-sources/{data_source}`
- `DELETE /api/data-sources/{data_source}`
- `POST /api/data-sources/{data_source}/test`
- `POST /api/data-sources/{data_source}/sync`
- `GET /api/data-sources/{data_source}/tables`
- `GET /api/data-sources/{data_source}/tables/{table}/fields`

交互说明：

- 密码只在创建/更新时输入，响应中不返回密文。
- 同步元数据后，可在数据集页面选择数据源表。

## 数据集管理页

页面目标：基于数据源表创建分析数据集，并配置字段语义、维度和指标。

列表字段：

- 数据集名称。
- 数据源。
- 主表。
- 数据集类型。
- 状态。
- 字段数量。
- 创建人。

主要操作：

- 新增数据集。
- 编辑数据集。
- 删除数据集。
- 同步字段。
- 字段配置。
- 数据预览。

可用接口：

- `GET /api/datasets`
- `POST /api/datasets`
- `GET /api/datasets/{dataset}`
- `PUT /api/datasets/{dataset}`
- `DELETE /api/datasets/{dataset}`
- `POST /api/datasets/{dataset}/sync-fields`
- `GET /api/datasets/{dataset}/fields`
- `PUT /api/datasets/{dataset}/fields/{field}`
- `POST /api/datasets/{dataset}/preview`

字段配置项：

- 字段别名。
- 展示名称。
- 物理/计算字段类型。
- 标准化类型。
- 语义类型。
- 是否维度。
- 是否指标。
- 是否可见。
- 是否可过滤。
- 默认聚合方式。
- 排序。

验收重点：

- 非安全表名或字段名不允许进入查询。
- 图表配置只能引用数据集已存在且可见的字段。

## 查询分析页

页面目标：提供面向开发/分析人员的自由查询调试入口。

主要区域：

- 数据集选择器。
- 维度选择器。
- 指标选择器。
- 过滤条件编辑器。
- 排序编辑器。
- limit/offset。
- 是否使用缓存。
- 查询结果表格。
- SQL/日志查看入口。

可用接口：

- `POST /api/query/execute`
- `GET /api/query-logs`

请求结构：

```json
{
  "dataset_id": 1,
  "dimensions": [
    {"field": "province"}
  ],
  "metrics": [
    {"field": "amount", "aggregate": "sum", "alias": "amount_sum"}
  ],
  "filters": [
    {"field": "year", "operator": "=", "value": 2026}
  ],
  "sorts": [
    {"field": "amount_sum", "direction": "desc"}
  ],
  "limit": 100,
  "use_cache": true
}
```

响应重点：

- `columns`：结果列定义。
- `rows`：结果行。
- `meta.elapsed_ms`：耗时。
- `meta.cached`：是否命中缓存。
- `meta.total`：结果行数。

## 图表管理页

页面目标：配置图表类型、图表字段映射、查询条件和样式参数。

支持图表：

- 指标卡：`metric_card`
- 柱状图：`bar`
- 折线图：`line`
- 饼图：`pie`
- 表格：`table`

列表字段：

- 图表名称。
- 图表类型。
- 数据集。
- 状态。
- 创建人。
- 更新时间。

编辑区：

- 基础信息。
- 数据集选择。
- 图表类型。
- 维度配置。
- 指标配置。
- 过滤条件。
- 排序。
- 样式 JSON。
- 数据预览。

可用接口：

- `GET /api/charts`
- `POST /api/charts`
- `GET /api/charts/{chart}`
- `PUT /api/charts/{chart}`
- `DELETE /api/charts/{chart}`
- `POST /api/charts/preview`
- `POST /api/charts/{chart}/data`

交互说明：

- 保存前可调用 preview 验证配置。
- 图表数据默认使用缓存。
- 图表配置修改后会清理该图表查询缓存。

## 仪表盘页面

页面目标：将多个图表编排为可交互仪表盘。

区域设计：

- 仪表盘列表。
- 画布区域。
- 组件配置抽屉。
- 全局筛选器配置。
- 分享配置。

组件布局字段：

- `x`：横向位置。
- `y`：纵向位置。
- `w`：宽度。
- `h`：高度。
- `sort_order`：排序。

可用接口：

- `GET /api/dashboards`
- `POST /api/dashboards`
- `GET /api/dashboards/{dashboard}`
- `PUT /api/dashboards/{dashboard}`
- `DELETE /api/dashboards/{dashboard}`
- `POST /api/dashboards/{dashboard}/widgets`
- `PUT /api/dashboards/{dashboard}/widgets/{widget}`
- `DELETE /api/dashboards/{dashboard}/widgets/{widget}`
- `POST /api/dashboards/{dashboard}/data`
- `POST /api/dashboards/{dashboard}/share`
- `GET /api/share/dashboards/{token}`

交互说明：

- 全局筛选会合并到所有组件图表查询。
- Widget 可配置自己的 `query_overrides`。
- 公开分享可以设置密码和过期时间。

## 文件导入页

页面目标：上传 CSV/XLSX 文件，异步导入并生成可分析数据集。

页面区域：

- 上传组件。
- 导入任务列表。
- 任务详情。
- 日志列表。
- 生成的数据表/数据集信息。

可用接口：

- `POST /api/import-tasks`
- `GET /api/import-tasks`
- `GET /api/import-tasks/{import_task}`
- `POST /api/import-tasks/{import_task}/retry`
- `DELETE /api/import-tasks/{import_task}`

状态说明：

- `pending`：等待处理。
- `processing`：处理中。
- `completed`：完成。
- `failed`：失败。

详情字段：

- 文件名。
- 文件类型。
- 文件大小。
- 总行数。
- 成功行数。
- 失败行数。
- 进度。
- 错误信息。
- 上传表。
- 最近日志。

验收重点：

- 上传后文件保存在 MinIO。
- 成功后创建物理表、上传表记录和数据集。
- 失败任务可重试。

## 数据导出页

页面目标：对图表或仪表盘创建导出任务并下载文件。

支持导出：

- 图表 CSV。
- 图表 XLSX。
- 仪表盘 PDF。

可用接口：

- `POST /api/export-tasks`
- `GET /api/export-tasks`
- `GET /api/export-tasks/{export_task}`
- `GET /api/export-tasks/{export_task}/download`
- `POST /api/export-tasks/{export_task}/retry`

页面字段：

- 导出类型。
- 来源类型。
- 来源 ID。
- 状态。
- 文件名。
- 文件大小。
- 进度。
- 错误信息。
- 创建人。
- 开始/完成时间。

交互说明：

- 任务完成后显示下载按钮。
- 下载接口只允许任务创建者访问。
- 失败任务显示错误信息和重试按钮。

## 数据权限页

页面目标：管理资源访问权限、行级数据权限和列级字段权限。

资源权限页面：

- 资源类型：`data_source`、`dataset`、`chart`、`dashboard`。
- 资源 ID。
- 主体类型：`user`、`role`、`department`、`organization`。
- 主体 ID。
- 权限类型：`view`、`edit`、`delete`、`manage`。

行级权限页面：

- 数据集。
- 主体类型。
- 主体 ID。
- 字段名。
- 操作符。
- 值。
- 状态。

列级权限页面：

- 数据集。
- 主体类型。
- 主体 ID。
- 字段名。
- 权限类型：`visible`、`hidden`、`masked`。

可用接口：

- `apiResource resource-permissions`
- `apiResource data-permission-rules`
- `apiResource column-permission-rules`

查询引擎行为：

- 如果某个 dataset 配置了资源权限，未匹配主体的用户不能查询。
- 行级权限会自动编译成 SQL where 条件。
- 隐藏字段不能作为维度、指标或过滤字段查询。

## 审计日志页

页面目标：查看用户操作、登录、查询和导出日志。

操作日志：

- 用户。
- 动作。
- 资源类型。
- 资源 ID。
- 请求方法。
- 请求地址。
- IP。
- User Agent。
- 请求载荷。
- 响应码。
- 耗时。

登录日志：

- 用户。
- IP。
- User Agent。
- 状态。
- 消息。
- 时间。

查询日志：

- 用户。
- 数据集。
- 图表。
- 仪表盘。
- SQL。
- bindings。
- query hash。
- 耗时。
- 行数。
- 是否缓存。
- 是否慢查询。
- 状态。
- 错误信息。

导出日志：

- 用户。
- 导出任务。
- 来源类型。
- 来源 ID。
- 文件路径。
- 状态。
- 时间。

可用接口：

- `GET /api/operation-logs`
- `GET /api/login-logs`
- `GET /api/query-logs`
- `GET /api/export-logs`

筛选建议：

- 按用户筛选。
- 按资源类型筛选。
- 按状态筛选。
- 按 dataset/chart/dashboard 筛选查询日志。
- 按时间范围筛选，当前后端未实现时间过滤，可后续补充。

## 系统监控页

页面目标：展示系统依赖健康状态和 Prometheus 指标。

健康检查卡片：

- 总体状态。
- 数据库状态。
- Redis 状态。
- 存储状态。
- 队列状态。

可用接口：

- `GET /api/health`
- `GET /api/health/database`
- `GET /api/health/redis`
- `GET /api/health/storage`
- `GET /api/health/queue`
- `GET /api/metrics`

指标：

- 查询总数。
- 查询失败数。
- 查询总耗时。
- 查询缓存命中数。
- 导入任务总数。
- 导出任务总数。
- 队列积压数量。

页面提示：

- `status=ok` 表示健康。
- `status=failed` 表示该依赖检查失败。
- 总体 `degraded` 表示至少一个依赖失败。

## 系统设置页

当前后端未实现独立 `system_settings` 表。页面可先展示只读配置：

- APP 环境。
- API 地址。
- 当前队列连接。
- 当前缓存驱动。
- 当前导入/导出文件系统 disk。
- 慢查询阈值，默认 3000ms。

后续可扩展：

- 慢查询阈值配置。
- 查询缓存 TTL 配置。
- 导入文件大小限制。
- 导出文件保留天数。
- 公开分享默认过期策略。

## 页面权限建议

当前后端已提供功能权限和数据权限基础，前端可按以下编码规划按钮权限：

```text
data_sources.view
data_sources.manage
datasets.view
datasets.manage
charts.view
charts.manage
dashboards.view
dashboards.manage
imports.manage
exports.manage
permissions.manage
audit.view
monitor.view
```

这些编码可通过 `permissions` 和 `roles` 接口维护，再由前端根据当前用户角色权限控制菜单和按钮展示。

## 前端实现建议

推荐技术栈：

- Vue 3
- TypeScript
- Vite
- Pinia
- Axios
- Element Plus 或 Ant Design Vue
- ECharts

API 封装建议：

- 统一 Axios 实例。
- 自动注入 Bearer token。
- 统一处理 `code !== 0`。
- `40100` 跳转登录。
- `40300` 展示无权限提示。
- 文件下载接口使用 blob。

复杂控件建议：

- 查询条件编辑器使用可重复条件行。
- 图表配置使用字段拖拽或选择器。
- 仪表盘布局可使用 grid layout。
- 数据权限规则配置应提供字段选择器，避免手输字段名。
- 导入/导出任务列表应定时轮询处理中任务。
