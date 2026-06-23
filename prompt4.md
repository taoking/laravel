继续执行当前 Laravel 13 + Docker BI 分析工具项目的下一阶段任务：

# Phase 10.3：基于查询日志的加速推荐与自动刷新

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
11. query_logs 已记录 acceleration_hit、acceleration_mode、fallback_used、fallback_reason。
12. acceleration_profiles、acceleration_columns、acceleration_tasks 已存在。
13. acceleration_aggregate_definitions、acceleration_aggregate_columns 已存在或计划存在。

当前加速链路大致是：

```text
Redis 查询结果缓存
    ↓
ClickHouse 预聚合表
    ↓
ClickHouse 明细表
    ↓
原始数据源 fallback
```

本阶段目标是在已有加速体系上增加：

```text
查询日志分析
加速建议
定时刷新
效果评估
```

不要重写 Phase 10.1 和 Phase 10.2 的加速主流程。

## 二、本阶段目标

实现一套最小可用的加速推荐与自动刷新能力：

1. 基于 query_logs 分析慢查询。
2. 基于 query_logs 分析高频图表。
3. 基于 query_logs 分析高频维度、指标、过滤字段组合。
4. 自动生成预聚合建议。
5. 支持用户接受建议并创建 aggregate definition。
6. 支持定时刷新 detail_table 和 aggregate_table。
7. 支持记录刷新计划和刷新结果。
8. 支持对比原始查询、明细表加速、预聚合加速的耗时。
9. 支持加速收益统计。
10. 支持文档说明生产环境如何做增量刷新、CDC、物化视图。

## 三、本阶段明确不做

本阶段不要做：

1. 不做真正的 Kafka / Debezium / Flink CDC。
2. 不做复杂机器学习推荐算法。
3. 不做商业 BI 级别自动建模器。
4. 不做 StarRocks / Doris 完整落地。
5. 不做 ClickHouse 集群部署。
6. 不做复杂成本优化器。
7. 不做跨数据集 Join 自动推荐。
8. 不重写查询引擎。
9. 不重写权限系统。
10. 不破坏已有 ClickHouse 明细表和预聚合表加速。

本阶段只做：

```text
基于 query_logs 的规则化推荐
+
手动确认创建预聚合
+
定时刷新任务
+
加速效果统计
```

## 四、执行前先扫描

请先阅读：

1. plan.md
2. docs/bi-acceleration-clickhouse.md
3. docs/bi-acceleration-aggregate.md
4. 当前 Acceleration 模块代码
5. query_logs 相关 Model、migration、Service
6. acceleration_profiles 相关代码
7. acceleration_aggregate_definitions 相关代码
8. acceleration_tasks 相关代码
9. Chart / Dashboard 查询接口
10. QueryService / QueryEngine
11. 缓存失效相关 Service
12. Laravel Scheduler 相关配置
13. routes/api.php
14. README.md
15. docker-compose.yml

要求：

1. 不要重复创建已有 Service。
2. 优先复用现有 acceleration_tasks。
3. 自动推荐只生成建议，不要自动创建物理表。
4. 用户确认后才创建预聚合配置。
5. 自动刷新必须可关闭。

## 五、核心能力设计

本阶段新增四个能力：

```text
1. Query Log Analyzer：查询日志分析器
2. Acceleration Recommendation：加速推荐
3. Refresh Scheduler：加速表自动刷新
4. Acceleration Benefit Report：加速收益报表
```

整体流程：

```text
query_logs
    ↓
分析最近 7 天 / 30 天查询
    ↓
找出慢查询、高频查询、高频图表
    ↓
提取 dataset_id、chart_id、dimensions、metrics、filters、time_grain
    ↓
生成 acceleration_recommendations
    ↓
用户查看建议
    ↓
用户接受建议
    ↓
创建 aggregate definition
    ↓
触发 build_aggregate 任务
    ↓
定时刷新
    ↓
记录加速收益
```

## 六、数据库表设计

### 1. 新增 acceleration_recommendations

用于保存系统生成的加速建议。

字段建议：

```text
id
dataset_id
chart_id nullable
dashboard_id nullable
recommendation_type       aggregate_table / detail_table / index / cache
status                    pending / accepted / rejected / expired / created
priority                  low / medium / high
reason
dimensions_json
metrics_json
filters_json
time_field nullable
time_grain nullable
estimated_query_count
estimated_avg_duration_ms
estimated_max_duration_ms
estimated_total_duration_ms
estimated_benefit_score
source_query_log_ids_json
created_profile_id nullable
created_aggregate_definition_id nullable
accepted_by nullable
accepted_at nullable
rejected_by nullable
rejected_at nullable
expires_at nullable
created_at
updated_at
```

