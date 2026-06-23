继续执行当前 Laravel 13 + Docker BI 分析工具项目的下一阶段任务：

# Phase 10.4：StarRocks / Doris 作为一等 OLAP 数据源接入

## 一、当前项目背景

当前项目是 Laravel 13 + Docker 构建的 BI 分析工具。

前面阶段已经完成或计划完成：

1. 数据源管理。
2. 数据集建模。
3. 查询引擎。
4. 图表管理。
5. 仪表盘管理。
6. 查询缓存。
7. 数据权限。
8. 查询日志。
9. ClickHouse 明细表加速。
10. ClickHouse 预聚合表加速。
11. 基于 query_logs 的加速推荐。
12. 加速表定时刷新。
13. Vue 前端工程或前端计划。

本阶段的重点不是继续把 StarRocks / Doris 当作“同步目标加速层”，而是把它们作为 BI 系统的一等数据源。

也就是说：

```text
之前 ClickHouse 阶段：
MySQL / 导入表 -> 同步到 ClickHouse -> 查询加速

本阶段 StarRocks / Doris：
StarRocks / Doris 本身就是分析数据源
Laravel BI 直接连接并查询 StarRocks / Doris
```

这更接近真实公司里的 BI 项目：

```text
业务系统 / ETL / 数据仓库
    ↓
StarRocks / Doris
    ↓
BI 系统直接连接 OLAP 库
    ↓
数据集建模
    ↓
图表 / 仪表盘查询
```

## 二、本阶段目标

实现 StarRocks / Doris 作为一等 OLAP 数据源的完整接入能力。

核心目标：

1. 数据源类型新增 starrocks、doris。
2. 支持 StarRocks / Doris 连接测试。
3. 支持读取库、表、视图、字段元数据。
4. 支持基于 StarRocks / Doris 表创建数据集。
5. 查询引擎支持 StarRocks / Doris SQL 方言。
6. 支持 StarRocks / Doris 作为图表查询来源。
7. 支持 StarRocks / Doris 数据权限条件合并。
8. 支持查询日志记录 OLAP 引擎类型、SQL、耗时、失败原因。
9. 支持 Explain / 查询诊断基础接口。
10. 支持 StarRocks / Doris 物化视图基础管理或元数据展示。
11. 支持前端页面选择 StarRocks / Doris 数据源。
12. 不破坏已有 MySQL、PostgreSQL、ClickHouse 加速逻辑。

## 三、本阶段明确不做

本阶段不要做：

1. 不做完整 StarRocks / Doris 集群部署平台。
2. 不做 FE / BE 节点管理。
3. 不做 Routine Load / Stream Load 完整导入平台。
4. 不做 Kafka / Flink / CDC 同步链路。
5. 不做自动建仓。
6. 不做复杂 Cost Based Optimizer。
7. 不自动改写所有 SQL。
8. 不重写现有查询引擎。
9. 不把 StarRocks / Doris 强行塞进 ClickHouse acceleration_profiles。
10. 不破坏已有数据源、数据集、图表、仪表盘主流程。

本阶段只做：

```text
StarRocks / Doris 直连查询
+
SQL 方言适配
+
元数据读取
+
物化视图元数据 / 基础管理
+
查询诊断
```

## 四、整体设计原则

StarRocks / Doris 在本项目中有两种角色：

### 1. 一等数据源

这是本阶段重点。

```text
data_sources.type = starrocks
data_sources.type = doris
```

BI 查询直接基于 StarRocks / Doris 表执行。

适合：

1. 企业数据仓库已经建在 StarRocks / Doris。
2. 数据已经通过 ETL / CDC / Flink / DataX / SeaTunnel 同步到 OLAP 库。
3. BI 只负责连接和查询，不负责数据同步。
4. 需要讲真实公司生产架构。

### 2. 查询加速引擎

这是前面 ClickHouse acceleration 体系类似的角色。

