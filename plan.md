# Laravel 13 + Docker BI 分析工具项目计划

> 文件用途：将本文件放在 Laravel 项目根目录下，作为 Codex / AI 编程助手执行项目开发的长期计划文档。  
> 项目目标：基于 Laravel 13 + Docker 构建一套轻量但完整的 BI 分析工具，覆盖数据源接入、数据集建模、动态查询、图表配置、仪表盘、缓存、权限、导入导出和监控等核心能力。  
> 开发原则：先打通主链路，再逐步增强生产级能力。不要一开始追求大而全。

---

## 0. 项目定位

本项目是一套基于 Laravel 13 的 BI 分析工具，核心目标是实现：

```text
数据源接入
-> 元数据读取
-> 数据集建模
-> 维度 / 指标配置
-> 动态 SQL 查询
-> 图表展示
-> 仪表盘编排
-> 权限控制
-> 缓存优化
-> 异步导入导出
-> 查询日志和监控
```

第一版项目重点不是替代成熟商业 BI，而是构建一套能完整说明 BI 后端架构、数据建模、查询引擎和工程实现的系统。

---

## 1. 技术栈

### 1.1 后端

```text
Laravel 13
PHP 8.3+
Composer
Laravel Sanctum
Laravel Queue
Laravel Scheduler
Laravel Cache
Laravel Events / Listeners
Laravel Policies / Gates
```

### 1.2 前端

前端可以先简单实现，也可以独立项目开发。

推荐：

```text
Vue 3
TypeScript
Vite
Element Plus / Ant Design Vue
ECharts
Pinia
Axios
```

如果当前阶段只专注后端，可以先用 API + 简单 Blade / Swagger / Postman 调试。

### 1.3 数据库与中间件

```text
MySQL / PostgreSQL：系统元数据存储
Redis：缓存、队列、分布式锁、查询结果缓存
MinIO：上传文件、导出文件、临时文件存储
Nginx：Web 入口
PHP-FPM：Laravel 运行环境
Node：前端构建
```

### 1.4 后续可选组件

```text
ClickHouse：分析型数据查询
Doris：分析型数据库
StarRocks：分析型数据库
RabbitMQ：消息队列
Kafka：数据同步或事件流
Prometheus：指标采集
Grafana：监控面板
Loki：日志聚合
OpenTelemetry：链路追踪
```

---

## 2. Docker 环境规划

### 2.1 开发环境服务

项目根目录应提供 `docker-compose.yml`，至少包含：

```text
nginx
php-fpm
mysql
redis
minio
queue-worker
scheduler
node
```

### 2.2 推荐容器职责

| 服务 | 职责 |
|---|---|
| nginx | 反向代理，转发请求到 PHP-FPM |
| php-fpm | 运行 Laravel Web 请求 |
| mysql | 存储系统元数据 |
| redis | 缓存、队列、锁 |
| minio | 文件上传和导出文件存储 |
| queue-worker | 消费 Laravel 队列任务 |
| scheduler | 执行 Laravel 定时任务 |
| node | 前端开发和构建 |

### 2.3 目录建议

```text
docker/
  nginx/
    default.conf
  php/
    Dockerfile
    php.ini
  mysql/
    init/
  supervisor/
    queue-worker.conf
    scheduler.conf

storage/
bootstrap/cache/
```

### 2.4 环境变量

`.env.example` 应包含：

```env
APP_NAME="Laravel BI Platform"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=bi_platform
DB_USERNAME=bi_user
DB_PASSWORD=bi_password

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin
MINIO_BUCKET=bi-platform
MINIO_USE_PATH_STYLE_ENDPOINT=true
```

---

## 3. 总体架构

```text
Frontend
  |
  | HTTP API
  |
Laravel API
  |
  |-- Auth Module
  |-- User / Role / Permission Module
  |-- DataSource Module
  |-- Dataset Module
  |-- Query Engine Module
  |-- Chart Module
  |-- Dashboard Module
  |-- Import Module
  |-- Export Module
  |-- Cache Module
  |-- Audit Module
  |-- Monitor Module
  |
Infrastructure
  |
  |-- MySQL / PostgreSQL
  |-- Redis
  |-- MinIO
  |-- Queue Worker
  |-- Scheduler
  |-- Optional OLAP Engine
```

设计原则：

```text
1. 先做模块化单体，不急于微服务化。
2. 业务代码按模块组织，不要全部堆在 Controller。
3. Controller 只负责参数接收、权限校验和响应。
4. Service 负责业务流程。
5. Repository 或 Query 层负责数据库访问。
6. DTO 负责结构化参数传递。
7. Query Engine 独立成核心模块。
8. 图表、仪表盘只存配置，不直接存查询结果。
9. 查询结果通过 Redis 缓存。
10. 大文件导入导出走队列。
```

---

## 4. 推荐目录结构

```text
app/
  Modules/
    Auth/
      Controllers/
      Services/
      DTO/
      Requests/
      Resources/
    User/
      Controllers/
      Services/
      Models/
      Requests/
      Resources/
    Permission/
      Controllers/
      Services/
      Models/
      Policies/
    DataSource/
      Controllers/
      Services/
      Models/
      DTO/
      Drivers/
      Requests/
      Resources/
    Dataset/
      Controllers/
      Services/
      Models/
      DTO/
      Requests/
      Resources/
    Query/
      Controllers/
      Services/
      DTO/
      Compilers/
      Drivers/
      Validators/
    Chart/
      Controllers/
      Services/
      Models/
      Requests/
      Resources/
    Dashboard/
      Controllers/
      Services/
      Models/
      Requests/
      Resources/
    Import/
      Controllers/
      Services/
      Models/
      Jobs/
      Requests/
    Export/
      Controllers/
      Services/
      Models/
      Jobs/
      Requests/
    Audit/
      Middleware/
      Events/
      Listeners/
      Models/
      Services/
    Monitor/
      Services/
      Metrics/
  Support/
    Sql/
    Cache/
    Minio/
    Response/
    Exceptions/
  Jobs/
  Events/
  Listeners/
  Policies/
```

