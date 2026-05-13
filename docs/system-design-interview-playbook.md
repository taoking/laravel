# System Design Interview Playbook

本文档对应长期计划中的架构设计与工程实践主线，用于复盘 PHP/Laravel 资深面试常见系统设计题：支付回调、扣库存、订单超时、通知中心、后台 RBAC、审计日志。

## 学习目标

- 能把业务需求拆成数据模型、状态机、事务边界、异步事件和补偿流程。
- 能回答幂等、最终一致、限流、降级、重试、对账和审计。
- 能区分触发器、正确性来源和补偿机制。
- 能把 Laravel 中的事务、队列、事件、Policy、日志和中间件组合成完整方案。

## 源码入口

- Playbook 数据：`app/Learning/LaravelInterview/Support/SystemDesignInterviewPlaybook.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewSystemDesignCommand.php`
- 测试：`tests/Feature/InterviewSystemDesignCommandTest.php`

## 运行方式

列出所有设计题：

```bash
docker compose exec laravel.test php artisan interview:system-design
```

查看单个设计题：

```bash
docker compose exec laravel.test php artisan interview:system-design payment-callback
```

输出 JSON：

```bash
docker compose exec laravel.test php artisan interview:system-design admin-rbac --json
```

## 当前覆盖设计题

- `payment-callback`：支付回调验签、幂等、订单状态机、outbox、对账补偿。
- `inventory-deduction`：高并发扣库存、库存流水、防超卖、热点保护。
- `order-timeout`：订单超时关闭、延迟消息、条件更新、资源释放。
- `notification-center`：多渠道通知、模板版本、去重、限流、失败重试。
- `admin-rbac`：后台 RBAC、数据范围、权限缓存、操作审计。
- `audit-log`：审计日志、trace id、脱敏、异步写入、防篡改。

## 面试回答框架

### 1. 先明确需求边界

说清楚核心链路、非核心链路、强一致要求、最终一致要求、吞吐、延迟和失败处理。不要一上来直接画表。

### 2. 再设计状态机和幂等

资损类系统必须先定义状态流转。例如订单只能 `pending -> paid` 或 `pending -> closed`，不能随意倒退。所有外部回调和队列消费都要有业务幂等键。

### 3. 划清事务边界

本地事务只包数据库必要操作，不放外部 HTTP 调用。跨系统副作用通过 outbox、MQ、补偿任务和对账处理。

### 4. 补充异常和补偿

面试官通常会追问“MQ 丢了怎么办”“消费者失败怎么办”“第三方成功本地失败怎么办”。回答里必须有失败表、死信、重试、补偿、对账和人工修复入口。

### 5. 最后谈观测和运维

核心系统要有 trace id、审计日志、指标、告警、压测和容量评估。没有可观测性，系统设计不算完整。

## 高频追问

### 支付回调为什么不能只更新订单状态？

还要验签、防重放、校验金额和商户号、写支付流水、保证状态机、触发下游事件、处理重复回调，并通过对账发现第三方和本地状态不一致。

### 延迟消息能否保证订单一定关闭？

不能。延迟消息只是触发器。正确性来自订单状态机和条件更新；如果延迟消息丢失，还需要定时扫描补偿。

### RBAC 为什么还需要数据范围？

角色权限通常只回答“能否访问某类资源或动作”，但还要回答“能访问哪些数据”。例如区域经理只能看自己区域，租户管理员只能看本租户。

### 审计日志为什么不能同步写太多？

同步写过多会拖慢主流程，而且日志体积可能很大。关键日志可以先写必要字段，再异步扩展详情；同时要脱敏并保证 trace 可关联。

## 生产实践提示

- 状态机和幂等优先于 MQ 选型。
- 数据库唯一约束是资损类幂等的底线之一。
- Outbox 能降低“本地事务成功但消息发送失败”的不一致窗口。
- 对账不是兜底口号，要有数据源、频率、差异处理和人工入口。
- 后台权限必须同时做接口鉴权、数据范围和审计日志。
