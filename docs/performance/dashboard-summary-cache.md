# 首页统计缓存

## 目标

`/admin` 工作台展示真实业务统计，而不是固定占位值。当前统计项包括用户数、角色数、指标数和导入任务数。

## 代码入口

| 类型 | 路径 |
| --- | --- |
| Web 路由 | `routes/web.php` |
| 缓存服务 | `app/Domains/Dashboard/Services/DashboardSummaryService.php` |
| 缓存失效 Observer | `app/Domains/Dashboard/Observers/RefreshDashboardSummaryObserver.php` |
| 前端页面 | `resources/js/Pages/Dashboard.vue` |
| 多语言文案 | `resources/js/i18n.js` |
| 验收测试 | `tests/Feature/PhaseNineteenDashboardSummaryTest.php` |

## 实现说明

`DashboardSummaryService` 使用 `Cache::remember()` 缓存统计结果，缓存键为 `dashboard:summary`，TTL 为 60 秒。路由闭包只负责渲染 Inertia 页面，统计查询集中在服务类中，便于测试和后续替换为 Redis、预聚合表或异步统计。

缓存失效由 `RefreshDashboardSummaryObserver` 负责。`AppServiceProvider` 为 `User`、`Role`、`Metric` 和 `ImportTask` 注册 Observer，当这些模型保存、删除、恢复或强制删除时，主动清理 `dashboard:summary`。

## 验收标准

- `/admin` 的 `summary.users`、`summary.roles`、`summary.metrics` 和 `summary.import_tasks` 与数据库记录数一致。
- 首次访问后写入 `dashboard:summary` 缓存。
- 新增导入任务后缓存被清理，再次访问可看到最新导入任务数。
- 中文界面显示“导入任务”，英文界面显示“Import tasks”。

## 面试追问

基础问题：

- 为什么首页统计适合缓存？
- `Cache::remember()` 和手动 `get`/`put` 有什么区别？
- 为什么不能把统计逻辑长期放在路由闭包里？

资深追问：

- 只用 TTL 和主动失效分别有什么风险？
- 高并发下首页统计缓存击穿如何处理？
- 如果统计项变成百万级大表，实时 `count(*)` 会有什么问题？
- 多机部署时 Observer 清理本地文件缓存为什么不可靠？
- 统计结果是否需要强一致，还是可以接受最终一致？

## 验收命令

```bash
php artisan test --filter=PhaseNineteenDashboardSummaryTest
```