---

## 5. API 设计规范

### 5.1 URL 风格

```text
/api/auth/login
/api/auth/logout
/api/users
/api/roles
/api/permissions

/api/data-sources
/api/data-sources/{id}/test
/api/data-sources/{id}/tables
/api/data-sources/{id}/tables/{table}/fields

/api/datasets
/api/datasets/{id}/preview

/api/query/execute

/api/charts
/api/charts/{id}/data

/api/dashboards
/api/dashboards/{id}/widgets
/api/dashboards/{id}/data

/api/import-tasks
/api/export-tasks

/api/audit/query-logs
/api/audit/operation-logs
```

### 5.2 响应格式

统一响应：

```json
{
  "code": 0,
  "message": "success",
  "data": {}
}
```

分页响应：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "items": [],
    "pagination": {
      "page": 1,
      "page_size": 20,
      "total": 100
    }
  }
}
```

错误响应：

```json
{
  "code": 40001,
  "message": "Invalid request parameters",
  "errors": {}
}
```

### 5.3 Controller 规则

Controller 中不要写复杂业务逻辑。

Controller 只做：

```text
1. 接收 Request
2. 调用 FormRequest 验证参数
3. 调用 Service
4. 返回 Resource / JsonResponse
```

---

## 6. 数据库命名规范

### 6.1 表命名

```text
使用小写复数名
多个单词使用下划线
例如：
data_sources
dataset_fields
dashboard_widgets
query_logs
```

### 6.2 字段约定

通用字段：

```text
id
created_at
updated_at
deleted_at
created_by
updated_by
tenant_id
```

业务状态字段：

```text
status
is_enabled
sort_order
remark
```

### 6.3 JSON 字段

以下配置类内容可以用 JSON 字段：

```text
chart_config
dashboard_layout
filter_config
style_config
permission_rule
query_config
```

但注意：

```text
1. 核心查询字段不要全部塞 JSON，后续不好检索。
2. 可配置样式、布局、图表参数适合 JSON。
3. 需要频繁搜索、关联、统计的字段应拆成普通列。
```

---

## 7. 核心功能模块

---

# Phase 1：项目初始化与基础工程

## 7.1 目标

完成 Laravel 13 项目初始化，搭建 Docker 开发环境，打通登录、用户、角色、权限基础能力。

## 7.2 任务列表

```text
1. 初始化 Laravel 13 项目。
2. 配置 Docker Compose。
3. 配置 Nginx + PHP-FPM。
4. 配置 MySQL。
5. 配置 Redis。
6. 配置 MinIO。
7. 配置 queue-worker。
8. 配置 scheduler。
9. 配置 .env.example。
10. 增加统一 API 响应格式。
11. 增加全局异常处理。
12. 增加基础日志配置。
13. 增加 Sanctum 登录认证。
14. 增加用户表、角色表、权限表。
15. 增加 RBAC 基础接口。
```

## 7.3 需要创建的表

```text
users
roles
permissions
role_user
permission_role
organizations
departments
```

## 7.4 核心接口

```text
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me

GET    /api/users
POST   /api/users
GET    /api/users/{id}
PUT    /api/users/{id}
DELETE /api/users/{id}

GET    /api/roles
POST   /api/roles
PUT    /api/roles/{id}
DELETE /api/roles/{id}

GET    /api/permissions
POST   /api/permissions
PUT    /api/permissions/{id}
DELETE /api/permissions/{id}
```

## 7.5 验收标准

```text
1. docker compose up 后可以访问 Laravel。
2. 可以执行 migrate。
3. 可以创建管理员账号。
4. 可以登录并获取 token。
5. 可以创建角色和权限。
6. 用户可以绑定角色。
```

---

# Phase 2：数据源管理模块

## 8.1 目标

支持创建数据库连接，测试连接，读取数据库表和字段元数据。

第一版只要求支持 MySQL。

后续扩展：

```text
PostgreSQL
ClickHouse
Doris
StarRocks
SQL Server
Oracle
达梦
人大金仓
CSV / Excel
API 数据源
```

## 8.2 功能列表

```text
1. 新增数据源。
2. 修改数据源。
3. 删除数据源。
4. 启用 / 禁用数据源。
5. 测试数据库连接。
6. 获取数据库表列表。
7. 获取指定表字段列表。
8. 同步表元数据到系统库。
9. 缓存数据源元数据。
10. 加密存储数据库密码。
```

## 8.3 需要创建的表

### data_sources

建议字段：

```text
id
tenant_id
name
type
host
port
database_name
username
password_encrypted
charset
timezone
options_json
status
last_tested_at
last_test_result
created_by
updated_by
created_at
updated_at
deleted_at
```

### data_source_tables

```text
id
data_source_id
table_name
table_comment
table_type
row_count_estimate
synced_at
created_at
updated_at
```

### data_source_fields

```text
id
data_source_id
table_id
table_name
field_name
field_comment
data_type
normalized_type
is_nullable
is_primary_key
default_value
ordinal_position
created_at
updated_at
```

## 8.4 核心类设计

```text
DataSourceService
DataSourceConnectionFactory
DataSourceMetadataService
DataSourcePasswordEncryptor

