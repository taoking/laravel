# Kafka 消息事件流实践模块计划

更新日期：2026-05-14  
任务编号：P1-04  
状态：待开发  
适用项目：Laravel 13 指标分析平台

本文是 Kafka 专题的执行计划。目标不是只实现一个生产者和消费者，而是在 Laravel 13 项目中建设一个“学习 + 实战 + 面试”一体化的消息事件流实践模块。

## 1. 开发目标

当前项目已经有 Redis Queue，用于异步导入、失败重试和后台任务处理。Kafka 专题需要进一步覆盖事件流和跨系统事件分发能力。

Redis Queue 更适合任务队列：

- 处理明确的后台任务，例如 CSV 导入、导出文件生成、发送通知。
- 任务通常有一个执行方，关注是否执行成功、是否重试、是否失败。
- 更贴近 Laravel Job/Worker 的模型。

Kafka 更适合事件流：

- 记录业务事实，例如指标导入完成、指标数据变更、审计事件创建。
- 一个事件可以被多个消费者组独立消费。
- 更适合日志流、审计流、指标变更流、跨系统事件分发和后续数据同步。
- 通过 topic、partition、offset、consumer group 支持高吞吐和可回放。

本专题通过三个业务事件演示 Kafka 核心能力：

| 事件 | 业务含义 | 演示能力 |
| --- | --- | --- |
| `metric.import.completed` | 指标导入完成 | 事件发布、审计日志、统计刷新、缓存清理 |
| `metric.data.changed` | 指标数据发生变化 | message key、分区局部顺序、缓存刷新、统计重算 |
| `audit.event.created` | 审计事件被投递 | 统一审计收集、消费者组、幂等消费、失败补偿 |

完成后应能作为简历和面试亮点说明：项目不只是使用队列执行任务，还能基于 Kafka 建设事件驱动的数据流，理解消息幂等、顺序性、offset、消费者组、失败补偿和 Redis Queue 与 Kafka 的边界。

## 2. 交付范围

代码交付：

| 类型 | 路径或命名 | 说明 |
| --- | --- | --- |
| Docker | `docker-compose.yml`、`docker/kafka/` | Kafka 单节点开发环境 |
| 配置 | `config/kafka.php` | broker、topic、consumer group、retry、dead letter 配置 |
| 领域模块 | `app/Domains/Messaging` | Kafka 消息模型、Producer、Consumer、Handler |
| 事件定义 | `app/Domains/Messaging/Events` | 三类业务事件 DTO 或 Value Object |
| Producer | `KafkaProducer` | 统一封装消息发布 |
| Consumer | `KafkaConsumer` | 统一封装消费、ack、重试、死信 |
| Handler | `app/Domains/Messaging/Handlers` | 按 event_type 处理业务副作用 |
| 幂等模型 | `ConsumedMessage` | 消费幂等表模型 |
| 迁移 | `create_consumed_messages_table` | 记录消费状态 |
| 命令 | `kafka:topics` | 创建或查看 topic |
| 命令 | `kafka:produce {event}` | 生产测试事件 |
| 命令 | `kafka:consume {consumer_group}` | 启动消费者 |
| 命令 | `kafka:dead-letter:replay` | 死信人工补偿和重新消费 |
| 测试 | `tests/Feature/KafkaMessagingTest.php` | 生产、消费、幂等、失败补偿验收 |
| 文档 | `docs/queue/kafka-practice.md` | 本文档，随实现持续更新 |

约束：

- Kafka 客户端选型必须先写入文档，说明是否使用 `ext-rdkafka`、Laravel Kafka 包或其他兼容方案。
- 本地开发环境必须能通过 Docker 启动。
- 测试环境允许使用 fake producer/consumer，但必须说明 fake 测试和真实 Kafka 集成测试的边界。
- 业务处理必须可重复执行，不允许依赖 Kafka 提供“业务 exactly once”。

## 3. 核心功能清单

必须实现或在实现文档中明确说明：