本阶段只做接口预留，不作为重点。

```text
acceleration_profiles.engine_type = starrocks / doris
```

后续可以补充，但本阶段不要强制实现完整同步加速闭环。

## 五、执行前先扫描

请先阅读：

1. plan.md
2. routes/api.php
3. 当前 DataSource 模块代码
4. 当前 Dataset 模块代码
5. 当前 QueryService / QueryEngine / SqlGenerator
6. 当前 DatabaseDriverInterface 或类似抽象
7. 当前 MySQL / PostgreSQL / ClickHouse 连接实现
8. 当前 query_logs 相关代码
9. 当前 data_permissions / column_permissions 相关代码
10. 当前前端 data-source / dataset / chart 页面
11. config/database.php
12. .env.example
13. docker-compose.yml
14. README.md
15. docs/bi-acceleration-clickhouse.md
16. docs/bi-acceleration-aggregate.md

要求：

1. 先理解已有数据源抽象。
2. 优先扩展现有接口，不要另起一套数据源系统。
3. StarRocks / Doris 应该进入数据源管理、数据集建模、图表查询主流程。
4. 不要为了接入 StarRocks / Doris 重写整个 QueryService。
5. 如果现有接口不支持方言扩展，做最小抽象改造。

## 六、数据源类型扩展

### 1. data_sources 表

如果 data_sources.type 已经是字符串，直接支持：

```text
mysql
postgresql
clickhouse
starrocks
doris
csv
excel
```

如果 type 有枚举校验，需要增加：

```text
starrocks
doris
```

StarRocks / Doris 连接字段：

```text
host
port
database
username
password
charset
timezone
ssl_enabled
options_json
```

默认端口建议：

```text
StarRocks MySQL 协议端口：9030，具体以部署配置为准
Doris FE query_port：9030，具体以部署配置为准
```

不要写死端口，前端和后端都允许用户填写。

### 2. .env.example 可选增加

如果项目支持默认 OLAP 连接，可以增加：

```text
STARROCKS_HOST=starrocks
STARROCKS_PORT=9030
STARROCKS_DATABASE=default_cluster
STARROCKS_USERNAME=root
STARROCKS_PASSWORD=

DORIS_HOST=doris-fe
DORIS_PORT=9030
DORIS_DATABASE=default_cluster
DORIS_USERNAME=root
DORIS_PASSWORD=
```

但不要要求项目必须配置默认 StarRocks / Doris。
数据源信息应优先来自 data_sources 表。

## 七、连接实现

StarRocks / Doris 都可以优先通过 MySQL 协议连接。

如果当前项目使用 Laravel DB connection 动态连接 MySQL，可以复用 MySQL driver，但需要封装 OLAP 方言差异。

新增或扩展：

```text
app/Services/DataSource/Drivers/
  DataSourceDriverInterface.php
  MySqlDataSourceDriver.php
  StarRocksDataSourceDriver.php
  DorisDataSourceDriver.php
```

如果已有类似结构，按现有结构改造。

### Driver 接口建议

```php
interface DataSourceDriverInterface
{
    public function testConnection(DataSource $dataSource): bool;

    public function listDatabases(DataSource $dataSource): array;

    public function listTables(DataSource $dataSource, ?string $database = null): array;

    public function listViews(DataSource $dataSource, ?string $database = null): array;

    public function listColumns(DataSource $dataSource, string $table, ?string $database = null): array;

    public function preview(DataSource $dataSource, string $table, int $limit = 100): array;

    public function explain(DataSource $dataSource, string $sql, array $bindings = []): array;

    public function executeQuery(DataSource $dataSource, string $sql, array $bindings = []): array;

    public function getDialect(): string;
}
```

### StarRocksDataSourceDriver

职责：

1. 测试连接。
2. 读取 databases。
3. 读取 tables。
4. 读取 views。
5. 读取 columns。
6. 查询预览。
7. 执行 Explain。
8. 执行 BI 查询。
9. 返回 dialect = starrocks。