DatabaseDriverInterface
MySqlMetadataDriver
PostgresMetadataDriver
ClickHouseMetadataDriver
```

## 8.5 核心接口

```text
GET    /api/data-sources
POST   /api/data-sources
GET    /api/data-sources/{id}
PUT    /api/data-sources/{id}
DELETE /api/data-sources/{id}

POST   /api/data-sources/{id}/test
POST   /api/data-sources/{id}/sync
GET    /api/data-sources/{id}/tables
GET    /api/data-sources/{id}/tables/{table}/fields
```

## 8.6 技术注意点

```text
1. 数据源密码必须加密存储。
2. 测试连接要设置超时时间。
3. 不要把外部数据库连接写死到 config/database.php。
4. 动态连接需要运行时创建 connection。
5. 表名和字段名要做白名单校验，避免 SQL 注入。
6. 元数据查询结果可以缓存。
```

## 8.7 验收标准

```text
1. 可以新增一个 MySQL 数据源。
2. 可以测试连接成功或失败。
3. 可以读取表列表。
4. 可以读取字段列表。
5. 可以同步表和字段到系统元数据表。
```

---

# Phase 3：数据集建模模块

## 9.1 目标

将数据库表封装为 BI 数据集。图表不直接依赖数据库表，而是依赖数据集。

第一版只做单表数据集。

后续扩展多表 Join、计算字段、自定义 SQL 数据集。

## 9.2 功能列表

```text
1. 创建数据集。
2. 选择数据源和表。
3. 同步字段。
4. 配置字段别名。
5. 配置字段类型。
6. 配置维度字段。
7. 配置指标字段。
8. 配置默认聚合方式。
9. 配置数据集过滤条件。
10. 数据集预览。
11. 数据集启用 / 禁用。
```

## 9.3 需要创建的表

### datasets

```text
id
tenant_id
name
description
data_source_id
dataset_type
main_table
config_json
status
created_by
updated_by
created_at
updated_at
deleted_at
```

### dataset_tables

```text
id
dataset_id
data_source_id
table_name
alias
join_type
join_condition
sort_order
created_at
updated_at
```

### dataset_fields

```text
id
dataset_id
table_name
field_name
field_alias
display_name
source_type
normalized_type
semantic_type
is_dimension
is_metric
is_visible
is_filterable
default_aggregate
expression
sort_order
created_at
updated_at
```

### dataset_filters

```text
id
dataset_id
field_id
operator
value_type
value_json
is_required
created_at
updated_at
```

### dataset_relations

后续多表 Join 使用：

```text
id
dataset_id
left_table
left_field
right_table
right_field
join_type
relation_type
created_at
updated_at
```

## 9.4 字段类型设计

normalized_type 建议值：

```text
string
number
integer
decimal
date
datetime
boolean
json
unknown
```

semantic_type 建议值：

```text
normal
time
province
city
region
amount
count
rate
percentage
category
id
```

## 9.5 默认聚合方式

```text
none
sum
avg
count
count_distinct
max
min
```

## 9.6 核心接口

```text
GET    /api/datasets
POST   /api/datasets
GET    /api/datasets/{id}
PUT    /api/datasets/{id}
DELETE /api/datasets/{id}

POST   /api/datasets/{id}/sync-fields
GET    /api/datasets/{id}/fields
PUT    /api/datasets/{id}/fields/{fieldId}
POST   /api/datasets/{id}/preview
```

## 9.7 验收标准

```text
1. 可以基于一张 MySQL 表创建数据集。
2. 可以读取并保存字段信息。
3. 可以将字段标记为维度或指标。
4. 可以配置指标默认聚合方式。
5. 可以预览前 100 条数据。
```

---

# Phase 4：查询引擎模块

## 10.1 目标

查询引擎是本项目最核心的技术模块。

它负责将前端传入的维度、指标、过滤、排序、分页配置，转换为安全 SQL，并执行查询。

## 10.2 查询请求示例

```json
{
  "dataset_id": 1,
  "dimensions": [
    {
      "field": "province"
    },
    {
      "field": "order_date",
      "time_granularity": "month",
      "alias": "month"
    }
  ],
  "metrics": [
    {
      "field": "sales_amount",
      "aggregate": "sum",
      "alias": "sales_amount_sum"
    }
  ],
  "filters": [
    {
      "field": "year",
      "operator": "=",
      "value": 2026
    }
  ],
  "sorts": [
    {
      "field": "sales_amount_sum",
      "direction": "desc"
    }
  ],
  "limit": 1000,
  "offset": 0
}
```

## 10.3 输出 SQL 示例

```sql
select
    province,
    date_format(order_date, '%Y-%m') as month,
    sum(sales_amount) as sales_amount_sum
from orders
where year = ?
group by province, date_format(order_date, '%Y-%m')
order by sales_amount_sum desc
limit 1000 offset 0;
```

## 10.4 功能列表

```text
1. 接收查询 JSON。
2. 校验 dataset 是否存在。
3. 校验字段是否属于 dataset。
4. 校验字段是否可查询。
5. 编译维度字段。
6. 编译指标字段。
7. 编译 where 条件。
8. 编译 group by。
9. 编译 order by。
10. 编译 limit / offset。
11. 合并数据权限条件。
12. 生成参数绑定 SQL。
13. 执行查询。
14. 记录查询日志。
15. 返回结构化数据。
16. 支持 Redis 查询缓存。
```

## 10.5 核心类设计

```text
app/Modules/Query/
  DTO/
    QueryRequestDTO.php
    DimensionDTO.php
    MetricDTO.php
    FilterDTO.php
    SortDTO.php
  Services/
    QueryService.php
    QueryExecutor.php
    QueryCacheService.php
    QueryLogService.php
  Compilers/
    SqlCompiler.php
    DimensionCompiler.php
    MetricCompiler.php
    FilterCompiler.php
    SortCompiler.php
    PermissionConditionCompiler.php
  Drivers/
    DatabaseDriverInterface.php
    MySqlQueryDriver.php
    PostgresQueryDriver.php
    ClickHouseQueryDriver.php
  Validators/
    QueryRequestValidator.php
    FieldPermissionValidator.php