- Kafka 单节点 Docker 开发环境。
- Topic 设计和命名规则。
- Producer 服务类。
- Consumer 服务类。
- Artisan 生产者命令。
- Artisan 消费者命令。
- 消息 Key 设计。
- partition 内顺序性说明。
- consumer group 说明。
- offset 与 ack 说明。
- 重复消费与幂等表设计。
- 失败重试。
- dead letter topic。
- 消息堆积观察方式。
- consumer rebalance 说明。
- Redis Queue 与 Kafka 对比。
- Feature Test 或集成测试说明。

## 4. Topic 设计

| Topic | 用途 | 建议分区 | 消费者组 | 死信 Topic |
| --- | --- | --- | --- | --- |
| `metrics.import.completed` | 指标导入完成事件 | 3 | `audit-log-consumer`、`metric-summary-consumer`、`cache-refresh-consumer` | `metrics.import.completed.dlq` |
| `metrics.data.changed` | 指标数据变更事件 | 6 | `cache-refresh-consumer`、`metric-summary-consumer` | `metrics.data.changed.dlq` |
| `audit.events` | 审计事件投递 | 3 | `audit-log-consumer` | `audit.events.dlq` |

命名规则：

- Topic 使用复数业务域名加事件类别，例如 `metrics.data.changed`。
- Dead letter topic 统一追加 `.dlq`。
- event_type 使用单数业务事件语义，例如 `metric.data.changed`。
- consumer group 按业务处理目的命名，而不是按机器或进程命名。

## 5. 消息协议

统一消息结构：

```json
{
  "message_id": "uuid",
  "event_type": "metric.import.completed",
  "version": 1,
  "idempotency_key": "metric-import:1001:completed",
  "trace_id": "request-trace-id",
  "created_at": "2026-05-14T10:00:00+08:00",
  "payload": {},
  "meta": {
    "producer": "laravel.metrics-platform",
    "environment": "local"
  }
}
```

统一 headers：

| Header | 说明 |
| --- | --- |
| `event_type` | 事件类型 |
| `version` | 协议版本 |
| `trace_id` | 请求链路追踪号 |
| `message_id` | 消息唯一 ID |
| `idempotency_key` | 消费幂等键 |
| `producer` | 生产者服务名 |
| `created_at` | 消息创建时间 |

版本策略：

- `version` 从 1 开始。
- 新增字段必须保持向后兼容。
- 删除或改变字段语义时必须提升 major 版本，并保留旧版本 handler。
- Consumer 需要按 event_type + version 路由到不同处理器。

## 6. 业务事件设计

### 6.1 metric.import.completed

| 字段 | 设计 |
| --- | --- |
| topic 名称 | `metrics.import.completed` |
| event_type | `metric.import.completed` |
| message key | `import_task:{import_task_id}` |
| version | `1` |
| producer | `ProcessMetricImportJob` 或 `MetricImportService` |
| consumer | `AuditLogEventHandler`、`MetricSummaryRefreshHandler`、`MetricCacheRefreshHandler` |
| 幂等键 | `metric-import:{import_task_id}:completed:v1` |
| 失败处理 | 可重试异常进入重试；超过最大次数进入 `metrics.import.completed.dlq` |

payload 字段：

| 字段 | 说明 |
| --- | --- |
| `import_task_id` | 导入任务 ID |
| `file_id` | 上传文件 ID |
| `status` | 导入结果 |
| `total_rows` | 总行数 |
| `success_rows` | 成功行数 |
| `failed_rows` | 失败行数 |
| `started_at` | 导入开始时间 |
| `finished_at` | 导入结束时间 |
| `operator_id` | 操作人 ID |

headers：

- `event_type=metric.import.completed`
- `version=1`
- `trace_id`
- `message_id`
- `idempotency_key`
- `producer=laravel.metric-import`
- `created_at`

业务用途：

- 写入审计日志。
- 刷新导入任务统计。
- 清理指标列表缓存。
- 触发后续统计重算。

### 6.2 metric.data.changed

| 字段 | 设计 |
| --- | --- |
| topic 名称 | `metrics.data.changed` |
| event_type | `metric.data.changed` |
| message key | `metric:{metric_id}` |
| version | `1` |
| producer | `MetricController`、`MetricService` 或导入 Job |
| consumer | `MetricCacheRefreshHandler`、`MetricSummaryRefreshHandler` |
| 幂等键 | `metric-data:{metric_id}:{change_id}:v1` |
| 失败处理 | 缓存刷新失败可重试；非法 payload 进入死信 |

