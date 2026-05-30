# 项目介绍：Laravel 13 资深 PHP 学习项目

更新日期：2026-05-31
适用分支：`13.x`

## 1. 项目定位

本项目不是普通 CRUD 示例，而是一个用于长期学习、复盘和资深 PHP 面试准备的 Laravel 13 实战项目。

项目主题：

```text
多数据源指标分析与内容管理平台
```

核心目标：

- 用 Laravel 13 项目承载 PHP 语言底层、Laravel 框架源码、数据库、缓存、队列、安全、性能和部署知识。
- 用可运行代码、测试、命令和文档证明每个知识点，而不是只停留在概念解释。
- 形成一套可以在资深 PHP/Laravel 面试中讲清楚的项目表达、源码追问和生产问题复盘材料。

## 2. 技术基线

| 项目 | 当前选择 |
| --- | --- |
| PHP | 8.4 |
| 框架 | Laravel 13 |
| 前端 | Inertia + Vue 3 |
| 后台语言 | 默认中文，支持英文切换 |
| 数据库 | MySQL / SQLite 测试环境 |
| 缓存 | Redis / Laravel Cache |
| 队列 | Redis Queue、Laravel Job |
| 事件流 | Kafka 本地驱动 + Docker Kafka 演示 |
| 文档 | Swagger UI + OpenAPI YAML + Markdown 专题文档 |
| 质量 | PHPUnit、PHPStan/Larastan、Psalm、Pint、GitHub Actions |
| 部署 | Docker Compose、Nginx、PHP-FPM、Supervisor |

## 3. 已实现业务模块

| 模块 | 说明 | 面试覆盖 |
| --- | --- | --- |
| 登录与会话 | 登录、退出、当前用户接口 | Auth、Session、CSRF、限流 |
| RBAC 权限 | 用户、角色、权限、菜单、按钮权限 | Guard、Policy、Middleware、权限缓存 |
| 指标库 | 指标、分类、地区、频率、最新值 | Eloquent、Query Builder、Resource、分页 |
| 指标查询 | 筛选、排序、分页、Explain 示例 | 索引、N+1、Query Object、SQL 优化 |
| CSV/XLSX 导入 | 上传、异步处理、失败行、幂等、重试 | Queue、Job、Generator、文件解析、失败补偿 |
| 异步导出 | 创建导出任务、后台生成 CSV、进度、下载鉴权 | 大数据导出、内存控制、私有文件下载 |
| 审计日志 | 审计事件、操作日志、敏感字段脱敏 | Event、Listener、Observer、安全合规 |
| Redis 缓存 | 指标详情、空值缓存、随机 TTL、热点 ZSet、Lua 限流 | 穿透、击穿、雪崩、分布式锁、热 Key |
| Kafka 事件流 | topic、produce、consume、lag、dead letter replay | MQ 选型、offset、consumer group、幂等、死信 |
| 安全攻防 | 签名、反重放、SSRF、XSS、SQL 注入、上传校验、越权 | Web 安全、接口安全、审计 |
| 首页统计 | 真实统计、缓存、主动失效 | 缓存一致性、Observer、统计口径 |
| 语义搜索 | 本地 token vector、同义词、余弦相似度 | AI/向量检索、召回排序、成本边界 |
| Docker 部署 | Nginx、PHP-FPM、MySQL、Redis、Queue、Scheduler、Kafka | 生产部署、502/504 排障、Worker 常驻 |

## 4. 访问路径

后台页面：

| 路径 | 用途 |
| --- | --- |
| `/login` | 登录页 |
| `/admin` | 工作台 |
| `/admin/users` | 用户管理 |
| `/admin/roles` | 角色管理 |
| `/admin/menus` | 菜单管理 |
| `/admin/metrics` | 指标管理 |
| `/admin/imports` | 导入任务 |
| `/admin/audit-logs` | 审计日志 |

核心 API：

| 路径 | 用途 |
| --- | --- |
| `/api/v1/health` | API 健康检查 |
| `/api/v1/me` | 当前用户 |
| `/api/v1/permissions` | 当前用户权限和菜单 |
| `/api/v1/users` | 用户管理 |
| `/api/v1/roles` | 角色管理 |
| `/api/v1/metrics` | 指标列表、筛选、排序、分页 |
| `/api/v1/metrics/semantic-search?q=income%20sales` | 指标语义搜索 |
| `/api/v1/imports` | 导入任务 |
| `/api/v1/exports` | 导出任务 |
| `/api/v1/security/signed-echo` | 签名反重放示例 |
| `/api/v1/security/url-check` | SSRF URL 安全检查 |
| `/api/v1/audit-logs` | 审计日志 |