```

## 10.6 查询引擎流程

```text
QueryController
  -> QueryRequestValidator
  -> QueryRequestDTO
  -> DatasetService 获取数据集元数据
  -> PermissionConditionCompiler 合并数据权限
  -> SqlCompiler 生成 SQL
  -> QueryCacheService 检查缓存
  -> QueryExecutor 执行查询
  -> QueryLogService 记录日志
  -> 返回结果
```

## 10.7 支持的过滤操作符

```text
=
!=
>
>=
<
<=
in
not_in
like
not_like
between
is_null
is_not_null
```

## 10.8 时间颗粒

```text
year
quarter
month
week
day
hour
minute
```

MySQL 示例：

```text
year:    year(field)
month:   date_format(field, '%Y-%m')
day:     date_format(field, '%Y-%m-%d')
hour:    date_format(field, '%Y-%m-%d %H:00:00')
```

## 10.9 SQL 安全规则

必须做到：

```text
1. 表名只能来自 dataset 元数据。
2. 字段名只能来自 dataset_fields。
3. 聚合函数只能来自白名单。
4. operator 只能来自白名单。
5. 所有值必须参数绑定，禁止字符串拼接 value。
6. 禁止执行 select 之外的语句。
7. 禁止出现 drop / delete / update / insert / alter / truncate 等危险关键字。
8. limit 必须有最大值限制。
9. 查询必须设置超时时间。
10. 记录慢查询。
```

## 10.10 查询结果格式

```json
{
  "columns": [
    {
      "name": "province",
      "label": "省份",
      "type": "string"
    },
    {
      "name": "sales_amount_sum",
      "label": "销售额",
      "type": "number"
    }
  ],
  "rows": [
    {
      "province": "广东",
      "sales_amount_sum": 100000
    }
  ],
  "meta": {
    "elapsed_ms": 35,
    "cached": false,
    "total": 1
  }
}
```

## 10.11 验收标准

```text
1. 可以对单表数据集执行 group by 查询。
2. 可以执行 sum / avg / count / max / min。
3. 可以执行 where 过滤。
4. 可以执行 order by。
5. 可以限制 limit。
6. 可以记录查询 SQL、耗时、用户、dataset_id。
7. 字段非法时必须拒绝查询。
```

---

# Phase 5：图表模块

## 11.1 目标

图表模块负责保存图表配置，并调用查询引擎获取数据。

图表本身不存业务数据，只存配置。

## 11.2 第一版支持图表

```text
指标卡
柱状图
折线图
饼图
表格
```

后续支持：

```text
条形图
面积图
散点图
漏斗图
雷达图
地图
透视表
组合图
```

## 11.3 需要创建的表

### charts

```text
id
tenant_id
name
description
dataset_id
chart_type
config_json
style_json
status
created_by
updated_by
created_at
updated_at
deleted_at
```

### chart_configs

可以先不单独拆表，第一版放在 charts.config_json 中即可。

后续需要版本化时再拆：

```text
id
chart_id
query_config_json
style_config_json
version
created_by
created_at
```

## 11.4 chart.config_json 示例

```json
{
  "dimensions": [
    {
      "field": "province"
    }
  ],
  "metrics": [
    {
      "field": "sales_amount",
      "aggregate": "sum",
      "alias": "sales_amount_sum"
    }
  ],
  "filters": [],
  "sorts": [
    {
      "field": "sales_amount_sum",
      "direction": "desc"
    }
  ],
  "limit": 100
}
```

## 11.5 chart.style_json 示例

```json
{
  "title": "各省销售额",
  "legend": true,
  "xAxis": {
    "name": "省份"
  },
  "yAxis": {
    "name": "销售额"
  },
  "label": {
    "show": true
  }
}
```

## 11.6 核心接口

```text
GET    /api/charts
POST   /api/charts
GET    /api/charts/{id}
PUT    /api/charts/{id}
DELETE /api/charts/{id}

POST   /api/charts/{id}/data
POST   /api/charts/preview
```

## 11.7 后端核心类

```text
ChartService
ChartDataService
ChartConfigValidator
ChartQueryBuilder
```

## 11.8 验收标准

```text
1. 可以创建图表。
2. 图表可以绑定数据集。
3. 图表可以配置维度和指标。
4. 图表可以调用查询引擎返回数据。
5. 可以支持指标卡、柱状图、折线图、饼图、表格。
```

---

# Phase 6：仪表盘模块

## 12.1 目标

仪表盘用于组合多个图表，并提供布局、筛选、联动和分享能力。

## 12.2 功能列表

```text
1. 创建仪表盘。
2. 修改仪表盘。
3. 删除仪表盘。
4. 添加图表到仪表盘。
5. 删除仪表盘图表。
6. 保存拖拽布局。
7. 配置全局筛选器。
8. 图表数据刷新。
9. 图表联动。
10. 仪表盘分享。
11. 大屏展示。
```

## 12.3 需要创建的表

### dashboards

```text
id
tenant_id
name
description
layout_json
global_filters_json
status
created_by
updated_by
created_at
updated_at
deleted_at
```

### dashboard_widgets

```text
id
dashboard_id
chart_id
widget_type
x
y
w
h
config_json
sort_order
created_at
updated_at
```

### dashboard_filters

```text
id
dashboard_id
field_name
label
filter_type
default_value_json
config_json
created_at
updated_at
```

### dashboard_linkages

```text
id
dashboard_id
source_widget_id
target_widget_id
source_field
target_field
config_json
created_at
updated_at
```

### dashboard_shares

```text
id
dashboard_id
share_token
share_type
password_hash
expired_at
created_by
created_at
updated_at
```

## 12.4 核心接口

```text
GET    /api/dashboards
POST   /api/dashboards
GET    /api/dashboards/{id}
PUT    /api/dashboards/{id}
DELETE /api/dashboards/{id}