payload 字段：

| 字段 | 说明 |
| --- | --- |
| `metric_id` | 指标 ID |
| `change_id` | 本次变更 ID |
| `change_type` | `created`、`updated`、`deleted`、`value_imported` |
| `changed_fields` | 变更字段 |
| `region_id` | 地区 ID，可为空 |
| `frequency_id` | 频率 ID，可为空 |
| `occurred_at` | 业务发生时间 |
| `operator_id` | 操作人 ID |

headers：

- `event_type=metric.data.changed`
- `version=1`
- `trace_id`
- `message_id`
- `idempotency_key`
- `producer=laravel.metric-service`
- `created_at`

业务用途：

- 刷新指标详情缓存。
- 刷新热门指标统计。
- 重新计算统计结果。
- 作为后续搜索索引更新入口。

顺序性要求：

- 同一 `metric_id` 的事件应使用同一个 message key。
- 同一指标的变更进入同一 partition 后可保持局部顺序。
- 不要求所有指标之间全局有序。

### 6.3 audit.event.created

| 字段 | 设计 |
| --- | --- |
| topic 名称 | `audit.events` |
| event_type | `audit.event.created` |
| message key | `actor:{actor_id}` 或 `resource:{resource_type}:{resource_id}` |
| version | `1` |
| producer | `RecordOperationLog`、安全中间件、领域服务 |
| consumer | `AuditLogEventHandler` |
| 幂等键 | `audit:{message_id}:v1` 或 `audit:{trace_id}:{action}:{resource}` |
| 失败处理 | 数据库短暂失败可重试；payload 缺关键字段直接死信 |

payload 字段：

| 字段 | 说明 |
| --- | --- |
| `actor_id` | 操作人 ID，可为空 |
| `action` | 操作动作 |
| `resource_type` | 资源类型 |
| `resource_id` | 资源 ID |
| `ip` | 客户端 IP |
| `user_agent` | User-Agent |
| `request_method` | 请求方法 |
| `request_path` | 请求路径 |
| `context` | 扩展上下文 |

headers：

- `event_type=audit.event.created`
- `version=1`
- `trace_id`
- `message_id`
- `idempotency_key`
- `producer=laravel.audit`
- `created_at`

业务用途：

- 统一收集审计日志。
- 和 HTTP trace_id 关联排查请求链路。
- 后续可扩展到安全告警或风控事件。

## 7. 幂等设计

Kafka 可以做到消息持久化、消费者 offset 管理和至少一次消费语义，但不能保证业务副作用只执行一次。消费者处理成功后提交 offset，如果提交 offset 失败，消费者重启后可能再次消费同一条消息。生产者重试、网络抖动、rebalance 也可能导致重复投递或重复消费。

因此消费者必须自己做业务幂等。

建议表：`consumed_messages`

| 字段 | 类型建议 | 说明 |
| --- | --- | --- |
| `id` | bigint | 主键 |
| `message_id` | string | 消息唯一 ID |
| `topic` | string | Topic |
| `partition` | integer | Partition |
| `offset` | bigint | Offset |
| `consumer_group` | string | 消费者组 |
| `event_type` | string | 事件类型 |
| `idempotency_key` | string | 业务幂等键 |
| `status` | string | `processing`、`processed`、`failed`、`dead_lettered` |
| `processed_at` | timestamp nullable | 成功处理时间 |
| `error_message` | text nullable | 错误信息 |
| `created_at` | timestamp | 创建时间 |
| `updated_at` | timestamp | 更新时间 |

索引建议：

- 唯一索引：`consumer_group + idempotency_key`。
- 普通索引：`topic + partition + offset`。
- 普通索引：`status + updated_at`。

处理流程：

1. Consumer 收到消息后解析 headers 和 payload。
2. 使用 `consumer_group + idempotency_key` 尝试写入 `processing` 记录。
3. 如果唯一索引冲突，说明已处理或处理中：
   - `processed`：跳过业务处理并提交 offset。
   - `processing`：根据超时时间判断是否抢占或跳过。
   - `failed`：按重试策略决定是否重试。