说明：

1. recommendation_type 第一版重点支持 aggregate_table。
2. detail_table、index、cache 可以先预留。
3. status 用于控制建议生命周期。
4. estimated_benefit_score 用于排序。
5. source_query_log_ids_json 保存产生建议的 query_log ID。

### 2. 新增 acceleration_refresh_schedules

用于配置加速表刷新计划。

字段建议：

```text
id
target_type               detail_profile / aggregate_definition
target_id
refresh_type              manual / hourly / daily / weekly / cron
cron_expression nullable
enabled
last_run_at nullable
next_run_at nullable
last_task_id nullable
last_status nullable
last_error_message nullable
created_by nullable
created_at
updated_at
```

说明：

1. detail_profile 对应 ClickHouse 明细表。
2. aggregate_definition 对应预聚合表。
3. 第一版可以只实现 daily / hourly / manual。
4. cron_expression 可以预留。
5. Laravel Scheduler 每分钟或每 5 分钟扫描需要执行的计划。

### 3. 新增 acceleration_benefit_reports

用于保存加速收益快照。

字段建议：

```text
id
dataset_id nullable
chart_id nullable
dashboard_id nullable
acceleration_profile_id nullable
aggregate_definition_id nullable
report_date
query_count
raw_query_count
detail_hit_count
aggregate_hit_count
cache_hit_count
fallback_count
avg_raw_duration_ms nullable
avg_detail_duration_ms nullable
avg_aggregate_duration_ms nullable
avg_cache_duration_ms nullable
estimated_saved_ms
created_at
updated_at
```

说明：

1. 用于展示加速收益。
2. 可以每天生成一次。
3. 第一版也可以通过 API 实时聚合 query_logs，不强制落表。
4. 如果实现成本低，可以落表。

## 七、服务类设计

新增或扩展：

```text
app/Services/Acceleration/
```

建议新增：

```text
QueryLogAnalysisService
AccelerationRecommendationService
AccelerationRecommendationBuilder
AccelerationRefreshScheduleService
AccelerationRefreshRunner
AccelerationBenefitReportService
```

### 1. QueryLogAnalysisService

负责分析 query_logs。

需要支持：

1. 查询最近 N 天日志。
2. 按 dataset_id 聚合。
3. 按 chart_id 聚合。
4. 按 dashboard_id 聚合。
5. 统计慢查询。
6. 统计高频查询。
7. 统计 acceleration_mode 分布。
8. 统计 fallback 原因。
9. 统计 cache_hit 比例。
10. 提取常见 dimensions、metrics、filters、time_grain。

建议方法：

```php
analyzeSlowQueries(int $days = 7): array

analyzeFrequentCharts(int $days = 7): array

analyzeDatasetQueryPatterns(int $datasetId, int $days = 7): array

analyzeFallbackReasons(int $days = 7): array
```

### 2. AccelerationRecommendationService

负责生成和管理推荐。

建议方法：

```php
generateRecommendations(int $days = 7): int

listRecommendations(array $filters = []): LengthAwarePaginator

acceptRecommendation(int $recommendationId, int $userId): mixed

rejectRecommendation(int $recommendationId, int $userId, ?string $reason = null): void

expireOldRecommendations(): int
```

推荐规则第一版采用简单规则：

#### 规则 1：慢查询推荐预聚合

如果某个 chart 或 dataset 查询满足：

```text
query_count >= 5
avg_duration_ms >= 3000
acceleration_mode != aggregate_table
```

则推荐 aggregate_table。

#### 规则 2：高频查询推荐预聚合

如果某个图表最近 7 天查询次数：

```text
query_count >= 30
```

并且维度、指标组合稳定，则推荐 aggregate_table。

#### 规则 3：fallback 高频推荐修复

如果某个 dataset 经常 fallback：

```text
fallback_count >= 5
```

则生成建议，reason 说明 fallback_reason，例如字段映射缺失、权限字段缺失、预聚合维度不匹配。

#### 规则 4：明细表加速推荐

如果某个 dataset 大量查询仍然走 raw：

```text
raw_query_count >= 20
avg_duration_ms >= 2000
```

并且没有 active detail profile，则推荐 detail_table。

第一版重点落地 aggregate_table 推荐。

### 3. AccelerationRecommendationBuilder

负责把 query_logs 中的查询模式转换成 aggregate definition 草案。

输入：

```text
dataset_id
chart_id
dimensions
metrics
filters
time_field
time_grain
```

输出：

```text
aggregate definition draft
```

要求：