POST   /api/dashboards/{id}/widgets
PUT    /api/dashboards/{id}/widgets/{widgetId}
DELETE /api/dashboards/{id}/widgets/{widgetId}

POST   /api/dashboards/{id}/data
POST   /api/dashboards/{id}/share
GET    /api/share/dashboards/{token}
```

## 12.5 前端布局建议

前端可以使用：

```text
grid-layout
vue-grid-layout
react-grid-layout
```

布局数据示例：

```json
[
  {
    "widget_id": 1,
    "x": 0,
    "y": 0,
    "w": 6,
    "h": 4
  },
  {
    "widget_id": 2,
    "x": 6,
    "y": 0,
    "w": 6,
    "h": 4
  }
]
```

## 12.6 验收标准

```text
1. 可以创建仪表盘。
2. 可以添加多个图表。
3. 可以保存布局。
4. 可以统一刷新仪表盘图表数据。
5. 可以配置全局筛选器并影响图表查询。
```

---

# Phase 7：文件导入模块

## 13.1 目标

支持上传 CSV / Excel 文件，异步导入数据，并生成数据集。

## 13.2 功能列表

```text
1. 上传 CSV / Excel 文件。
2. 文件保存到 MinIO。
3. 创建导入任务。
4. 队列异步读取文件。
5. 自动识别表头。
6. 推断字段类型。
7. 创建物理表或写入中间表。
8. 记录导入进度。
9. 记录失败行。
10. 导入成功后生成数据集。
```

## 13.3 需要创建的表

### import_tasks

```text
id
tenant_id
file_name
file_path
file_type
file_size
status
total_rows
success_rows
failed_rows
progress
error_message
created_by
started_at
finished_at
created_at
updated_at
```

### import_task_logs

```text
id
import_task_id
row_number
status
message
raw_data_json
created_at
```

### uploaded_tables

```text
id
tenant_id
import_task_id
table_name
display_name
schema_json
created_at
updated_at
```

## 13.4 核心类

```text
ImportTaskService
FileUploadService
CsvParser
ExcelParser
ImportSchemaInferService
ImportDataWriter
CreateDatasetFromImportService

Jobs/ProcessImportTaskJob
```

## 13.5 核心接口

```text
POST   /api/import-tasks
GET    /api/import-tasks
GET    /api/import-tasks/{id}
POST   /api/import-tasks/{id}/retry
DELETE /api/import-tasks/{id}
```

## 13.6 验收标准

```text
1. 可以上传 CSV。
2. 文件可以保存到 MinIO。
3. 可以创建导入任务。
4. 队列可以异步处理任务。
5. 可以查看导入进度。
6. 导入完成后可以生成数据集。
```

---

# Phase 8：导出模块

## 14.1 目标

支持图表数据、表格数据、仪表盘导出。

## 14.2 功能列表

```text
1. 图表数据导出 CSV。
2. 图表数据导出 Excel。
3. 表格数据异步导出。
4. 仪表盘导出 PDF。
5. 导出文件保存到 MinIO。
6. 导出任务进度查询。
7. 导出文件下载。
8. 导出权限校验。
```

## 14.3 需要创建的表

### export_tasks

```text
id
tenant_id
export_type
source_type
source_id
status
file_name
file_path
file_size
progress
error_message
created_by
started_at
finished_at
created_at
updated_at
```

## 14.4 核心类

```text
ExportTaskService
ChartExportService
DashboardExportService
ExcelExportService
CsvExportService
PdfExportService

