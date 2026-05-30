# Laravel 13 学习项目索引

本文档是当前 Laravel 13 长期学习项目的导航页。项目首轮已完成 Phase 1 到 Phase 6，并补充了默认中文的前端多语言能力、首页真实统计、Excel 导入、异步导出、Docker 一键验收和语义搜索加分模块。

## 当前项目基线

- 当前分支：`13.x`。
- 框架：Laravel 13。
- PHP 主运行版本：8.4。
- 产品形态：Inertia 管理后台。
- 项目主题：多数据源指标分析与内容管理平台。
- 当前范围：认证权限、指标 API、导入导出、安全审计、首页统计缓存、语义搜索、性能部署文档、面试复盘和中文多语言界面。

## 推荐阅读顺序

1. `docs/laravel13-senior-php-learning-architecture.md`：长期学习项目总纲。
2. `docs/project-introduction.md`：项目定位、技术栈、业务模块、访问路径、启动方式和知识覆盖。
3. `docs/implementation-execution-plan.md`：可交给 Codex agent 执行的开发计划、访问路径、接口路径和验收标准。
4. `docs/development-completion-review.md`：当前开发完成度、访问路径、验收命令和后续执行规则。
5. `docs/pending-development-tasks.md`：后续待开发任务清单、优先级、交付物和验收标准。
6. `docs/interview/architect-interview-coverage-plan.md`：资深架构师/面试官视角的覆盖度评估、追问地图和后续 agent 执行任务卡。
7. `docs/current-project-audit.md`：当前项目未完成项、待补充项和本轮审计结论。
8. `docs/testing-ci/static-analysis.md`：PHPStan/Larastan/Psalm 静态分析基线和后续准入门禁。
9. `docs/queue/kafka-practice.md`：Kafka 消息事件流实践模块说明，已完成 P1-04 最小事件流闭环。
10. `docs/redis/cache-reliability.md`：Redis 缓存穿透、击穿、雪崩、锁、Lua、大 Key 和热 Key 专题。
11. `docs/performance/dashboard-summary-cache.md`：工作台真实统计、缓存失效和面试追问。
12. `docs/ai/semantic-search.md`：本地 token vector 语义搜索、向量检索演进和 AI 成本边界。
13. `docs/learning-knowledge-map.md`：12 层知识地图和文档目录规划。
14. `docs/laravel-framework-study-guide.md`：当前 Laravel 13 骨架和源码学习说明。
15. `docs/laravel-startup-shutdown-flow.md`：Laravel 启动、关闭、HTTP/CLI 生命周期。
16. `docs/laravel-core/container.md`：服务容器与依赖解析源码追问。
17. `docs/laravel-core/middleware-pipeline.md`：Middleware Pipeline 洋葱模型源码追问。
18. `docs/laravel-core/queue-worker.md`：Queue Worker 执行流程源码追问。
19. `docs/database/large-pagination.md`：大数据分页、offset 与 seek pagination 优化。
20. `docs/runtime/php-fpm-worker-octane.md`：PHP-FPM、Worker、Scheduler、Octane 和常驻进程专题。
21. `docs/php-language/runtime-labs.md`：PHP 弱类型、COW、引用、对象、Generator 和 PHP 8.x 新特性实验。
22. `docs/testing-ci/github-actions.md`：GitHub Actions CI、质量门禁和失败本地复现。

## 已实现访问路径

- 后台首页：`http://127.0.0.1:8000/admin`
- 登录页：`http://127.0.0.1:8000/login`
- API 根路径：`http://127.0.0.1:8000/api/v1`
- 健康检查：`http://127.0.0.1:8000/up`
- API 健康检查：`http://127.0.0.1:8000/api/v1/health`
- 当前用户接口：`http://127.0.0.1:8000/api/v1/me`
- 当前权限菜单：`http://127.0.0.1:8000/api/v1/permissions`
- 用户列表接口：`http://127.0.0.1:8000/api/v1/users`
- 角色列表接口：`http://127.0.0.1:8000/api/v1/roles`
- 指标列表接口：`http://127.0.0.1:8000/api/v1/metrics`
- 指标语义搜索接口：`http://127.0.0.1:8000/api/v1/metrics/semantic-search?q=income%20sales`
- 指标分类接口：`http://127.0.0.1:8000/api/v1/metric-categories`
- 地区维度接口：`http://127.0.0.1:8000/api/v1/dimensions/regions`
- 频率维度接口：`http://127.0.0.1:8000/api/v1/dimensions/frequencies`
- 导入任务接口：`http://127.0.0.1:8000/api/v1/imports`
- 导出任务接口：`http://127.0.0.1:8000/api/v1/exports`
- 导出任务详情：`http://127.0.0.1:8000/api/v1/exports/{export}`
- 导出文件下载：`http://127.0.0.1:8000/api/v1/exports/{export}/download`
- 签名反重放接口：`http://127.0.0.1:8000/api/v1/security/signed-echo`
- SSRF URL 安全检查：`http://127.0.0.1:8000/api/v1/security/url-check`
- 审计日志接口：`http://127.0.0.1:8000/api/v1/audit-logs`
- 接口文档 Swagger UI：`http://127.0.0.1:8000/docs/api`
- OpenAPI YAML：`http://127.0.0.1:8000/docs/openapi.yaml`

