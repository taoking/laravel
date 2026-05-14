# GitHub Actions CI

本文对应 `P2-03 GitHub Actions CI`，用于把本地质量门禁固化到远程流水线，避免后续开发破坏 Laravel 13 学习项目的可运行状态。

## 1. Workflow 入口

主 workflow：

- 文件：`.github/workflows/ci.yml`
- 名称：`CI`
- 触发：
  - push 到 `13.x`、`master`、`*.x`
  - pull request
  - 手动 `workflow_dispatch`
  - 其他 workflow 通过 `workflow_call` 复用

保留的兼容 workflow：

- 文件：`.github/workflows/tests.yml`
- 名称：`Tests`
- 作用：保留 Laravel 骨架默认测试入口，但 PHP 版本已收敛到当前项目基线 `8.4`。

## 2. CI 执行步骤

`CI` workflow 当前执行：

| 步骤 | 命令或 Action | 本地复现 |
| --- | --- | --- |
| Checkout | `actions/checkout@v6` | 无 |
| Setup PHP | `shivammathur/setup-php@v2`，PHP `8.4` | `php -v` |
| Setup Node | `actions/setup-node@v6`，Node `24` | `node -v` |
| Composer 校验 | `composer validate --strict` | `composer validate --strict` |
| PHP 依赖安装 | `composer install --prefer-dist --no-interaction --no-progress` | `composer install` |
| Node 依赖安装 | `npm ci` | `npm ci` |
| 应用初始化 | 复制 `.env`、生成 key、创建 SQLite 文件 | `cp .env.example .env && php artisan key:generate` |
| 静态分析 | `composer analyse` | `composer analyse` |
| 代码格式 | `./vendor/bin/pint --test` | `./vendor/bin/pint --test` |
| PHP 测试 | `php artisan test` | `php artisan test` |
| 前端构建 | `npm run build` | `npm run build` |
| Docker 配置校验 | `docker compose config` | `docker compose config` |

## 3. 版本基线

当前项目基线：

- PHP：`8.4`
- Composer：v2
- Node：`24`
- 数据库测试：SQLite
- Kafka：CI 默认使用 `KAFKA_DRIVER=local`，不依赖真实 Kafka 服务
- Queue：测试环境由 `phpunit.xml` 配置为 `sync`
- Cache/Session：测试环境由 `phpunit.xml` 配置为 `array`

说明：

- `composer.json` 要求 PHP `^8.4`，因此 CI 不再运行 PHP 8.3。
- `.github/workflows/tests.yml` 原本的 PHP 8.3/8.4/8.5 matrix 已改为 PHP 8.4，避免与项目运行基线冲突。
- 如后续需要验证 PHP 8.5，必须先确认依赖、扩展和 Laravel 版本兼容，再扩展 matrix。

## 4. 失败处理

| 失败步骤 | 常见原因 | 本地排查命令 |
| --- | --- | --- |
| Composer validate | `composer.json` 格式、版本约束或 lock 不一致 | `composer validate --strict` |
| Composer install | PHP 版本、扩展、lock 文件冲突 | `composer install -vvv` |
| npm ci | `package-lock.json` 与 `package.json` 不一致 | `npm ci` |
| composer analyse | 类型错误、Laravel 动态调用未加注解、Psalm 约束失败 | `composer analyse` |
| Pint | PHP 代码格式不符合项目规则 | `./vendor/bin/pint` |
| php artisan test | 业务回归、迁移、Seeder、环境变量或队列 fake 问题 | `php artisan test --filter=目标测试` |
| npm run build | Vue/Inertia 编译错误、依赖版本、资源导入错误 | `npm run build` |
| docker compose config | compose YAML 语法或环境变量引用错误 | `docker compose config` |

## 5. 后续准入规则

后续 Codex agent 提交前至少运行：

```bash
composer analyse
php artisan test
npm run build
./vendor/bin/pint --test
composer validate --strict
docker compose config
git diff --check
```

新增 API、页面、队列、缓存、安全、Docker 或 Kafka 内容时，还要运行对应专题测试或命令，并在 `docs/development-log.md` 记录验收结果。

## 6. 面试表达

可以这样说明：

> 我把项目本地门禁固化到了 GitHub Actions。CI 会在 push 和 PR 时执行 Composer 校验、PHPStan/Larastan/Psalm、Pint、PHPUnit、前端构建和 Docker Compose 配置校验。这样后续继续补 Kafka、Redis、安全、OpenAPI 或部署模块时，任何类型错误、格式问题、测试回归和构建失败都会在合并前暴露。