4. 在数据库事务内执行业务副作用。
5. 业务成功后将记录更新为 `processed`，写入 `processed_at`。
6. 再手动提交 offset。

关键问题：

- 如果业务成功但提交 offset 失败，消息可能再次被消费。
- 再次消费时幂等表已是 `processed`，消费者跳过业务副作用，然后重新提交 offset。
- 这就是通过幂等表解决重复消费的核心。

幂等键生成规则：

| 事件 | 幂等键 |
| --- | --- |
| `metric.import.completed` | `metric-import:{import_task_id}:completed:v{version}` |
| `metric.data.changed` | `metric-data:{metric_id}:{change_id}:v{version}` |
| `audit.event.created` | `audit:{message_id}:v{version}` 或 `audit:{trace_id}:{action}:{resource_type}:{resource_id}` |

## 8. 顺序性设计

Kafka 只保证同一个 partition 内的消息有序，不保证跨 partition 全局有序。

message key 会参与分区选择。同一个 key 通常会进入同一个 partition，因此可以获得局部顺序。

设计规则：

| 业务 | 是否需要顺序 | message key | 说明 |
| --- | --- | --- | --- |
| 同一指标的数据变更 | 需要 | `metric:{metric_id}` | 保证同一指标的创建、更新、删除按顺序处理 |
| 不同指标之间 | 不需要全局顺序 | 不同 metric key | 可以并行消费，提高吞吐 |
| 导入任务完成事件 | 通常不需要强顺序 | `import_task:{id}` | 每个导入任务独立 |
| 审计事件 | 通常不需要强顺序 | `actor:{id}` 或 `resource:{type}:{id}` | 可按操作人或资源做局部顺序 |

权衡：

- 使用 `metric_id` 作为 key，可以保证同一指标的局部顺序。
- 如果某个 `metric_id` 特别热门，可能导致单个 partition 压力过大。
- 如果业务更关注吞吐，可以使用更分散的 key，例如 `metric:{metric_id}:bucket:{hash}`，但会牺牲严格局部顺序。
- 需要顺序的业务：状态流转、同一指标多次变更、同一资源审计链路。
- 不需要顺序的业务：统计刷新、缓存清理、通知类事件、独立导入任务完成事件。

## 9. 失败、重试与补偿

异常分类：

| 类型 | 示例 | 策略 |
| --- | --- | --- |
| 可重试异常 | 数据库连接失败、Redis 短暂不可用、网络抖动 | 指数退避重试 |
| 不可重试异常 | payload 缺字段、版本不支持、资源不存在且不可恢复 | 直接进入死信 |
| 业务冲突 | 幂等处理中、状态不允许流转 | 记录状态，按业务规则跳过或死信 |

重试策略：

- 每条消息最多重试 3 次。
- 重试间隔建议 5 秒、30 秒、120 秒。
- 每次失败更新 `consumed_messages.error_message`。
- 超过最大次数后投递到 dead letter topic。

Dead letter topic：

| 原 Topic | 死信 Topic |
| --- | --- |
| `metrics.import.completed` | `metrics.import.completed.dlq` |
| `metrics.data.changed` | `metrics.data.changed.dlq` |
| `audit.events` | `audit.events.dlq` |

Dead letter payload：

```json
{
  "original_topic": "metrics.data.changed",
  "original_partition": 1,
  "original_offset": 203,
  "consumer_group": "cache-refresh-consumer",
  "event": {},
  "error_class": "RuntimeException",
  "error_message": "Redis connection failed",
  "attempts": 3,
  "failed_at": "2026-05-14T10:10:00+08:00",
  "trace_id": "request-trace-id"
}
```

人工补偿：

- 通过 `php artisan kafka:dead-letter:list` 查看死信。
- 修复数据或代码后执行 `php artisan kafka:dead-letter:replay --topic=metrics.data.changed.dlq --message-id=...`。
- replay 时必须保留原始 `message_id` 或生成新的补偿 `message_id`，并明确记录补偿来源。
- 毒性消息不能无限重试，必须进入死信并允许人工处理，避免消费者卡死。