## 本地种子账号

- 超级管理员：`admin@example.com` / `password`
- 分析师用户：`analyst@example.com` / `password`

## 当前已实现代码入口

- Inertia 根视图：`resources/views/app.blade.php`
- Swagger UI 页面：`resources/views/docs/api.blade.php`
- Web 路由：`routes/web.php`
- API 路由：`routes/api.php`
- API 统一响应：`app/Support/ApiResponse.php`
- trace_id 中间件：`app/Http/Middleware/EnsureTraceId.php`
- Inertia 共享数据中间件：`app/Http/Middleware/HandleInertiaRequests.php`
- 前端 API 客户端：`resources/js/api.js`
- 后台布局：`resources/js/Layouts/AdminLayout.vue`
- 登录页：`resources/js/Pages/Auth/Login.vue`
- 后台首页：`resources/js/Pages/Dashboard.vue`
- 首页统计缓存服务：`app/Domains/Dashboard/Services/DashboardSummaryService.php`
- 首页统计缓存失效 Observer：`app/Domains/Dashboard/Observers/RefreshDashboardSummaryObserver.php`
- 首页统计缓存说明：`docs/performance/dashboard-summary-cache.md`
- 前端多语言：`resources/js/i18n.js`
- 用户管理页：`resources/js/Pages/Access/Users.vue`
- 角色管理页：`resources/js/Pages/Access/Roles.vue`
- 菜单管理页：`resources/js/Pages/Access/Menus.vue`
- 指标管理页：`resources/js/Pages/Metrics/Index.vue`
- 导入任务页：`resources/js/Pages/Imports/Index.vue`
- 审计日志页：`resources/js/Pages/Audit/Index.vue`
- 指标模型：`app/Domains/Metrics/Models/Metric.php`、`MetricCategory.php`、`MetricValue.php`、`Region.php`、`Frequency.php`
- 指标查询对象：`app/Domains/Metrics/Queries/MetricQuery.php`
- 指标控制器：`app/Http/Controllers/Api/V1/Metrics/MetricController.php`
- 指标语义搜索控制器：`app/Http/Controllers/Api/V1/Metrics/SemanticMetricSearchController.php`
- 指标语义搜索服务：`app/Domains/Metrics/Services/SemanticMetricSearchService.php`
- 指标语义搜索说明：`docs/ai/semantic-search.md`
- 指标 Explain 复盘：`docs/database/metric-query-explain.md`
- 指标大数据分页复盘：`docs/database/large-pagination.md`
- 指标大数据造数命令：`app/Console/Commands/SeedMetricDatasetCommand.php`
- 指标 Explain 命令：`app/Console/Commands/ExplainMetricQueryCommand.php`
- 指标 seek pagination 命令：`app/Console/Commands/MetricSeekPageCommand.php`
- 导入导出 Worker：`docs/queue/import-export-worker.md`
- Kafka 消息事件流计划：`docs/queue/kafka-practice.md`
- Kafka 配置：`config/kafka.php`
- Kafka Docker 服务：`docker-compose.yml`
- Kafka 消息模块：`app/Domains/Messaging`
- Kafka Artisan 命令：`app/Console/Commands/KafkaTopicsCommand.php`、`KafkaProduceCommand.php`、`KafkaConsumeCommand.php`、`KafkaLagCommand.php`、`KafkaDeadLetterReplayCommand.php`
- Kafka 验收测试：`tests/Feature/PhaseSevenKafkaMessagingTest.php`
- 导入任务模型：`app/Domains/Imports/Models/ImportTask.php`
- 上传文件模型：`app/Domains/Files/Models/UploadedFile.php`
- 导出任务模型：`app/Domains/Imports/Models/ExportTask.php`
- CSV/XLSX 导入 Reader：`app/Domains/Imports/Readers/MetricImportReader.php`
- CSV 导入 Job：`app/Jobs/ProcessMetricImportJob.php`
- CSV 导出 Job：`app/Jobs/ProcessMetricExportJob.php`
- 导入任务补偿命令：`app/Console/Commands/ImportCompensateCommand.php`
- 定时统计命令：`app/Console/Commands/ComputeMetricDailySummary.php`
- 安全审计说明：`docs/security/security-audit.md`
- Web 安全攻防实验：`docs/security/web-attack-labs.md`
- 审计日志模型：`app/Domains/Audit/Models/AuditLog.php`
- 操作日志模型：`app/Domains/Operations/Models/OperationLog.php`
- 操作日志中间件：`app/Http/Middleware/RecordOperationLog.php`
- 签名中间件：`app/Http/Middleware/VerifyApiSignature.php`
- SSRF URL 检查控制器：`app/Http/Controllers/Api/V1/Security/UrlSafetyController.php`
- SSRF URL 检查服务：`app/Support/Security/UrlSafetyInspector.php`
- 审计敏感字段脱敏：`app/Support/Security/SensitiveDataMasker.php`
- 热点指标服务：`app/Domains/Metrics/Services/HotMetricService.php`
- 指标缓存可靠性服务：`app/Domains/Metrics/Services/MetricCacheService.php`
- Redis Lua 限流服务：`app/Domains/Metrics/Services/RedisRateLimiterService.php`
- Redis 缓存实验命令：`app/Console/Commands/RedisCacheLabCommand.php`
- 运行机制实验命令：`app/Console/Commands/RuntimeWorkerLabCommand.php`
- PHP 语言底层实验命令：`app/Console/Commands/PhpLanguageLabCommand.php`
- PHP 语言底层专题：`docs/php-language/runtime-labs.md`
- 审计日志接口：`app/Http/Controllers/Api/V1/Audit/AuditLogController.php`
- 性能 Runbook：`docs/performance/performance-runbook.md`
- Docker 部署 Runbook：`docs/deploy/docker-deploy-runbook.md`
- Docker 一键 smoke 脚本：`scripts/deploy/docker-smoke.sh`
- Docker Runbook 验收测试：`tests/Feature/PhaseFourteenDockerRunbookTest.php`
- 模块级回归测试专题：`docs/testing-ci/regression-coverage.md`
- 模块级回归验收测试：`tests/Feature/PhaseFifteenRegressionCoverageTest.php`
- 架构师面试覆盖度计划和任务卡：`docs/interview/architect-interview-coverage-plan.md`
- Laravel Container 专题：`docs/laravel-core/container.md`
- Laravel ServiceProvider 专题：`docs/laravel-core/service-provider.md`
- Laravel Facade 专题：`docs/laravel-core/facade.md`
- Laravel Middleware Pipeline 专题：`docs/laravel-core/middleware-pipeline.md`
- Laravel Router 和模型绑定专题：`docs/laravel-core/router-model-binding.md`
- Laravel Eloquent 查询专题：`docs/laravel-core/eloquent-query.md`
- Laravel Queue Worker 专题：`docs/laravel-core/queue-worker.md`
- 面试项目包装：`docs/interview/project-story.md`
- 资深面试题入口：`docs/interview/senior-questions.md`
- Docker Compose：`docker-compose.yml`
- Nginx 配置：`docker/nginx/default.conf`
- PHP OPcache：`docker/php/opcache.ini`
- Supervisor Worker：`docker/supervisor/worker.conf`
- wrk 压测脚本：`scripts/bench/wrk-metrics.sh`
- OpenAPI 文档：`public/docs/openapi.yaml`
- OpenAPI 契约测试：`tests/Feature/PhaseTwelveOpenApiContractTest.php`
- Phase 1 验收测试：`tests/Feature/PhaseOneScaffoldTest.php`
- Phase 2 验收测试：`tests/Feature/PhaseTwoAccessControlTest.php`
- Phase 3 验收测试：`tests/Feature/PhaseThreeMetricManagementTest.php`
- Phase 4 验收测试：`tests/Feature/PhaseFourImportQueueTest.php`
- Phase 5 验收测试：`tests/Feature/PhaseFiveSecurityAuditTest.php`
- Phase 9 数据库性能测试：`tests/Feature/PhaseNineDatabasePerformanceTest.php`
- Phase 11 PHP 语言底层测试：`tests/Feature/PhaseElevenPhpLanguageLabTest.php`
- Phase 12 OpenAPI 契约测试：`tests/Feature/PhaseTwelveOpenApiContractTest.php`
- Phase 13 安全攻防测试：`tests/Feature/PhaseThirteenSecurityAttackLabTest.php`
- Phase 15 模块级回归测试：`tests/Feature/PhaseFifteenRegressionCoverageTest.php`
- Phase 16 异步导出测试：`tests/Feature/PhaseSixteenAsyncExportTest.php`
- Phase 17 Excel 导入测试：`tests/Feature/PhaseSeventeenExcelImportTest.php`
- Phase 18 语义搜索测试：`tests/Feature/PhaseEighteenSemanticSearchTest.php`
- Phase 19 首页统计缓存测试：`tests/Feature/PhaseNineteenDashboardSummaryTest.php`
- RBAC 模型：`app/Domains/Access/Models/Role.php`、`Permission.php`、`Menu.php`
- 权限服务：`app/Domains/Access/Services/PermissionService.php`
- 权限中间件：`app/Http/Middleware/EnsureUserHasPermission.php`
- 用户 Policy：`app/Policies/UserPolicy.php`
- 菜单管理接口：`app/Http/Controllers/Api/V1/MenuController.php`
- 静态分析配置：`phpstan.neon`、`psalm.xml`
- 静态分析说明：`docs/testing-ci/static-analysis.md`
- GitHub Actions CI：`.github/workflows/ci.yml`、`.github/workflows/tests.yml`
- CI 说明：`docs/testing-ci/github-actions.md`