Jobs/ProcessExportTaskJob
```

## 14.5 核心接口

```text
POST   /api/export-tasks
GET    /api/export-tasks
GET    /api/export-tasks/{id}
GET    /api/export-tasks/{id}/download
POST   /api/export-tasks/{id}/retry
```

## 14.6 验收标准

```text
1. 可以对图表查询结果导出 CSV。
2. 大数据量导出必须走队列。
3. 导出完成后文件保存到 MinIO。
4. 用户可以下载自己的导出文件。
```

---

# Phase 9：缓存模块

## 15.1 目标

为元数据、图表查询结果、仪表盘配置、权限数据提供缓存。

## 15.2 缓存对象

```text
数据源表列表
数据源字段列表
数据集元数据
图表配置
图表查询结果
仪表盘布局
用户权限
数据权限规则
```

## 15.3 Redis Key 设计

```text
bi:data_source:{id}:tables
bi:data_source:{id}:table:{table}:fields
bi:dataset:{id}:schema
bi:chart:{id}:config
bi:chart:{id}:query:{query_hash}
bi:dashboard:{id}:layout
bi:user:{id}:permissions
bi:user:{id}:data_permissions
```

## 15.4 查询缓存规则

```text
1. 根据 dataset_id、dimensions、metrics、filters、sorts、limit、user_id、permission_hash 生成 query_hash。
2. 同一个用户或权限范围相同的用户可以命中缓存。
3. 数据集修改时，清理相关图表缓存。
4. 图表修改时，清理图表缓存。
5. 权限修改时，清理用户权限缓存。
6. 可以设置短 TTL，例如 60 秒、300 秒、1800 秒。
```

## 15.5 防击穿策略

```text
1. 热门图表可以逻辑过期。
2. 缓存重建加 Redis 锁。
3. 查询失败不缓存。
4. 空结果可以短时间缓存。
```

## 15.6 核心类

```text
QueryCacheService
DatasetCacheService
ChartCacheService
DashboardCacheService
PermissionCacheService
CacheKeyBuilder
```

## 15.7 验收标准

```text
1. 同一图表重复查询可以命中 Redis。
2. 图表配置修改后缓存失效。
3. 数据集字段修改后缓存失效。
4. 查询日志中能看到 cached=true/false。
```

---

# Phase 10：数据权限模块

## 16.1 目标

实现资源权限、行级数据权限、列级字段权限。

这是 BI 系统重要能力。

## 16.2 权限分层

```text
功能权限：能否访问菜单、按钮、接口
资源权限：能否访问某个数据源、数据集、图表、仪表盘
数据权限：能否看到某些行、某些字段
```

## 16.3 示例场景

```text
1. 普通销售只能看自己负责的客户。
2. 区域经理只能看自己区域的数据。
3. 部门负责人只能看自己部门数据。
4. 普通用户不能看利润字段。
5. 某些仪表盘只能指定角色访问。
```

## 16.4 需要创建的表

### resource_permissions

```text
id
tenant_id
resource_type
resource_id
subject_type
subject_id
permission_type
created_at
updated_at
```

resource_type：

```text
data_source
dataset
chart
dashboard
```

subject_type：

```text
user
role
department
organization
```

permission_type：

```text
view
edit
delete
manage
```

### data_permission_rules

```text
id
tenant_id
dataset_id
subject_type
subject_id
field_name
operator
value_type
value_json
status
created_at
updated_at
```

### column_permission_rules

```text
id
tenant_id
dataset_id
subject_type
subject_id
field_name
permission_type
created_at
updated_at
```

permission_type：

```text
visible
hidden
masked
```

## 16.5 查询引擎集成方式

数据权限必须在 Query Engine 层统一追加。

流程：

```text
用户发起查询
-> 获取用户角色 / 部门 / 组织
-> 查询数据权限规则
-> 编译成 where 条件
-> 和用户原始 filters 合并
-> 生成最终 SQL
```

示例：

```text
用户原始条件：
year = 2026

权限条件：
province = '广东'

最终条件：
year = 2026 and province = '广东'
```

## 16.6 验收标准

```text
1. 可以给角色配置 dataset 访问权限。
2. 没有权限的用户不能访问数据集。
3. 可以配置 province = 广东 这种行级权限。
4. 查询引擎会自动追加权限条件。
5. 可以隐藏某些字段。
```

---

# Phase 11：审计日志与查询日志模块

## 17.1 目标

记录用户操作、查询 SQL、导出、登录和异常。

## 17.2 需要创建的表

### operation_logs

```text
id
tenant_id
user_id
action
resource_type
resource_id
request_method
request_url
request_ip
request_user_agent
request_payload_json
response_code
elapsed_ms
created_at
```

### query_logs

```text
id
tenant_id
user_id
dataset_id
chart_id
dashboard_id
sql_text
bindings_json
query_hash
elapsed_ms
row_count
cached
status
error_message
created_at
```

### login_logs

```text
id
tenant_id
user_id
login_ip
user_agent
status
message
created_at
```

### export_logs

可以和 export_tasks 复用，也可以单独拆。

```text
id
tenant_id
user_id
export_task_id
source_type
source_id
file_path
status
created_at
```

## 17.3 功能列表

```text
1. 记录登录日志。
2. 记录操作日志。
3. 记录查询日志。
4. 记录慢查询。
5. 记录导出日志。
6. 查询日志后台列表。
7. 根据用户、数据集、图表筛选日志。
```

## 17.4 慢查询标准

第一版：

```text
elapsed_ms >= 3000
```

后续可配置：

```text
system_settings.slow_query_threshold_ms
```

## 17.5 验收标准

```text
1. 每次图表查询都有 query_logs。
2. 查询失败也要记录。
3. 可以看到 SQL、耗时、是否缓存、返回行数。
4. 可以查询某个用户的操作日志。
```

---

# Phase 12：监控与生产级增强

## 18.1 目标

增加系统可观测性和生产环境设计能力。

## 18.2 功能列表

```text
1. 健康检查接口。
2. Redis 状态检查。
3. MySQL 状态检查。
4. MinIO 状态检查。
5. 队列积压数量统计。
6. 查询耗时指标。
7. 导入导出任务指标。
8. Prometheus metrics 接口。
9. Grafana 面板。
10. Loki 日志收集。
```

## 18.3 健康检查接口

```text
GET /api/health
GET /api/health/database
GET /api/health/redis
GET /api/health/storage
GET /api/health/queue
```

## 18.4 Prometheus 指标示例

```text
bi_query_total
bi_query_failed_total
bi_query_duration_ms
bi_query_cache_hit_total
bi_import_task_total
bi_export_task_total
bi_queue_pending_jobs
```

## 18.5 验收标准

```text
1. 可以访问健康检查接口。
2. 可以查看 Redis / MySQL / MinIO 状态。
3. 可以统计查询次数、失败次数和缓存命中次数。
4. 可选：Prometheus 能采集指标。
```

---

## 19. MVP 最小可用版本范围

第一轮开发不要超过以下范围。

MVP 必须完成：

```text
1. Docker 开发环境
2. Laravel 13 后端
3. 用户登录
4. RBAC 基础权限
5. MySQL 数据源管理
6. 表和字段元数据读取
7. 单表数据集
8. 字段维度 / 指标配置
9. 查询引擎基础版
10. 图表配置
11. 指标卡
12. 柱状图
13. 折线图
14. 表格
15. 仪表盘基础布局
16. Redis 查询缓存
17. 查询日志
```

MVP 暂不做：

```text
1. 多租户复杂隔离
2. 多表 Join
3. 自定义 SQL 数据集
4. StarRocks / Doris / ClickHouse
5. 高级图表
6. 复杂数据权限
7. PDF 导出
8. 实时数据推送
9. 微服务化
10. Kubernetes 部署
```

---

## 20. Codex 执行规则

Codex 在执行本项目时必须遵守以下规则。

### 20.1 每次执行前

```text
1. 阅读本 plan.md。
2. 明确当前要执行的 Phase。
3. 只修改和当前 Phase 相关的文件。
4. 不要一次性生成所有模块。
5. 优先保证代码可运行。
6. 如果需要新增依赖，说明原因。
7. 如果涉及数据库结构，必须生成 migration。
8. 如果涉及接口，必须增加 route、request、controller、service。
9. 如果涉及核心逻辑，尽量增加 feature test 或 unit test。
```

### 20.2 每次执行后

必须输出：

```text
1. 本次完成了哪些内容。
2. 修改了哪些文件。
3. 新增了哪些表。
4. 新增了哪些接口。
5. 如何运行或测试。
6. 还有哪些未完成。
```

### 20.3 代码风格

```text
1. 遵循 Laravel 官方目录和命名习惯。
2. Controller 保持轻量。
3. Service 承载业务流程。
4. DTO 承载结构化参数。
5. FormRequest 负责参数验证。
6. Resource 负责响应格式。
7. 不要在 Controller 中拼 SQL。
8. 不要在前端直接拼接危险 SQL。
9. 查询引擎必须使用白名单和参数绑定。
10. 不要把密钥、密码写死在代码里。
```

### 20.4 Git 提交建议

每个 Phase 可以拆成多个提交。

示例：

```text
feat: initialize docker environment
feat: add auth and rbac module
feat: add data source management
feat: add dataset metadata model
feat: add query engine basic compiler
feat: add chart module
feat: add dashboard module
feat: add query cache
feat: add query logs
```

---

## 21. 建议开发顺序

严格按以下顺序执行：

```text
1. Docker + Laravel 初始化
2. 用户登录 + RBAC
3. 数据源管理
4. 元数据读取
5. 数据集建模
6. 查询引擎
7. 图表配置
8. 仪表盘
9. 查询缓存
10. 查询日志
11. 导入导出
12. 数据权限
13. 监控增强
14. 生产环境优化
```

不要提前做复杂功能。

例如：

```text
没有完成单表数据集之前，不要做多表 Join。
没有完成查询引擎之前，不要做复杂图表。
没有完成图表模块之前，不要做大屏。
没有完成查询日志之前，不要做复杂监控。
```

---

## 22. 第一阶段 Codex 任务示例

可以先给 Codex 这样的任务：

```text
请阅读项目根目录下的 plan.md。