1. dimensions 不超过配置上限。
2. metrics 不超过配置上限。
3. 只使用 dataset_fields 中存在的字段。
4. 只使用支持的聚合函数。
5. avg 要按已有预聚合规则处理。
6. 权限字段如果经常出现，建议加入预聚合维度或说明风险。
7. 不自动创建物理表，只生成 recommendation。

### 4. AccelerationRefreshScheduleService

负责刷新计划 CRUD。

支持：

1. 创建刷新计划。
2. 启用刷新计划。
3. 禁用刷新计划。
4. 更新 next_run_at。
5. 查询待执行计划。
6. 记录上次执行结果。

### 5. AccelerationRefreshRunner

负责执行刷新计划。

流程：

```text
Scheduler 调用
    ↓
查找 due schedules
    ↓
根据 target_type 判断刷新对象
    ↓
创建 acceleration_task
    ↓
派发对应 Job
    ↓
更新 last_run_at / next_run_at / last_task_id
```

要求：

1. 不在 scheduler 中直接执行大量同步。
2. 必须派发队列 Job。
3. 每次执行要记录任务。
4. 失败时记录 last_error_message。
5. 刷新成功后清理相关缓存。

### 6. AccelerationBenefitReportService

负责统计加速收益。

统计来源：

```text
query_logs
```

指标：

1. 查询总次数。
2. raw 查询次数。
3. detail_table 命中次数。
4. aggregate_table 命中次数。
5. cache 命中次数。
6. fallback 次数。
7. 平均 raw 耗时。
8. 平均 detail_table 耗时。
9. 平均 aggregate_table 耗时。
10. 估算节省时间。

估算节省时间第一版可以简单计算：

```text
estimated_saved_ms =
  raw_avg_duration_ms * accelerated_query_count
  - actual_accelerated_total_duration_ms
```

如果数据不足，允许返回 null 或说明无法估算。

## 八、配置文件调整

更新：

```text
config/bi_acceleration.php
```

增加：

```php
'recommendation' => [
    'enabled' => env('BI_ACCELERATION_RECOMMENDATION_ENABLED', true),
    'analysis_days' => env('BI_ACCELERATION_ANALYSIS_DAYS', 7),
    'slow_query_threshold_ms' => env('BI_ACCELERATION_RECOMMEND_SLOW_MS', 3000),
    'min_query_count' => env('BI_ACCELERATION_RECOMMEND_MIN_QUERY_COUNT', 5),
    'high_frequency_threshold' => env('BI_ACCELERATION_RECOMMEND_HIGH_FREQUENCY', 30),
    'max_dimensions' => env('BI_ACCELERATION_RECOMMEND_MAX_DIMENSIONS', 5),
    'max_metrics' => env('BI_ACCELERATION_RECOMMEND_MAX_METRICS', 10),
],

'refresh' => [
    'enabled' => env('BI_ACCELERATION_REFRESH_ENABLED', true),
    'scheduler_interval_minutes' => env('BI_ACCELERATION_REFRESH_INTERVAL_MINUTES', 5),
],
```

.env.example 增加：

```text
BI_ACCELERATION_RECOMMENDATION_ENABLED=true
BI_ACCELERATION_ANALYSIS_DAYS=7
BI_ACCELERATION_RECOMMEND_SLOW_MS=3000
BI_ACCELERATION_RECOMMEND_MIN_QUERY_COUNT=5
BI_ACCELERATION_RECOMMEND_HIGH_FREQUENCY=30
BI_ACCELERATION_RECOMMEND_MAX_DIMENSIONS=5
BI_ACCELERATION_RECOMMEND_MAX_METRICS=10
BI_ACCELERATION_REFRESH_ENABLED=true
BI_ACCELERATION_REFRESH_INTERVAL_MINUTES=5
```

## 九、Artisan 命令

新增命令：

```text
php artisan bi:acceleration:recommend
php artisan bi:acceleration:refresh-due
php artisan bi:acceleration:benefit-report
```

### 1. bi:acceleration:recommend

作用：

```text
分析 query_logs 并生成 acceleration_recommendations
```

参数建议：

```text
--days=7
--dataset=
--dry-run
```

输出：

1. 扫描日志数量。
2. 生成建议数量。
3. 慢查询数量。
4. 高频图表数量。
5. fallback 高频原因。

### 2. bi:acceleration:refresh-due

作用：

```text
扫描到期刷新计划并派发刷新任务
```

参数建议：

```text
--dry-run
```

输出：

1. 到期计划数量。
2. 派发任务数量。
3. 跳过原因。

### 3. bi:acceleration:benefit-report

作用：

```text
生成加速收益统计
```

参数建议：

```text
--date=
--days=1
```