## 本地启动和验收命令

```bash
composer install
npm install
composer analyse
php artisan kafka:topics --create
npm run build
php artisan test
php artisan serve --host=127.0.0.1 --port=8000
```

如果本地 `.env` 仍指向 MySQL，但只想快速运行演示环境，可以使用 SQLite：

```bash
touch database/database.sqlite
DB_CONNECTION=sqlite DB_DATABASE=$(pwd)/database/database.sqlite php artisan migrate --seed --force
DB_CONNECTION=sqlite DB_DATABASE=$(pwd)/database/database.sqlite php artisan serve --host=127.0.0.1 --port=8000
```

## 学习主线

### 项目主线

- 多数据源指标分析与内容管理平台。
- 登录注册、RBAC、菜单/按钮权限。
- 指标库、指标分类、维度、查询 API。
- CSV/Excel 异步导入、失败记录、报表导出。
- 操作日志、审计日志、文件上传。
- Redis 缓存、限流、分布式锁、热门指标排行。
- Queue、Schedule、Worker、Supervisor。
- 性能压测、慢 SQL、PHP-FPM、OPcache、Nginx。
- Docker 部署和生产排障。
- 语义搜索、向量检索和 Laravel AI SDK 演进作为后期加分模块。

### 知识主线

- PHP 语言底层：类型、数组、引用、写时复制、闭包、Generator、Trait、Attribute、Enum。
- PHP 运行机制：CLI/FPM、FastCGI、OPcache、JIT、GC、Composer autoload。
- Composer 工程化：依赖、版本、脚本、包开发、包发现。
- Laravel 核心：Application、Container、Provider、Facade、Middleware、Routing、Eloquent、Queue。
- 数据库：Eloquent、Query Builder、MySQL 索引、事务、锁、Explain、慢 SQL。
- Redis：数据结构、缓存一致性、锁、限流、持久化、集群。
- 队列与事件流：Job、Retry、Timeout、Failed Job、Horizon、Schedule、Kafka、Topic、Producer、Consumer、offset、consumer group、幂等、顺序性、死信、补偿。
- 安全：CSRF、XSS、SQL 注入、SSRF、越权、接口签名、重放攻击。
- 架构：分层、DTO、Value Object、Strategy、Pipeline、Event-Driven、模块化。
- 性能：FPM、OPcache、SQL、Redis、异步化、压测、容量评估。
- 测试与 CI：PHPUnit 12、Pest 4、Feature Test、PHPStan/Larastan/Psalm、Pint、GitHub Actions。
- 部署：Nginx、PHP-FPM、Supervisor、Cron、Docker Compose、健康检查、回滚。

