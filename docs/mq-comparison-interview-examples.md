# MQ Comparison Interview Examples

本文档对应长期计划中的 Kafka/RocketMQ 对比专题，用于在已有 RabbitMQ 可运行示例之外，补齐 MQ 选型、可靠性、顺序、重复消费和消息堆积面试点。

## 学习目标

- 能区分 RabbitMQ、Kafka、RocketMQ 的核心模型。
- 能根据业务场景选择任务队列、事件流或交易消息。
- 能说明消息可靠性不是单点能力，而是生产、Broker、消费、补偿的端到端设计。
- 能回答顺序消息、重复消费、消息堆积、死信、事务消息和 outbox。

## 源码入口

- 示例逻辑：`app/Learning/LaravelInterview/Support/MessageQueueComparison.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewMqCompareCommand.php`
- 测试：`tests/Feature/InterviewMqCompareCommandTest.php`

## 运行方式

```bash
docker compose exec laravel.test php artisan interview:mq-compare
```

输出完整 JSON：

```bash
docker compose exec laravel.test php artisan interview:mq-compare --json
```

## 对比结论

### RabbitMQ

RabbitMQ 更像业务任务和消息路由中心。Exchange、Queue、Binding、Routing Key 能表达复杂路由；ACK、NACK、死信、重试队列适合业务任务失败处理。

适合：

- 订单状态异步流转。
- 邮件、短信、站内信通知。
- 需要复杂 routing key、死信和补偿的业务队列。

### Kafka

Kafka 更像可回放的分布式日志。核心是 topic、partition、offset 和 consumer group。分区内有序，多消费者组可以独立消费同一事件流。

适合：

- 用户行为日志。
- CDC、埋点、数据同步。
- 高吞吐、多订阅方、可回放事件流。

### RocketMQ

RocketMQ 更贴近交易业务消息，常见能力包括延迟消息、顺序消息、事务消息、消费重试和死信。

适合：

- 交易系统最终一致。
- 订单超时关闭。
- 需要事务消息或业务延迟消息的场景。

## 面试问答

### 如何保证消息不丢？

生产端要有发送确认或 outbox；Broker 要持久化并正确配置副本或镜像；消费端要手动 ACK；失败后要有重试、死信、告警和补偿。任何单点机制都不能单独保证端到端可靠。

### 如何保证消息顺序？

通常只能保证局部顺序。用订单号、用户 ID 等业务 key 把同一实体的消息路由到同一分区或同一队列，再由单消费者或有序消费模型处理。全局顺序成本很高，业务上要尽量避免。

### 重复消费如何处理？

重复消费是常态。消费者必须用业务幂等键、唯一索引、消费记录表或状态机去重。MQ 的唯一消息 ID 不能替代业务幂等。

### 消息堆积如何排查？

先看生产速度、消费速度、失败率、单条耗时和下游依赖。处理时先保护主流程和下游，再扩容消费者、拆分队列、转移死信或限流生产者。

### 事务消息能替代本地事务吗？

不能。事务消息解决的是本地事务和消息发送之间的一致性窗口，但业务仍需要幂等、状态机、补偿和对账。

## 生产实践提示

- 跨系统消息要设计 schema 版本，避免生产者升级破坏旧消费者。
- 消费者先做幂等判断，再执行副作用。
- 高优先级和低优先级消息拆队列或 topic。
- 死信不是垃圾桶，要有告警、分析、修复和重放流程。
- 消息体不要放过大 payload，必要时只传业务 ID，再由消费者查询详情。