当前只执行 Phase 1：项目初始化与基础工程。

目标：
1. 检查当前 Laravel 13 项目结构。
2. 补充 Docker Compose 开发环境。
3. 增加 MySQL、Redis、MinIO、queue-worker、scheduler 服务。
4. 增加 .env.example 中必要配置。
5. 增加统一 API 响应辅助类。
6. 增加全局异常响应格式。
7. 增加 Sanctum 登录基础接口。
8. 增加 users、roles、permissions、role_user、permission_role 表。
9. 增加基础 AuthController、UserController、RoleController、PermissionController。
10. 添加基础路由。

要求：
1. 不要实现 Phase 2 之后的功能。
2. Controller 保持轻量。
3. 使用 FormRequest 验证参数。
4. 使用 Service 承载业务逻辑。
5. 生成必要 migration。
6. 输出修改文件清单和运行步骤。
```

---

## 23. 第二阶段 Codex 任务示例

```text
请阅读项目根目录下的 plan.md。

当前只执行 Phase 2：数据源管理模块。

目标：
1. 实现 data_sources、data_source_tables、data_source_fields 三张表。
2. 实现 DataSource 模块目录。
3. 支持 MySQL 数据源 CRUD。
4. 支持数据源连接测试。
5. 支持动态创建数据库连接。
6. 支持读取 MySQL 表列表。
7. 支持读取 MySQL 表字段。
8. 支持同步元数据到系统表。
9. 数据源密码必须加密存储。
10. 接口必须经过登录认证。

要求：
1. 不要实现 Dataset 模块。
2. 不要实现图表模块。
3. 所有外部数据库连接需要设置超时。
4. 表名、字段名输出时注意安全。
5. 输出接口列表、测试方式、修改文件清单。
```

---

## 24. 第三阶段 Codex 任务示例

```text
请阅读项目根目录下的 plan.md。

当前只执行 Phase 3：数据集建模模块。

目标：
1. 实现 datasets、dataset_tables、dataset_fields、dataset_filters 表。
2. 支持基于 MySQL 数据源中的单表创建数据集。
3. 创建数据集时同步字段到 dataset_fields。
4. 支持字段别名、显示名、字段类型配置。
5. 支持标记字段为维度或指标。
6. 支持配置指标默认聚合方式。
7. 支持数据集预览前 100 条数据。
8. 预览时只能查询当前数据集绑定的表。

要求：
1. 不要实现多表 Join。
2. 不要实现复杂计算字段。
3. 预览 SQL 必须做表名和字段名白名单校验。
4. 输出接口列表、测试方式、修改文件清单。
```

---

## 25. 第四阶段 Codex 任务示例

```text
请阅读项目根目录下的 plan.md。

当前只执行 Phase 4：查询引擎模块。