接口文档：

| 路径 | 用途 |
| --- | --- |
| `/docs/api` | Swagger UI |
| `/docs/openapi.yaml` | OpenAPI YAML |

## 5. 本地启动

基础方式：

```bash
composer install
npm install
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

SQLite 快速验证：

```bash
touch database/database.sqlite
DB_CONNECTION=sqlite DB_DATABASE=$(pwd)/database/database.sqlite php artisan migrate --seed --force
DB_CONNECTION=sqlite DB_DATABASE=$(pwd)/database/database.sqlite php artisan serve --host=127.0.0.1 --port=8000
```

Docker 验收：

```bash
scripts/deploy/docker-smoke.sh
```

## 6. 本地账号

| 角色 | 邮箱 | 密码 |
| --- | --- | --- |
| 超级管理员 | `admin@example.com` | `password` |
| 分析师 | `analyst@example.com` | `password` |

## 7. 质量门禁

每次提交前建议运行：

```bash
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
ruby -e "require 'yaml'; YAML.load_file('public/docs/openapi.yaml')"
git diff --check
```

当前已记录的最终验收基线：

- `php artisan test`：82 个测试、604 个断言。
- `composer analyse`：PHPStan 0 errors，Psalm no errors。
- `npm run build`、Pint、Composer 校验、Docker Compose 配置和 OpenAPI YAML 解析均通过。

## 8. 知识覆盖

当前项目覆盖的资深 PHP 面试范围：

- PHP 语言底层：弱类型、数组、引用、写时复制、对象赋值、Generator、Enum、Attribute、Readonly。
- PHP 运行机制：CLI/FPM、FastCGI、OPcache、JIT、GC、Worker、Scheduler、Octane 对比。
- Composer 工程化：PSR-4、依赖版本、scripts、package discovery、autoload 优化。
- Laravel 核心源码：Application、Container、ServiceProvider、Facade、Middleware Pipeline、Router、Eloquent、Queue Worker。
- MySQL：索引、Explain、慢 SQL、大数据分页、seek pagination、事务和锁追问。
- Redis：缓存一致性、穿透、击穿、雪崩、分布式锁、Lua、ZSet 热榜、大 Key/热 Key 追问。
- Queue/MQ：Redis Queue、Job 幂等、失败重试、补偿、Kafka topic、consumer group、offset、lag、dead letter。
- Web 安全：CSRF、XSS、SQL 注入、SSRF、越权、签名、反重放、上传安全、审计脱敏。
- 架构设计：分层、Service、Query Object、Event-Driven、Observer、Strategy、模块化。
- 性能优化：缓存、SQL 优化、异步化、导入导出内存控制、PHP-FPM、OPcache、压测脚本。
- 测试与 CI：Feature Test、回归测试、PHPStan/Larastan、Psalm、Pint、GitHub Actions。
- 部署运维：Nginx、PHP-FPM、Supervisor、Docker Compose、健康检查、发布回滚、502/504 排障。

## 9. 推荐阅读

| 文档 | 说明 |
| --- | --- |
| `docs/learning-index.md` | 学习导航和所有代码入口 |
| `docs/development-completion-review.md` | 完成度、访问路径和验收命令 |
| `docs/current-project-audit.md` | 当前未完成项和待补充项审计 |
| `docs/implementation-execution-plan.md` | 可交给后续 agent 执行的计划 |
| `docs/interview/architect-interview-coverage-plan.md` | 架构师和面试官视角覆盖度评估 |
| `docs/interview/project-story.md` | 面试项目包装表达 |
| `docs/interview/senior-questions.md` | 资深面试题入口 |

## 10. 当前状态

当前 P0-P3 计划项已经完成。后续不建议直接追加零散功能，应先重新进行架构师覆盖度复审，再新增 P4 任务。

推荐 P4 方向：

- 浏览器端自动化验收。
- 真实压测与容量评估。
- Outbox Pattern 落地。
- Redis Sentinel/Cluster 或大 Key/热 Key 监控演示。
- Octane 常驻容器实验。