## 10. Consumer group、offset、ack、rebalance 与 lag

Consumer group：

- 同一个 consumer group 内，一个 partition 同一时刻只会分配给一个消费者实例。
- 同一个 topic 可以被多个 consumer group 独立消费。
- 审计日志、缓存刷新、统计刷新应使用不同 consumer group，互不影响 offset。

Offset：

- offset 是消费者在 partition 内的消费位置。
- 自动提交 offset 简单但风险更高，可能出现业务未完成但 offset 已提交。
- 本专题要求手动提交 offset：业务成功并写入幂等表后再提交。

Ack：

- 在代码表达中，ack 等价于确认当前消息已成功处理并提交 offset。
- 失败时不提交 offset，按重试或死信策略处理。

Rebalance：

- 当消费者实例增加、减少或心跳超时，Kafka 会重新分配 partition。
- rebalance 期间可能出现消费暂停或重复消费。
- 幂等表是抵御 rebalance 重复消费的核心机制。

Lag：

- lag 是最新 offset 与消费者已提交 offset 的差值。
- lag 持续上升说明消费速度低于生产速度。
- 需要文档记录如何查看 lag，以及如何通过增加 partition、消费者实例、批量处理或优化 handler 降低 lag。

## 11. Artisan 命令设计

Topic 命令：

```bash
php artisan kafka:topics
php artisan kafka:topics --create
```

生产消息：

```bash
php artisan kafka:produce metric.import.completed --import-task-id=1
php artisan kafka:produce metric.data.changed --metric-id=1 --change-type=updated
php artisan kafka:produce audit.event.created --action=metric.updated
```

消费消息：

```bash
php artisan kafka:consume audit-log-consumer
php artisan kafka:consume cache-refresh-consumer
php artisan kafka:consume metric-summary-consumer
```

死信补偿：

```bash
php artisan kafka:dead-letter:list
php artisan kafka:dead-letter:replay --topic=metrics.data.changed.dlq --message-id=...
```

消息堆积观察：

```bash
php artisan kafka:lag
php artisan kafka:lag --group=audit-log-consumer
```

## 12. 测试与验收

必须具备自动化测试或明确的集成测试说明。

基础验收：

- `docker compose up -d` 后能启动 Kafka。
- 能通过命令创建或查看 topic。
- 执行 `php artisan kafka:produce metric.import.completed` 能生产消息。
- 执行 `php artisan kafka:consume audit-log-consumer` 能消费消息。
- 消费后数据库审计日志新增一条记录。
- 同一 `message_id` 重复投递两次，业务日志只写入一次。
- 消费失败时能记录失败状态。
- 超过重试次数后进入 dead letter topic。
- 消费者组内启动两个消费者时，同一 partition 不会被两个消费者同时消费。
- 文档中能解释 offset、consumer group、ack、rebalance、lag、死信、幂等、顺序性。

建议测试：

| 测试 | 目的 |
| --- | --- |
| `it_produces_metric_import_completed_event` | 验证生产事件结构 |
| `it_consumes_audit_event_and_writes_audit_log` | 验证消费副作用 |
| `it_skips_duplicate_message_by_idempotency_key` | 验证幂等 |
| `it_records_failed_message_when_handler_throws` | 验证失败状态 |
| `it_sends_message_to_dead_letter_after_max_attempts` | 验证死信 |
| `it_uses_metric_id_as_partition_key_for_metric_data_changed` | 验证 key 设计 |

测试边界：

- 单元测试可以 fake Kafka Producer。
- Feature Test 可以验证数据库、幂等表和 handler 行为。
- 真实 Kafka 集成测试可以通过独立命令或 CI 服务开关运行，不要求所有本地测试都依赖 Kafka 容器。

## 13. Redis Queue 与 Kafka 对比