## 当前文档

- 总纲：`docs/laravel13-senior-php-learning-architecture.md`
- 项目介绍：`docs/project-introduction.md`
- 执行计划：`docs/implementation-execution-plan.md`
- 完成度审计：`docs/development-completion-review.md`
- 开发日志：`docs/development-log.md`
- 当前项目审计：`docs/current-project-audit.md`
- 待开发任务：`docs/pending-development-tasks.md`
- 架构师面试覆盖度计划：`docs/interview/architect-interview-coverage-plan.md`
- 静态分析基线：`docs/testing-ci/static-analysis.md`
- GitHub Actions CI：`docs/testing-ci/github-actions.md`
- Kafka 专题计划：`docs/queue/kafka-practice.md`
- Redis 缓存可靠性专题：`docs/redis/cache-reliability.md`
- 首页统计缓存专题：`docs/performance/dashboard-summary-cache.md`
- 指标语义搜索专题：`docs/ai/semantic-search.md`
- Laravel 源码专题：`docs/laravel-core/container.md`、`service-provider.md`、`facade.md`、`middleware-pipeline.md`、`router-model-binding.md`、`eloquent-query.md`、`queue-worker.md`
- 数据库性能专题：`docs/database/metric-query-explain.md`、`docs/database/large-pagination.md`
- PHP 运行机制专题：`docs/runtime/php-fpm-worker-octane.md`
- PHP 语言底层专题：`docs/php-language/runtime-labs.md`
- 模块级回归测试专题：`docs/testing-ci/regression-coverage.md`
- 知识地图：`docs/learning-knowledge-map.md`
- Laravel 骨架学习：`docs/laravel-framework-study-guide.md`
- Laravel 生命周期：`docs/laravel-startup-shutdown-flow.md`