目标：
1. 实现 Query 模块目录结构。
2. 实现 QueryRequestDTO、DimensionDTO、MetricDTO、FilterDTO、SortDTO。
3. 实现 QueryRequestValidator。
4. 实现 SqlCompiler。
5. 实现 DimensionCompiler。
6. 实现 MetricCompiler。
7. 实现 FilterCompiler。
8. 实现 SortCompiler。
9. 实现 MySqlQueryDriver。
10. 实现 QueryExecutor。
11. 实现 QueryLogService。
12. 增加 POST /api/query/execute 接口。
13. 支持 select、where、group by、order by、limit。
14. 支持 sum、avg、count、max、min。
15. 所有 value 必须使用参数绑定。
16. 字段必须来自 dataset_fields 白名单。

要求：
1. 不要实现复杂数据权限。
2. 不要实现 ClickHouse。
3. 不要实现多表 Join。
4. 必须增加基础测试。
5. 输出接口示例、请求示例、响应示例、修改文件清单。
```

---

## 26. 适合面试表达的项目亮点

开发时要有意识沉淀以下内容，后面可以写进简历或用于面试回答。

### 26.1 元数据驱动

```text
系统没有为每个图表写死 SQL，而是通过数据源元数据、数据集字段、维度、指标和图表配置动态生成查询。
```

### 26.2 查询引擎

```text
将前端 JSON 查询配置转为安全 SQL，支持维度、指标、过滤、排序、分页、聚合和时间颗粒。
```

### 26.3 SQL 安全

```text
表名和字段名全部来自元数据白名单，查询值使用参数绑定，操作符和聚合函数使用白名单，避免 SQL 注入。
```

### 26.4 查询缓存

```text
根据查询配置、用户权限和数据集版本生成 query_hash，将图表查询结果缓存到 Redis，降低重复查询压力。
```

### 26.5 数据权限

```text
数据权限不在每个业务接口单独处理，而是在查询引擎层统一追加权限条件，实现行级数据隔离。
```

### 26.6 异步任务

```text
大文件导入、Excel 导出、PDF 导出使用 Laravel Queue 异步处理，并记录任务进度和失败原因。
```

### 26.7 可观测性

```text
记录查询日志、慢查询、队列任务、导入导出任务，并预留 Prometheus + Grafana + Loki 监控方案。
```

---

## 27. 生产环境设计说明

开发环境可以使用 Docker Compose。

生产环境通常不建议直接照搬本地 Sail 或简单 Compose，而是根据公司情况选择：

### 27.1 中小团队方案

```text
Nginx
PHP-FPM 多进程
MySQL 主从或云 RDS
Redis 单机或主从
MinIO 或云对象存储
Supervisor 管理 Queue Worker
定时任务独立容器
日志写入文件或 Loki
```

### 27.2 容器化方案

```text
Docker 镜像构建
Nginx 容器
PHP-FPM 容器
Queue Worker 容器
Scheduler 容器
Redis / MySQL 使用托管服务
对象存储使用 S3 / OSS / COS
CI/CD 自动发布
```

### 27.3 Kubernetes 方案

```text
Ingress
Deployment: api
Deployment: queue-worker
CronJob: scheduler
Service
ConfigMap
Secret
PVC
HPA
Prometheus
Grafana
Loki
```

### 27.4 容量设计关注点

需要考虑：

```text
1. 用户数。
2. 并发查询数。
3. 单次查询最大数据量。
4. 图表刷新频率。
5. 仪表盘访问频率。
6. 数据源数据库压力。
7. Redis 缓存容量。
8. 队列任务积压。
9. 导入文件大小。
10. 导出文件大小。
11. 慢查询比例。
12. MySQL 元数据表增长速度。
```

### 27.5 扩展方式

```text
1. Web API 可以横向扩容多个 PHP-FPM 容器。
2. Queue Worker 可以独立扩容。
3. Redis 可以升级为主从或集群。
4. 元数据库可以使用云 RDS。
5. 大数据分析可以接入 ClickHouse / Doris / StarRocks。
6. 文件存储可以从 MinIO 切换到云对象存储。
7. 查询结果可以增加缓存预热。
8. 热门仪表盘可以定时刷新缓存。
```

---

## 28. 当前优先级总结

最高优先级：

```text
1. Docker 环境跑通。
2. 登录和权限跑通。
3. 数据源连接跑通。
4. 数据集建模跑通。
5. 查询引擎跑通。
6. 图表数据返回跑通。
7. 仪表盘展示跑通。
```

中等优先级：

```text
1. Redis 查询缓存。
2. 查询日志。
3. Excel / CSV 导入。
4. Excel / CSV 导出。
5. 基础数据权限。
```

后续增强：

```text
1. 多表 Join。
2. 自定义 SQL 数据集。
3. ClickHouse / Doris / StarRocks。
4. 复杂大屏。
5. PDF 导出。
6. Prometheus + Grafana。
7. Loki。
8. Kubernetes。
```

---

## 29. 最终目标

完成后，本项目应具备以下能力：

```text
1. 用户可以登录系统。
2. 管理员可以创建数据源。
3. 系统可以读取数据源表和字段。
4. 用户可以创建数据集。
5. 用户可以配置维度和指标。
6. 查询引擎可以动态生成 SQL。
7. 用户可以创建图表。
8. 用户可以创建仪表盘。
9. 查询结果可以缓存。
10. 系统可以记录查询日志。
11. 文件可以导入为数据集。
12. 图表数据可以导出。
13. 不同用户可以看到不同数据。
14. 系统具备基础生产环境扩展方案。
```

本计划应作为项目长期开发依据。  
每次开发只执行一个明确阶段，确保代码始终可运行、可测试、可解释。
