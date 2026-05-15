# 模块级回归测试覆盖

本文对应 P2-02 测试覆盖增强。目标是把阶段验收测试提升为后续重构的保护网，覆盖 API 合同、权限矩阵、验证错误、资源不存在和关键副作用。

## 覆盖入口

| 类型 | 文件 | 覆盖重点 |
| --- | --- | --- |
| 认证合同 | `tests/Feature/PhaseFifteenRegressionCoverageTest.php` | 未登录访问关键 API 返回统一 401 JSON |
| 验证合同 | `tests/Feature/PhaseFifteenRegressionCoverageTest.php` | 用户、指标、导出接口 422 错误结构稳定 |
| 权限矩阵 | `tests/Feature/PhaseFifteenRegressionCoverageTest.php` | 分析师只读指标，不能写用户、角色、指标、导入、导出和审计 |
| 数据副作用 | `tests/Feature/PhaseFifteenRegressionCoverageTest.php` | 无效导出类型不会创建 `export_tasks` |
| 404 合同 | `tests/Feature/PhaseFifteenRegressionCoverageTest.php` | 不存在资源返回统一错误结构 |

## 执行命令

```bash
php artisan test --filter=PhaseFifteenRegressionCoverageTest
php artisan test
composer analyse
```

当前验收结果：

- `php artisan test --filter=PhaseFifteenRegressionCoverageTest` 通过：5 个测试、99 个断言。
- `php artisan test` 通过：69 个测试、498 个断言。
- `composer analyse` 通过，PHPStan/Larastan/Psalm 无新增错误。

## 后续新增测试规则

新增 API 或页面时，至少补充以下一种测试：

- 成功路径：验证核心返回字段和数据库副作用。
- 失败路径：验证 401、403、404、422 或 429 的统一 JSON 合同。
- 权限路径：验证超级管理员、分析师、未登录用户的差异。
- 异步路径：验证任务入队、重试、失败记录或幂等键。
- 缓存路径：验证命中、失效、空值缓存或锁释放。

## 生产风险与维护边界

- 只测管理员 happy path 会掩盖真实权限缺陷；后续新增接口至少要覆盖未登录、低权限角色和管理员三类身份中的关键差异。
- 422、403、404 的 JSON 合同一旦漂移，会影响前端统一错误处理和 OpenAPI 示例；新增 FormRequest 或异常处理时必须跑本专题测试。
- 异步、缓存和幂等测试不能只断言状态码，应同时断言数据库副作用、缓存键变化或任务状态，避免失败路径静默写入错误数据。

## 面试表达

可以这样说明：

> 我没有只写 happy path 测试，而是把 Laravel API 的认证、授权、验证错误、404、幂等和副作用都做成回归测试。这样后续重构 Service、Policy、Request 或中间件时，只要统一响应、权限边界或数据副作用被破坏，测试会马上失败。

## 资深追问

1. Feature Test 和 Unit Test 的边界是什么？
2. 为什么权限测试要覆盖不同角色，而不是只测管理员？
3. 422 验证错误为什么要固定响应结构？
4. 如何测试队列任务失败和重试？
5. 如何避免测试过度依赖数据库初始数据？
6. Mock、Fake 和真实集成测试怎么取舍？