### DorisDataSourceDriver

职责：

1. 测试连接。
2. 读取 databases。
3. 读取 tables。
4. 读取 views。
5. 读取 columns。
6. 查询预览。
7. 执行 Explain。
8. 执行 BI 查询。
9. 返回 dialect = doris。

## 八、元数据读取

第一版可以使用通用 SQL：

```sql
SHOW DATABASES;
SHOW TABLES;
DESCRIBE table_name;
```

如果项目已有 information_schema 读取逻辑，可以优先复用。

元数据结果需要统一成项目内部结构：

```text
table_name
table_type
comment
engine
row_count nullable
created_at nullable
updated_at nullable
```

字段结构：

```text
field_name
field_type
normalized_type
nullable
default_value
comment
is_dimension_candidate
is_metric_candidate
```

类型归一化建议：

```text
整数类       -> integer
小数类       -> decimal
浮点类       -> float
字符串类     -> string
日期类       -> date
时间类       -> datetime
布尔类       -> boolean
JSON/复杂类型 -> string/json
```

要求：

1. 元数据读取失败要返回清晰错误。
2. 不要暴露密码。
3. 表名、库名需要安全转义。
4. 不允许前端直接拼接 SQL。
5. 数据库、表、字段必须来自元数据或经过白名单校验。

## 九、Dataset 建模支持

支持基于 StarRocks / Doris 表创建数据集。

功能要求：

1. 数据源类型为 starrocks / doris 时，可以选择库和表。
2. 可以读取字段列表。
3. 可以标记维度 / 指标。
4. 可以配置字段别名。
5. 可以配置聚合方式。
6. 可以配置时间字段。
7. 可以配置时间颗粒。
8. 可以预览数据。
9. 可以基于该数据集创建图表。
10. 可以基于该数据集创建仪表盘。

注意：

StarRocks / Doris 数据集不需要导入到 Laravel MySQL，也不需要同步到 ClickHouse。
它本身就是 OLAP 查询来源。

## 十、SQL 方言适配

新增或扩展 SQL 方言层：

```text
app/Services/Query/Dialects/
  SqlDialectInterface.php
  MySqlDialect.php
  StarRocksDialect.php
  DorisDialect.php
```

如果当前已有 SqlGenerator，可以做最小改造。

### SqlDialectInterface 建议

```php
interface SqlDialectInterface
{
    public function quoteIdentifier(string $identifier): string;

    public function compileDateGrain(string $field, string $grain): string;

    public function compileLimit(int $limit, ?int $offset = null): string;

    public function compileAggregate(string $function, string $field): string;

    public function supportsFunction(string $function): bool;

    public function getName(): string;
}
```

### StarRocks / Doris 第一版支持

聚合函数：

```text
sum
avg
count
countDistinct
min
max
```

时间颗粒：

```text
year
quarter
month
week
day
hour
```

排序：

```text
asc
desc
```

过滤操作符：

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
between
is_null
is_not_null
```

分页：

```text
limit
offset
```

注意：

1. 不同引擎函数有差异时在 dialect 中处理。
2. 不要在业务代码里写大量 if engine_type。
3. 字段名必须来自 dataset_fields。
4. 操作符必须白名单。
5. 值必须参数绑定或严格安全处理。
6. 不允许前端传原始 SQL 到查询接口。

## 十一、查询执行链路

目标链路：

```text
图表查询请求
    ↓
读取 chart config
    ↓
读取 dataset
    ↓
读取 data_source
    ↓
根据 data_source.type 选择 driver
    ↓
根据 data_source.type 选择 dialect
    ↓
合并业务 filter
    ↓
合并数据权限 filter
    ↓
生成 StarRocks / Doris SQL
    ↓
执行查询
    ↓
记录 query_logs
    ↓
写入 Redis 查询缓存
    ↓