| 维度 | Redis Queue | Kafka |
| --- | --- | --- |
| 定位 | 任务队列 | 事件流、日志流、跨系统分发 |
| Laravel 集成 | 原生 Queue/Job/Worker | 需要客户端或扩展集成 |
| 消费模型 | 通常一个任务被一个 worker 执行 | 多 consumer group 可独立消费同一事件 |
| 回放能力 | 弱 | 强，基于 offset 回放 |
| 顺序性 | 队列维度简单顺序 | partition 内有序 |
| 吞吐量 | 适合中等后台任务 | 适合高吞吐事件流 |
| 典型场景 | 导入、导出、通知、邮件 | 审计流、指标变更流、跨系统同步 |
| 复杂度 | 低 | 高，需要处理 partition、offset、rebalance、lag |

结论：

- 后台任务优先 Redis Queue。
- 需要事件订阅、事件回放、跨系统分发、高吞吐日志流时使用 Kafka。
- Kafka 不应替代所有 Laravel Job；边界清晰才是架构能力。

## 14. 面试覆盖

本专题必须能支撑以下资深面试题：

- Kafka 和 Redis Queue 有什么区别？
- Kafka 为什么吞吐量高？
- Kafka 如何保证消息顺序？
- Kafka 能保证消息不丢吗？
- Kafka 能保证消息不重复吗？
- 什么是消费者组？
- 什么是 offset？
- 自动提交 offset 和手动提交 offset 区别？
- 消息重复消费怎么解决？
- 消费失败如何重试？
- 什么是死信队列？
- 什么是消息堆积？
- consumer rebalance 是什么？
- Kafka 适合做任务队列吗？
- Laravel 项目中如何集成 Kafka？
- 如何设计消息事件的 version 和 trace_id？
- 如何保证数据库事务和消息发送的一致性？

必须补充的回答方向：

- Kafka 的高吞吐来自顺序写、批量发送、零拷贝、partition 并行和消费者组扩展。
- Kafka 只保证 partition 内顺序，不保证全局顺序。
- Kafka 可以降低丢消息风险，但业务仍需正确配置 ack、持久化、副本和消费提交策略。
- Kafka 不能保证业务不重复执行，消费者必须做幂等。
- 数据库事务和消息发送一致性建议使用 Outbox Pattern：业务事务内写业务表和 outbox 表，由后台进程投递 Kafka。

## 15. 项目包装表达

面试表达：

> 我在 Laravel 13 指标分析平台里没有把 Kafka 当成普通队列使用，而是把它设计成消息事件流模块。项目里 Redis Queue 负责 CSV 导入、导出这类后台任务，Kafka 负责指标导入完成、指标数据变更和审计事件这类业务事实的分发。  
> 我设计了 topic、message key、事件 version、trace_id、consumer group、手动 offset 提交和消费幂等表。对于同一指标的变更事件，我使用 metric_id 作为 key，让它们进入同一 partition 保持局部顺序；对于重复消费，我通过 consumer_group + idempotency_key 做唯一约束，业务成功后再提交 offset。  
> 失败处理上，我区分可重试异常和不可重试异常，超过重试次数后进入 dead letter topic，并提供人工补偿和重新消费命令。这个模块能说明我理解事件驱动架构、Kafka 与 Redis Queue 的边界，也能把消息顺序性、幂等、失败补偿和消费者组这些面试高频问题落到 Laravel 企业项目代码里。

## 16. 实施步骤

建议拆成 5 个迭代：

1. 环境与选型：确定 Kafka 客户端，补 Docker 单节点 Kafka，补 `config/kafka.php`。
2. 消息协议：实现事件 DTO、topic 配置、message key、headers、version。
3. 生产消费：实现 Producer、Consumer、Artisan produce/consume 命令。
4. 可靠性：实现 `consumed_messages`、手动 offset、重试、dead letter、replay。
5. 验收包装：补测试、lag 观察命令、Redis Queue 对比文档、面试题和项目表达。

完成定义：

- 本文所有“核心功能清单”均有代码或明确实现说明。
- 三个业务事件均可生产、消费和验证业务副作用。
- 幂等、顺序性、失败补偿均有测试或集成测试说明。
- `docs/pending-development-tasks.md` 将 P1-04 状态更新为“已完成”。
- `docs/learning-index.md` 增加实际代码入口和验收命令。