输出：

1. 查询总数。
2. 加速命中数。
3. fallback 数。
4. 估算节省时间。

## 十、Laravel Scheduler

在 Laravel Scheduler 中注册：

```text
bi:acceleration:refresh-due
```

建议每 5 分钟执行一次。

如果当前项目已经有 Console Kernel 或 Laravel 13 的新式调度配置，请按项目实际版本接入。

要求：

1. Scheduler 只负责扫描和派发 Job。
2. 不直接执行重任务。
3. README 说明需要运行 scheduler。
4. Docker 中如已有 scheduler 服务，需要确认命令可用。

## 十一、API 设计

新增接口前先检查当前路由风格。

建议新增：

### 1. 推荐接口

```text
GET    /api/acceleration/recommendations
POST   /api/acceleration/recommendations/generate
GET    /api/acceleration/recommendations/{recommendation}
POST   /api/acceleration/recommendations/{recommendation}/accept
POST   /api/acceleration/recommendations/{recommendation}/reject
```

列表支持筛选：

```text
dataset_id
chart_id
recommendation_type
status
priority
```

### 2. 刷新计划接口

```text
GET    /api/acceleration/refresh-schedules
POST   /api/acceleration/refresh-schedules
GET    /api/acceleration/refresh-schedules/{schedule}
PUT    /api/acceleration/refresh-schedules/{schedule}
DELETE /api/acceleration/refresh-schedules/{schedule}
POST   /api/acceleration/refresh-schedules/{schedule}/enable
POST   /api/acceleration/refresh-schedules/{schedule}/disable
POST   /api/acceleration/refresh-schedules/{schedule}/run-now
```

### 3. 收益报表接口

```text
GET /api/acceleration/benefit-report
GET /api/acceleration/benefit-report/datasets/{dataset}
GET /api/acceleration/benefit-report/charts/{chart}
```

返回示例：

```json
{
  "query_count": 120,
  "raw_query_count": 20,
  "detail_hit_count": 50,
  "aggregate_hit_count": 40,
  "cache_hit_count": 10,
  "fallback_count": 3,
  "avg_raw_duration_ms": 4200,
  "avg_detail_duration_ms": 850,
  "avg_aggregate_duration_ms": 120,
  "estimated_saved_ms": 168000
}
```

## 十二、接受推荐流程

用户接受 aggregate_table 推荐时：

```text
recommendation status = accepted
    ↓
根据 recommendation 中的 dimensions / metrics / time_grain
    ↓
创建 aggregate definition
    ↓
recommendation.created_aggregate_definition_id = aggregate_definition.id
    ↓
recommendation status = created
    ↓
可选择立即派发 build_aggregate Job
```

要求：

1. 不要重复创建相同定义。
2. 如果已经存在等价 aggregate definition，提示或复用。
3. 创建前校验 dataset_fields。
4. 创建前校验 detail_profile 是否 active。
5. 创建失败要记录错误。
6. 接受推荐后不要立即阻塞等待构建完成，应派发 Job。

## 十三、推荐去重规则

避免每次分析都生成重复建议。

去重维度：

```text
dataset_id
recommendation_type
dimensions_json
metrics_json
time_grain
time_field
```

如果已有 pending / accepted / created 的相同建议，不重复创建。

如果已有 active aggregate definition 能覆盖该建议，不再生成建议。

## 十四、刷新计划逻辑

### 1. detail_profile 刷新

target_type：

```text
detail_profile
```

执行：

```text
派发 BuildAccelerationTableJob 或 RefreshDetailAccelerationJob
```

第一版如果没有增量刷新，可以直接重建。

### 2. aggregate_definition 刷新

target_type：

```text
aggregate_definition
```

执行：

```text
派发 BuildAggregateTableJob 或 RefreshAggregateTableJob
```

第一版可以直接重建预聚合表。

### 3. next_run_at 计算

hourly：

```text
当前时间 + 1 小时
```

daily：

```text
第二天相同时间
```

weekly：

```text
下周相同时间
```

manual：

```text
不自动计算
```

cron_expression：

```text
第一版可以预留，不强制完整支持
```

## 十五、前端页面建议

如果 Vue 前端已经落地，可以增加：

```text
查询加速管理
  - 加速建议
  - 刷新计划
  - 加速收益
```

### 1. 加速建议页面

功能：

1. 建议列表。
2. 筛选 dataset、chart、status、priority。
3. 展示推荐原因。
4. 展示维度、指标、时间颗粒。
5. 展示查询次数、平均耗时、预估收益。
6. 接受建议。
7. 拒绝建议。
8. 接受后跳转到 aggregate definition。

### 2. 刷新计划页面

