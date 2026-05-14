# 资深 PHP 面试题入口

## PHP 语言

- PHP 数组为什么既能做 list 又能做 map？
- 写时复制什么时候发生？
- 引用和对象变量赋值有什么区别？
- `empty($arr['x'])` 为什么不报 notice？
- PHP 8 的 Union Type、Enum、Readonly、Attribute 分别解决什么问题？

## Laravel

- Laravel 请求从 `public/index.php` 到 Controller 的链路是什么？
- ServiceProvider 的 `register` 和 `boot` 区别是什么？
- Facade 是真正的静态方法吗？
- Middleware Pipeline 为什么叫洋葱模型？
- Eloquent 为什么容易产生 N+1？

## MySQL

- 联合索引最左前缀是什么？
- 覆盖索引、回表、索引下推分别是什么？
- MVCC 如何支持快照读？
- 间隙锁和临键锁解决什么问题？
- 千万级分页如何优化？

## Redis / 队列

- 缓存穿透、击穿、雪崩分别怎么处理？
- 分布式锁如何避免误删？
- Redis Queue 和 RabbitMQ/Kafka 的差异是什么？
- Job 为什么要幂等？
- Worker 发布后为什么要 `queue:restart`？

## 安全 / 部署

- CSRF、XSS、SQL 注入在 Laravel 中如何防？
- 接口签名和反重放如何设计？
- 502 和 504 分别怎么排查？
- Supervisor 如何管理 Laravel Worker？
- 发布失败如何回滚？