返回图表数据
```

要求：

1. StarRocks / Doris 与 MySQL 查询共用 QueryService 主流程。
2. 只在 driver / dialect 层处理差异。
3. 查询日志中记录 engine_type。
4. 查询失败时返回清晰错误。
5. 不要 fallback 到 MySQL，因为它是不同数据源；除非数据集本身配置了其他 fallback 来源。
6. 查询缓存 key 要包含 data_source_id 和 engine_type。

缓存 key 建议：

```text
bi:chart:{chart_id}:user:{user_id}:engine:{engine_type}:ds:{data_source_id}:query:{query_hash}
```

## 十二、查询日志增强

如果 query_logs 还没有这些字段，建议增加：

```text
engine_type
data_source_type
olap_query_id nullable
explain_text nullable
query_state nullable
```

第一版可以只加：

```text
engine_type
data_source_type
```

记录内容：

```text
starrocks
doris
mysql
clickhouse
```

要求：

1. 查询成功记录 duration_ms。
2. 查询失败记录 error_message。
3. 慢查询继续按已有规则判断。
4. StarRocks / Doris 查询要能在日志中区分。
5. 查询日志页面支持按 engine_type 筛选。

## 十三、Explain / 查询诊断

新增基础诊断接口。

建议 API：

```text
POST /api/datasets/{dataset}/explain
POST /api/charts/{chart}/explain
```

返回：

```text
generated_sql
bindings
engine_type
explain_result
estimated_info nullable
warnings
```

要求：

1. 只允许对当前用户有权限的数据集执行。
2. explain 的 SQL 必须由后端生成。
3. 不允许前端直接提交任意 SQL。
4. explain 失败要返回可读错误。
5. 查询诊断不影响正式查询。

前端可展示：

1. 引擎类型。
2. 生成 SQL。
3. Explain 结果。
4. 查询耗时。
5. 是否命中缓存。
6. 是否慢查询。

## 十四、物化视图支持

本阶段做基础能力，不做复杂自动优化。

StarRocks / Doris 的物化视图更多应作为 OLAP 引擎内部能力管理，而不是 Laravel 自己维护预聚合物理表。

新增可选模块：

```text
OlapMaterializedViewService
```

支持：

1. 查看物化视图列表。
2. 查看物化视图状态。
3. 查看物化视图定义 SQL。
4. 手动刷新物化视图，如果引擎支持。
5. 在文档中说明查询改写由 StarRocks / Doris 优化器完成。
6. 可选提供创建物化视图模板，但第一版不要让前端自由输入危险 SQL。

### API 建议

```text
GET  /api/data-sources/{dataSource}/materialized-views
GET  /api/data-sources/{dataSource}/materialized-views/{name}
POST /api/data-sources/{dataSource}/materialized-views/{name}/refresh
```

如果创建接口风险较高，第一版不提供创建，只做查看和刷新。

### 前端页面建议

在数据源详情中增加：

```text
物化视图
```

展示：

1. 名称。
2. 数据库。
3. 状态。
4. 最近刷新时间，如果能读取。
5. 定义 SQL，如果能读取。
6. 操作：刷新。

## 十五、StarRocks / Doris 与现有加速体系的关系

本阶段要在文档和代码中明确：

### 1. Direct OLAP Source

```text
StarRocks / Doris 作为 data_source.type
BI 直接查询
```

这是本阶段重点。

### 2. Acceleration Target

```text
StarRocks / Doris 作为 acceleration_profiles.engine_type
原始库数据同步到 StarRocks / Doris
```

这是后续扩展，不是本阶段重点。

第一版可以只预留 enum，不实现同步任务。

## 十六、前端改造建议

如果 Vue 前端已经存在，更新以下页面：

### 1. 数据源新增 / 编辑页

数据源类型增加：

```text
StarRocks
Doris
```

表单字段：

```text
host
port
database
username
password
ssl_enabled
options_json
```

按钮：

```text
测试连接
读取表
读取字段
```

### 2. 数据源详情页

增加展示：

1. 数据库列表。
2. 表列表。
3. 视图列表。
4. 字段列表。
5. 物化视图列表。
6. 连接状态。

### 3. 数据集页面

支持从 StarRocks / Doris 数据源创建数据集。

### 4. 图表页面

支持 StarRocks / Doris 数据集查询和图表预览。

### 5. 查询日志页面

增加 engine_type 筛选。

### 6. 查询诊断页面

如果时间允许，增加 Explain 展示。

## 十七、Docker / 本地环境

本阶段不强制在 docker-compose 中部署 StarRocks / Doris，因为完整集群服务较重。

建议策略：

1. README 中说明推荐连接外部 StarRocks / Doris。
2. 如果提供 Docker 示例，放在独立文件中：
   - docker-compose.starrocks.yml
   - docker-compose.doris.yml
3. 不影响主 docker-compose。
4. 不要让默认开发环境变得过重。

如果 Codex 判断本地部署复杂度较高，只做文档，不强制加容器。

## 十八、权限要求

StarRocks / Doris 查询必须复用现有权限体系。

要求：

1. 资源权限生效。
2. 数据源访问权限生效。
3. 数据集访问权限生效。
4. 图表访问权限生效。
5. 行级数据权限合并进 SQL。
6. 列级权限过滤 select 字段。
7. 权限字段必须在 dataset_fields 中存在。
8. 权限条件不允许被前端覆盖。
9. 不允许用户通过 SQL 注入绕过权限。
10. 查询诊断接口也必须校验权限。

## 十九、安全要求

1. 密码加密存储。
2. 日志中不输出密码。
3. SQL 字段名必须白名单。
4. 表名必须来自元数据。
5. 库名必须来自元数据。
6. 操作符必须白名单。
7. 查询 limit 必须有上限。
8. 禁止执行 drop / delete / update / insert / alter 等写操作。
9. 物化视图刷新接口要限制权限。
10. Explain 接口不能执行任意用户 SQL。
11. options_json 不允许注入危险连接参数。
12. API 返回错误不要泄露敏感连接信息。

## 二十、验收标准

完成后需要满足：

1. 可以新增 StarRocks 类型数据源。
2. 可以新增 Doris 类型数据源。
3. 可以测试连接。
4. 可以读取数据库列表。
5. 可以读取表列表。
6. 可以读取字段列表。
7. 可以基于 StarRocks 表创建数据集。
8. 可以基于 Doris 表创建数据集。
9. 可以基于 StarRocks / Doris 数据集创建图表。
10. 图表查询可以正常生成 OLAP SQL。
11. 数据权限可以合并到 StarRocks / Doris 查询。
12. 查询日志能记录 data_source_type / engine_type。
13. 查询缓存 key 能区分 StarRocks / Doris。
14. 可以执行 explain 或返回清晰不支持说明。
15. 可以查看物化视图列表，或文档说明当前未实现原因。
16. 不破坏 MySQL 数据源查询。
17. 不破坏 ClickHouse 加速逻辑。
18. 不破坏图表和仪表盘接口。
19. 尽量通过 php artisan route:list。
20. 尽量通过 php artisan test。
21. 如果有前端，尽量通过 npm run build。
22. README / docs 有完整说明。

## 二十一、文档要求

新增：

```text
docs/bi-olap-starrocks-doris.md
```

内容包括：

1. 为什么 StarRocks / Doris 是一等 OLAP 数据源。
2. 直连 OLAP 数据源和 ClickHouse 加速层的区别。
3. 真实生产环境中的数据链路。
4. Laravel BI 在架构中的职责。
5. StarRocks / Doris 在架构中的职责。
6. 数据源配置方式。
7. 数据集建模流程。
8. SQL 方言适配说明。
9. 权限如何生效。
10. 查询缓存 key 设计。
11. 查询日志字段说明。
12. Explain / 查询诊断说明。
13. 物化视图管理边界。
14. 本阶段不做 CDC / Flink / Kafka 的原因。
15. 后续如何扩展成完整数仓接入方案。

生产环境建议需要包含：

1. StarRocks / Doris 独立集群部署。
2. BI 系统只连接 FE 查询入口。
3. FE 前面可以放负载均衡。
4. 数据同步由独立 ETL / CDC 系统负责。
5. BI 不直接承担大规模数据同步。
6. 高频看板优先使用 OLAP 引擎物化视图。
7. 慢查询通过 query_logs 和引擎自身 Profile / Explain 联合分析。
8. 权限既可以在 BI 层做，也可以结合 OLAP 库账号权限。
9. 大查询需要 limit、超时、并发控制。
10. 元数据缓存需要定期刷新。

更新 README：

1. 如何新增 StarRocks 数据源。
2. 如何新增 Doris 数据源。
3. 如何基于 OLAP 数据源创建数据集。
4. 如何查看查询日志和 Explain。
5. 当前实现边界。

## 二十二、建议执行顺序

第一步：现状扫描

1. 扫描 data_sources 表结构。
2. 扫描 DataSourceController / Service。
3. 扫描 QueryService。
4. 扫描 SQL 生成器。
5. 扫描权限合并逻辑。
6. 扫描 query_logs。
7. 扫描前端数据源页面。

第二步：数据源类型扩展

1. 增加 starrocks / doris 类型。
2. 增加后端校验。
3. 增加前端选项。
4. 增加连接测试。

第三步：Driver 实现

1. StarRocksDataSourceDriver。
2. DorisDataSourceDriver。
3. listDatabases。
4. listTables。
5. listColumns。
6. preview。
7. explain。

第四步：SQL 方言适配

1. StarRocksDialect。
2. DorisDialect。
3. 聚合函数。
4. 时间颗粒。
5. limit / offset。
6. identifier quote。
7. filter operator。

第五步：查询链路接入

1. 根据 data_source.type 选择 driver。
2. 根据 data_source.type 选择 dialect。
3. 生成 SQL。
4. 执行查询。
5. 写 query_logs。
6. 写查询缓存。

第六步：物化视图元数据

1. 查看物化视图列表。
2. 查看状态。
3. 可选刷新。
4. 文档说明边界。

第七步：前端适配

1. 数据源类型选项。
2. 连接测试。
3. 表字段读取。
4. 数据集创建。
5. 图表预览。
6. 查询日志 engine_type 筛选。

第八步：测试和文档

1. route:list。
2. php artisan test。
3. npm run build。
4. README。
5. docs/bi-olap-starrocks-doris.md。

## 二十三、运行命令

尽量运行：

```text
php artisan migrate
php artisan route:list
php artisan test
```

如果有前端：

```text
cd frontend
npm run build
```

如果有代码格式化：

```text
npm run lint
composer test
```

如果无法连接真实 StarRocks / Doris：

1. 保留接口和 driver。
2. 使用 mock 配置或单元测试验证 SQL 生成。
3. 文档说明需要外部 StarRocks / Doris 环境验证连接。

## 二十四、最终输出要求

任务完成后，请输出以下内容并记录到日志文件：

1. 本次完成内容。
2. 修改文件列表。
3. 是否新增 migration。
4. 新增或修改的 Model。
5. 新增或修改的 Service。
6. 新增 Driver 列表。
7. 新增 Dialect 列表。
8. 新增 API 列表。
9. 前端修改页面。
10. StarRocks 数据源接入说明。
11. Doris 数据源接入说明。
12. 查询链路说明。
13. 权限合并说明。
14. 查询日志字段说明。
15. 查询缓存 key 说明。
16. 物化视图支持边界。
17. 运行过的命令。
18. 测试结果。
19. 当前实现边界。
20. 下一阶段建议。