功能：

1. 刷新计划列表。
2. 新建刷新计划。
3. 启用 / 禁用。
4. 立即执行。
5. 查看 last_run_at、next_run_at、last_status。
6. 查看 last_error_message。

### 3. 加速收益页面

功能：

1. 总查询次数。
2. 加速命中率。
3. 缓存命中率。
4. fallback 次数。
5. 平均耗时对比。
6. 估算节省时间。
7. 按 dataset / chart 查看。

如果前端未完成，本阶段只做 API 和文档。

## 十六、权限和安全要求

1. 只有管理员或有管理权限的用户可以生成推荐。
2. 只有管理员或有数据集管理权限的用户可以接受推荐。
3. 普通用户不能创建物理加速表。
4. 不允许前端传入物理表名。
5. 推荐生成必须基于后端已有 query_logs。
6. 接受推荐时必须重新校验字段和权限。
7. 刷新计划必须校验目标对象是否存在。
8. 不能因为推荐或刷新失败影响原始图表查询。

## 十七、验收标准

完成后需要满足：

1. 可以运行 `php artisan bi:acceleration:recommend`。
2. 命令能基于 query_logs 生成 acceleration_recommendations。
3. 相同推荐不会重复生成。
4. 可以通过 API 查看推荐列表。
5. 可以接受 aggregate_table 推荐并创建 aggregate definition。
6. 可以拒绝推荐。
7. 可以创建 refresh schedule。
8. 可以运行 `php artisan bi:acceleration:refresh-due`。
9. 到期刷新计划会派发对应任务。
10. 刷新计划执行结果会记录。
11. 可以查看加速收益报表。
12. query_logs 能支撑收益统计。
13. 权限校验生效。
14. 不破坏现有明细表加速。
15. 不破坏现有预聚合表加速。
16. 不破坏原始查询 fallback。
17. migration 可以正常执行和回滚。
18. 尽量通过 `php artisan test`。
19. 尽量通过 `php artisan route:list`。
20. 文档完整说明当前边界。

## 十八、文档要求

新增或更新：

```text
docs/bi-acceleration-recommendation.md
```

内容包括：

1. 为什么需要加速推荐。
2. query_logs 如何用于加速分析。
3. 慢查询推荐规则。
4. 高频图表推荐规则。
5. fallback 原因分析。
6. acceleration_recommendations 表说明。
7. refresh_schedules 表说明。
8. benefit report 统计逻辑。
9. 接受推荐后的流程。
10. 定时刷新流程。
11. 当前规则推荐的限制。
12. 后续如何接入更复杂推荐算法。
13. 生产环境如何做增量刷新、CDC、调度和监控。

更新 README：

1. 如何运行推荐命令。
2. 如何运行刷新命令。
3. 如何配置 scheduler。
4. 如何查看收益报表。

## 十九、运行命令

尽量运行：

```text
php artisan migrate
php artisan route:list
php artisan test
php artisan bi:acceleration:recommend --dry-run
php artisan bi:acceleration:refresh-due --dry-run
php artisan bi:acceleration:benefit-report
```

如果有队列：

```text
php artisan queue:work
```

如果有前端：

```text
cd frontend
npm run build
```

## 二十、代码要求

1. 不要大范围重构已有查询引擎。
2. 推荐系统必须是可选增强。
3. 推荐失败不能影响查询。
4. 刷新失败不能影响查询。
5. 接受推荐时必须重新校验字段。
6. 不允许前端传物理表名。
7. 不允许拼接未校验 SQL。
8. migration 必须可回滚。
9. 配置必须走 env。
10. 命令和 API 都要有清晰错误提示。
11. 文档必须说明边界。

## 二十一、当前阶段不做的内容

本阶段不做：

1. 机器学习推荐。
2. 自动创建物理表。
3. 实时 CDC。
4. Flink 流式聚合。
5. StarRocks / Doris 自动改写。
6. 多层 Cube。
7. 成本优化器。
8. 完整商业 BI 自动调优平台。

只做规则化推荐和定时刷新最小闭环。

## 二十二、最终输出要求

任务完成后，请输出：

1. 本次完成内容。
2. 修改文件列表。
3. 新增 migration 列表。
4. 新增 Model 列表。
5. 新增 Service 列表。
6. 新增 Artisan Command 列表。
7. 新增 API 列表。
8. 新增或更新文档位置。
9. 推荐规则说明。
10. 接受推荐流程说明。
11. 刷新计划流程说明。
12. 加速收益统计说明。
13. 运行过的命令。
14. 测试结果。
15. 当前实现边界。
16. 遗留问题。
17. 下一阶段建议。