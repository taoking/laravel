# RabbitMQ Interview Examples

本文档对应长期计划中的 MQ 主线，用于复盘 RabbitMQ 的 exchange、queue、binding、routing key、ACK、重试和死信队列。

## 学习目标

- 能解释 RabbitMQ 的核心拓扑：Producer、Exchange、Queue、Binding、Consumer。
- 能通过 Laravel 命令创建拓扑、发布消息、消费消息并 ACK。
- 能说清消息可靠性需要哪些环节共同保证。
- 能把重复消费、幂等、重试、死信和补偿任务放进资深面试回答。

## 源码入口

- 示例逻辑：`app/Learning/LaravelInterview/Support/RabbitMqInterviewExamples.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewRabbitMqCommand.php`
- 命令注册：`app/Learning/LaravelInterview/Providers/InterviewExampleServiceProvider.php`
- 配置：`config/interview_examples.php`
- 测试：`tests/Feature/InterviewRabbitMqCommandTest.php`

## 运行方式

启动 Docker 环境：

```bash
docker compose up -d
```

执行 RabbitMQ 示例：

```bash
docker compose exec laravel.test php artisan interview:rabbitmq
```

输出完整 JSON：

```bash
docker compose exec laravel.test php artisan interview:rabbitmq --key=review --json
```

访问管理台：

```text
http://127.0.0.1:15672
```

默认账号来自 `.env.example`：

```dotenv
RABBITMQ_USER=laravel
RABBITMQ_PASSWORD=secret
```

## 覆盖内容

- Topic Exchange：按 routing key 路由业务事件。
- Queue：保存待消费消息。
- Binding：把 exchange 和 queue 连接起来。
- Publish：通过 Management API 投递一条订单支付事件。
- Consume + ACK：通过 `ack_requeue_false` 获取并确认消息。
- Dead Letter Topology：创建 dead letter exchange 和 dead letter queue。
- Retry Pattern：说明 retry queue + TTL + dead-letter-exchange 的生产做法。

## 面试问答

### RabbitMQ 的 Exchange、Queue、Binding 分别负责什么？

Exchange 负责接收消息并按类型和 routing key 路由；Queue 负责存储消息；Binding 定义 Exchange 到 Queue 的路由规则。Producer 不直接关心 Queue，Consumer 从 Queue 拉取或接收消息。

### ACK 解决什么问题？

ACK 告诉 RabbitMQ 消费者已经成功处理消息，可以从队列删除。没有 ACK 或连接断开时，消息可以重新投递。业务消费者必须设计幂等，因为重投递会导致重复消费。

### 如何保证消息不丢？

生产端要考虑 publisher confirm；Broker 端要考虑 exchange、queue、message 持久化；消费端要手动 ACK；失败后要有重试、死信队列、告警和补偿任务。任何单点机制都不能单独保证端到端可靠。

### 死信队列适合什么场景？

消息被拒绝且不重新入队、消息过期、队列超长等情况可以进入死信队列。死信队列适合承接异常消息，配合人工排查、补偿任务或延迟重试。

### RabbitMQ 和 Kafka 如何选型？

RabbitMQ 路由灵活，适合业务事件、任务削峰、复杂路由、延迟和死信场景。Kafka 吞吐高，适合日志流、埋点、数据管道和顺序追加消费。面试回答要从吞吐、延迟、路由、可靠性、运维成本和团队经验综合判断。

## 资深追问

- 消息重复消费如何防止资损？
- 消息堆积百万级如何处理？
- 如何设计延迟重试并避免无限重试？
- 消费者扩容为什么可能破坏局部顺序？
- RabbitMQ prefetch 对吞吐和公平性有什么影响？
- 发布成功但本地事务回滚怎么办？

## 生产实践提示

- 订单、支付、库存类消费者必须用唯一键、状态机或去重表保护幂等。
- 重试要限制次数，超过阈值进入死信队列。
- 消息体不要过大，大 payload 应存对象存储或数据库，只传引用 ID。
- 队列堆积要先保护下游，再扩消费者或临时降级。
- MQ 不是数据库，关键业务状态仍应有可查询、可修复的持久化记录。