## 后续落地标准

每个后续主题至少包含：

- 一个明确学习目标。
- 一个与指标分析平台相关的代码实例。
- 一个可运行入口：HTTP 路由、Artisan 命令、Feature Test 或 Unit Test。
- 一段中文说明，包含核心概念、生产风险和面试表达。
- 一组基础问题和资深追问。
- 一个验证方式：测试、命令、日志、Explain、压测或截图。

## 当前阶段约束

- Phase 1 已实现。
- Phase 2 已实现认证、RBAC 数据结构、权限菜单 API、权限中间件、用户 Policy 和越权测试。
- Phase 3 已实现指标库、维度、查询 API、排序白名单、分页和 Explain 示例。
- Phase 4 已实现 CSV/XLSX 导入、失败记录、幂等提交、重试、导出任务异步生成、进度查询、下载鉴权和定时统计命令。
- Phase 5 已实现指标详情缓存、指标查询限流、签名反重放、非法上传校验和审计日志。
- Phase 6 已实现性能 Runbook、Docker Compose、Nginx/PHP-FPM/Supervisor 配置、发布回滚 Runbook 和面试包装文档。
- P0 后台真实数据联动已完成：用户、角色、菜单、指标、导入任务、审计日志页面均已接入真实 API，工作台统计已使用真实数据库计数和缓存失效策略。
- 当前执行计划首轮已覆盖 Phase 1 到 Phase 6，P0 页面联动、P1-02 静态分析基线、P1-04 Kafka 使用专题、P1-03 MQ 队列可靠性专题、P1-01 Redis 缓存专题实验、P1-06 Laravel 源码专题、P1-08 MySQL 大数据性能实证、P1-05 运行机制专题、P1-07 PHP 语言底层代码示例、P2-03 CI、P2-01 OpenAPI 中文化、P2-02 测试覆盖增强、P2-04 安全攻防增强、P3-01 Excel 导入、P3-02 大数据导出异步化、P3-03 首页统计真实化、P3-04 Docker 一键启动验收和 P3-05 语义搜索/AI 加分模块已完成。
- 后续开发提交前必须保持 `composer analyse` 通过；当前暂无 P0-P3 待开发项，继续扩展前应先重新做架构师覆盖度复审并新增 P4 任务。
- 架构师面试补齐计划和后续 agent 任务卡已写入 `docs/interview/architect-interview-coverage-plan.md`，后续任务必须同时满足代码入口、验收命令、中文专题说明和资深追问。
- 每次新增 API 必须同步更新 `public/docs/openapi.yaml`。
- 每次新增页面必须同步更新本文档访问路径。
