# 开发日志

## 2026-05-14 P0 后台真实数据联动

目标：基于 `docs/pending-development-tasks.md` 中 P0 待开发项，完成后台页面真实 API 联动、补齐必要接口、更新文档并完成验收。

### 开发内容

- 新增前端 API 客户端：`resources/js/api.js`。
- 用户管理页接入 `/api/v1/users` 和 `/api/v1/roles`，支持分页、筛选、创建、编辑、删除和角色分配。
- 角色管理页接入 `/api/v1/roles` 和 `/api/v1/permissions/catalog`，支持角色 CRUD 和权限分配。
- 菜单管理页接入权限目录，支持树形展示、排序值、可见状态编辑。
- 指标管理页接入 `/api/v1/metrics`、`/api/v1/metric-categories`、`/api/v1/dimensions/frequencies`，支持筛选、分页、创建、编辑、删除。
- 导入任务页接入 `/api/v1/imports`，支持 CSV 上传、幂等键、任务列表和失败重试。
- 审计日志页接入 `/api/v1/audit-logs`，支持动作和用户筛选。
- 新增权限目录接口：`GET /api/v1/permissions/catalog`。
- 新增菜单更新接口：`PUT /api/v1/menus/{menu}`。
- 新增角色删除接口：`DELETE /api/v1/roles/{role}`。
- 更新 OpenAPI：补充权限目录、菜单更新、角色删除接口。
- 更新多语言文案：补充表单、按钮、状态、字段和提示语。

### 验收记录

已通过命令：

```bash
npm run build
php artisan test --filter=PhaseTwoAccessControlTest
php artisan test
./vendor/bin/pint --test
composer validate --strict
docker compose config
php artisan route:list --path=api/v1/permissions
php artisan route:list --path=api/v1/menus
```

验收结果：

- 前端生产构建通过。
- PhaseTwoAccessControlTest 通过：10 个测试、90 个断言。
- 全量测试通过：34 个测试、213 个断言。
- Pint 格式检查通过。
- Composer 配置校验通过。
- Docker Compose 配置校验通过。
- 新增权限目录、菜单更新、角色删除路由均已注册。

### 后续任务

- P1-02：接入 PHPStan/Larastan/Psalm 静态分析。
- P1-03：强化 MQ 与队列可靠性专题。
- P1-04：按 `docs/queue/kafka-practice.md` 实现 Kafka 消息事件流实践模块。
