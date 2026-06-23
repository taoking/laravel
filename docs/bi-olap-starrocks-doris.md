# StarRocks / Doris 一等 OLAP 数据源

本文档对应 Phase 10.4，说明 StarRocks / Doris 作为 `data_sources.type` 直连数据源的接入方式和边界。

## 定位

StarRocks / Doris 在本阶段不是 ClickHouse 加速层的替代实现，也不写入 `acceleration_profiles`。它们作为企业数仓或湖仓中的分析库，由 BI 系统直接连接、读取元数据、建模、生成 SQL 并查询。

```text
ETL / CDC / Flink / DataX / SeaTunnel
  -> StarRocks / Doris
  -> Laravel BI data_sources
  -> datasets
  -> charts / dashboards
```

ClickHouse 加速层的路径仍然是：

```text
MySQL / 导入表
  -> acceleration_profiles
  -> ClickHouse 明细表 / 预聚合表
  -> QueryService 自动路由
```

## 数据源配置

后端支持的数据源类型新增：

```text
starrocks
doris
```

两者第一版均通过 MySQL 协议连接。默认端口为 `9030`，但端口不写死，新增数据源时可以覆盖。

主要字段：

- `host`
- `port`
- `database_name`
- `username`
- `password`
- `charset`
- `timezone`
- `options_json.timeout`
- `options_json.ssl_enabled`

`.env.example` 提供了可选示例变量，但系统实际连接信息以 `data_sources` 表为准。主 `docker-compose.yml` 不默认部署 StarRocks / Doris，生产环境建议连接外部独立集群。

## 元数据能力

新增 API：

```text
GET  /api/data-sources/{data_source}/databases
GET  /api/data-sources/{data_source}/tables
GET  /api/data-sources/{data_source}/views
GET  /api/data-sources/{data_source}/tables/{table}/fields
POST /api/data-sources/{data_source}/tables/{table}/preview
```

元数据统一归一成项目内部结构：

- 表：`table_name`、`table_comment`、`table_type`、`row_count_estimate`
- 字段：`field_name`、`data_type`、`normalized_type`、`is_nullable`、`is_primary_key`、`default_value`

字段类型归一化：

```text
int / bigint       -> integer
decimal / numeric  -> decimal
float / double     -> float
date               -> date
datetime/timestamp -> datetime
json               -> json
boolean            -> boolean
其他               -> string
```

表名、字段名和物化视图名必须通过安全 identifier 校验。查询接口不接受前端传入原始 SQL。

## 数据集建模

StarRocks / Doris 表可以直接创建 dataset：

1. 新增 `starrocks` 或 `doris` 数据源。
2. 测试连接。
3. 同步或读取表字段元数据。
4. 基于表创建 dataset。
5. 调整字段维度、指标、别名、默认聚合和时间字段。
6. 基于 dataset 创建图表和仪表盘。

数据不会导入 Laravel MySQL，也不会自动同步到 ClickHouse。数据治理、入仓、增量同步由外部 ETL / CDC 系统负责。

## SQL 方言

新增方言类：

```text
App\Modules\Query\Dialects\MySqlDialect
App\Modules\Query\Dialects\StarRocksDialect
App\Modules\Query\Dialects\DorisDialect
```

QueryService 会根据 `dataset.dataSource.type` 选择方言。第一版 StarRocks / Doris 使用 MySQL 协议兼容函数：

- identifier：反引号 quote
- 聚合：`sum`、`avg`、`count`、`countDistinct`、`min`、`max`
- 时间颗粒：`year`、`quarter`、`month`、`week`、`day`、`hour`、`minute`
- 分页：`limit ... offset ...`

字段必须来自 `dataset_fields`，排序字段必须是已选择的维度或指标别名，过滤操作符走白名单并使用参数绑定。

## 权限

StarRocks / Doris 查询复用现有权限链路：

- 资源权限：dataset/chart/dashboard 访问控制。
- 列权限：隐藏字段不能被 select/filter 使用。
- 行级权限：`data_permission_rules` 被编译成额外 `where` 条件。
- Explain 接口同样执行权限校验。

权限条件与用户过滤条件一起进入后端生成的 SQL，前端无法覆盖或删除。

## 查询日志

`query_logs` 新增：

```text
engine_type
data_source_type
```

含义：

- `data_source_type`：dataset 原始数据源类型，例如 `mysql`、`starrocks`、`doris`。
- `engine_type`：实际执行查询的引擎。直连 OLAP 时为 `starrocks` 或 `doris`；ClickHouse 加速命中时为 `clickhouse`；源库查询时为数据源类型。

查询日志页面支持按 `engine_type` 筛选。

## 查询缓存

查询缓存 key 增加引擎和数据源段，避免不同来源的同形 SQL 互相污染：

```text
bi:chart:{chart_id}:query:{scope}:engine:{engine_type}:ds:{data_source_id}:acc:{...}:{query_hash}
bi:query:{scope}:engine:{engine_type}:ds:{data_source_id}:acc:{...}:{query_hash}
```

ClickHouse 加速字段仍然保留 `acceleration_hit`、profile、version 和 aggregate 信息。

## Explain / 查询诊断

新增 API：

```text
POST /api/datasets/{dataset}/explain
POST /api/charts/{chart}/explain
```

返回：

- `generated_sql`
- `bindings`
- `query_hash`
- `engine_type`
- `data_source_type`
- `explain_result`
- `estimated_info`
- `warnings`
- `elapsed_ms`

Explain 只对后端根据 dataset/chart 配置生成的 SQL 执行，不接受任意 SQL。

## 物化视图

新增 API：

```text
GET  /api/data-sources/{data_source}/materialized-views
GET  /api/data-sources/{data_source}/materialized-views/{name}
POST /api/data-sources/{data_source}/materialized-views/{name}/refresh
```

第一版只做元数据展示和手动刷新，不提供创建物化视图接口。查询改写由 StarRocks / Doris 优化器完成，BI 不维护物化视图依赖图，也不自动生成危险 DDL。

如果具体引擎版本没有在 `information_schema.TABLES` 中暴露物化视图，列表可能为空；仍可在文档和运维侧结合引擎自身 Profile / Explain 分析。

## 生产建议

- StarRocks / Doris 独立集群部署，BI 只连接 FE 查询入口。
- FE 前可以放负载均衡。
- 数据同步由独立 ETL / CDC / Flink / Kafka 链路负责。
- BI 不承担大规模入仓、Routine Load、Stream Load 或节点管理。
- 高频看板优先在 OLAP 引擎内建设物化视图。
- 慢查询通过 `query_logs`、Explain 和引擎 Profile 联合排查。
- 权限可以在 BI 层做，也可以结合 OLAP 库账号权限。
- 大查询需要配置 timeout、limit、并发控制和网关保护。
- 元数据缓存默认短期缓存，表结构变化后可手动同步刷新。

## 当前边界

- 不部署 StarRocks / Doris 集群。
- 不实现 CDC / Kafka / Flink / Routine Load。
- 不自动建仓或自动创建物化视图。
- 不把 StarRocks / Doris 接入 ClickHouse acceleration profile。
- 不做复杂 Cost Based Optimizer。
- StarRocks / Doris 方言第一版采用 MySQL 协议兼容函数，复杂函数差异后续在 dialect 类中扩展。
