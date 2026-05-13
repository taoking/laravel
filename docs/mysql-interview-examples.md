# MySQL Interview Examples

本文档对应长期计划中的 MySQL 主线，用于复盘索引、执行计划、事务隔离、悲观锁、唯一约束幂等和分页优化。

## 学习目标

- 能用 `EXPLAIN` 观察查询是否命中联合索引。
- 能解释覆盖索引、回表、索引字段顺序和排序的关系。
- 能演示 `lockForUpdate` 在事务中的使用方式。
- 能说明唯一索引如何支撑幂等设计。
- 能区分 offset 分页和 keyset pagination。

## 源码入口

- 示例表迁移：`database/migrations/2026_04_25_000002_create_interview_mysql_demo_orders_table.php`
- 示例逻辑：`app/Learning/LaravelInterview/Support/MySqlInterviewExamples.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewMySqlCommand.php`
- 命令注册：`app/Learning/LaravelInterview/Providers/InterviewExampleServiceProvider.php`
- 测试：`tests/Feature/InterviewMySqlCommandTest.php`

## 运行方式

执行迁移：

```bash
docker compose exec laravel.test php artisan migrate
```

运行 MySQL 示例：

```bash
docker compose exec laravel.test php artisan interview:mysql
```

输出完整 JSON：

```bash
docker compose exec laravel.test php artisan interview:mysql --json
```

## 覆盖内容

- Demo 表：`interview_mysql_demo_orders`。
- 唯一索引：`order_no`。
- 联合覆盖索引：`status, paid_at, order_no`。
- `EXPLAIN`：观察 `key`、`type`、`rows`、`Extra`。
- 事务隔离级别：读取 `@@transaction_isolation`。
- 悲观锁：事务内使用 `lockForUpdate`。
- 幂等：重复插入同一 `order_no` 返回 0 行影响。
- Keyset pagination：使用 `where id > last_seen_id order by id limit n`。

## 面试问答

### 联合索引字段顺序如何设计？

优先考虑等值过滤字段，再考虑范围过滤和排序字段。字段顺序要服务于实际查询，不是把所有字段随便放进索引。索引能否覆盖查询、是否减少排序、写入成本是否可接受，都要一起评估。

### 什么是覆盖索引？

查询所需字段都能从二级索引中拿到，就不需要回表读取主键记录。覆盖索引能减少随机 IO，但索引变大后会增加写入和维护成本。

### 为什么深分页慢？

`offset` 越大，MySQL 需要扫描和丢弃的记录越多。高页码分页可以改成 keyset pagination，例如 `where id > last_seen_id order by id limit n`，让查询从上一次位置继续。

### `lockForUpdate` 适合什么场景？

适合库存扣减、余额变更、订单状态流转等需要串行化修改同一行数据的场景。必须放在事务中使用，事务内不要做外部 HTTP 调用，否则锁持有时间过长。

### 唯一索引如何支撑幂等？

唯一索引能把同一个业务唯一键的重复写入收敛成失败或 0 行影响。支付回调、订单创建、消息消费都可以用唯一键配合状态机避免重复处理。

## 资深追问

- `EXPLAIN type` 中 `ref`、`range`、`index`、`ALL` 分别意味着什么？
- 可重复读如何配合 MVCC 工作？
- 间隙锁和临键锁什么时候出现？
- 死锁如何排查？
- 主从延迟下如何保证读到刚写入的数据？
- 分库分表后唯一约束和事务如何处理？

## 生产实践提示

- 不要只看 SQL 是否能跑，要看执行计划和扫描行数。
- 索引不是越多越好，写入成本、存储成本和优化器选择都要考虑。
- 资损类业务通常需要唯一索引、事务、状态机、幂等表共同保护。
- 事务内只放必要数据库操作，外部调用放到事务外或异步补偿。
- 慢查询排查要结合 SQL、执行计划、数据分布、锁等待和系统资源。
