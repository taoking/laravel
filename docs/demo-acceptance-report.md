# Demo Acceptance Report

日期：2026-06-25  
项目：Laravel 13 + Docker BI 分析工具  
阶段：Phase 18 Demo 测试数据 / 网站基础调试 / 验收报告

## 测试环境

- 代码目录：`/Users/tao/workspace/code/laravel/laravel`
- 后端：Laravel / PHP 8.4
- 前端：Vue + Vite，生产构建由 Nginx 服务
- Docker 服务：`mysql`、`redis`、`php-fpm`、`nginx`、`queue-worker`、`scheduler`、`clickhouse`
- 访问地址：`http://127.0.0.1:8080`

说明：

- 本地 MinIO 未作为核心链路启动，因为默认宿主机端口 `9000` 与 ClickHouse native port 冲突；需要同时启动时请调整 `MINIO_API_PORT` 或 `BI_CLICKHOUSE_NATIVE_PORT`。
- 本机未安装 Playwright/Chromium，因此未执行完整浏览器自动化；已完成登录页 HTML 烟测、构建产物烟测和 API 链路烟测。

## 启动命令

```bash
docker compose up -d mysql redis php-fpm nginx queue-worker scheduler clickhouse
docker compose exec -T php-fpm php artisan migrate --force
docker compose exec -T php-fpm php artisan bi:demo:seed --fresh --orders=5000
npm run build
```

SQLite 快速验证：

```bash
DB_CONNECTION=sqlite DB_DATABASE=/tmp/laravel_prompt11_verify.sqlite php artisan migrate --force
DB_CONNECTION=sqlite DB_DATABASE=/tmp/laravel_prompt11_verify.sqlite php artisan bi:demo:seed --orders=120 --skip-large-data
```

## Demo 账号

```text
admin@example.com / password
analyst@example.com / password
viewer@example.com / password
```

## Demo 数据

- 明细表：`sales_orders`
- Docker MySQL 验证规模：5000 行订单
- 时间范围：覆盖最近 12 个月以上
- 地域：广东、湖南、湖北、广西、浙江、江苏、四川、北京、上海等
- 产品分类：手机、电脑、家电、服饰、食品、图书、户外、数码配件
- 渠道：官网、小程序、天猫、京东、抖音、线下门店
- 订单状态：`paid`、`refunded`、`cancelled`、`pending`
- 故意保留少量质量问题：空客户名、负金额、重复订单号

## 生成资产

- 数据源：`Demo MySQL Sales`
- 数据集：`销售订单数据集`
- 指标：9 个，包括销售额、订单数、销量、退款金额、成本、利润、客单价、利润率、退款率
- 维度：10 个，包括订单日期、省份、城市、部门、销售人员、客户类型、产品分类、销售渠道、支付方式、订单状态
- 图表：10 个，包括销售额指标卡、订单数指标卡、利润率指标卡、最近 12 个月销售趋势、省份销售额、产品分类销售占比、销售渠道订单数、客户类型销售额、城市销售额排行、销售明细表格
- 仪表盘：`电商销售分析看板`
- 权限：`viewer@example.com` 只能查询 `province = 广东`，并隐藏 `customer_name`

## ClickHouse 加速

Docker MySQL + ClickHouse 环境验证通过：

- 明细加速：`demo_sales_orders_detail`，构建 5000 行
- 预聚合加速：`demo_sales_orders_monthly_province_agg`，构建 3247 行
- `bi:acceleration:benefit-report --days=1` 返回查询统计、detail/aggregate 命中和 fallback 统计

SQLite 或 ClickHouse 不可用时，Seeder 会跳过加速构建并在命令输出中说明原因，不影响 Demo 初始化。

## API 验收

已通过 API 烟测：

- `GET /api/health` 返回 `ok`
- `POST /api/auth/login` 可登录 admin/viewer
- `GET /api/auth/me` 正常返回当前用户
- 数据源列表可看到 `Demo MySQL Sales`
- 数据集列表可看到 `销售订单数据集`
- 图表列表返回 10 个 Demo 图表
- 图表数据接口至少返回 1 行数据
- 仪表盘数据接口正常返回 payload
- viewer 查询自动应用 `province = 广东`
- viewer 查询 `customer_name` 被 422 拦截
- 图表/仪表盘查询后 `query_logs` 有记录
- 数据集加速配置接口返回 2 个加速配置

API 烟测摘要：

```json
{
  "health": "ok",
  "data_sources": 1,
  "datasets": 1,
  "charts": 10,
  "dashboards": 1,
  "chart_rows": 1,
  "viewer_rows": 1,
  "hidden_column_blocked": true,
  "query_logs": 5,
  "acceleration_profiles": 2
}
```

## 页面验收

已验证：

- `http://127.0.0.1:8080/login` 返回 Vue SPA HTML
- 构建产物由 `/build/assets/...` 加载
- `public/hot` 已移除，停止 Vite 后 Nginx 使用生产构建产物

未完整执行：

- Playwright/Chromium 自动化页面点击流程未执行，因为本机缺少对应浏览器运行时。

## 测试与构建

```text
php artisan test tests/Feature/DemoBiSeederTest.php
2 tests, 32 assertions

php artisan test
87 tests, 801 assertions

vendor/bin/pint --test
PASS

npm run build
PASS，保留 Vite 单 chunk 体积提示

php artisan route:list --path=api
192 routes
```

## 已修复问题

- Docker PHP 版本与 Composer 依赖不匹配：升级 Docker PHP 到 8.4，并固定 Redis PECL 安装版本。
- MySQL 迁移 key length / 外键名过长：缩短相关索引字段长度和外键名。
- ClickHouse 建表失败：ORDER BY / partition key 相关字段改为非 Nullable 类型。
- Vite 开发服务配置：补充 host/origin/HMR 配置，便于 Docker 和宿主访问。
- Nginx 502：重启 Nginx 以刷新 php-fpm 容器地址。
- 本地 MySQL 旧 volume 账号不一致：补齐 `laravel` 用户和当前库权限。

## 未修复问题

- 数据质量模块尚未实现，因此未创建质量规则，只保留异常样本数据。
- MinIO 与 ClickHouse 默认宿主机端口存在冲突，未在本次核心链路中同时启动 MinIO。
- Composer audit 仍提示 2 个包存在 3 条安全 advisories，需要后续升级依赖处理。
- Vite 构建存在单 chunk 体积提示，可后续通过动态导入或 manualChunks 优化。
- 未安装 Playwright/Chromium，页面验收停留在 HTML/构建产物烟测和 API 验证。

## 结论

Phase 18 的 Demo 数据初始化、核心 API、权限、查询日志、ClickHouse 加速、测试和前端构建均已通过基础验收。当前 Demo 可用 `admin@example.com / password` 登录后进入 `/dashboards` 查看 `电商销售分析看板`，也可使用 `viewer@example.com / password` 验证广东行级过滤和 `customer_name` 隐藏字段效果。

## 后续建议

- 为前端补充 Playwright smoke test，覆盖登录、图表预览、仪表盘和查询日志。
- 处理 Composer audit advisories。
- 调整 MinIO / ClickHouse 默认端口，保证 `docker compose up -d` 全量服务无冲突。
- 实现数据质量模块后，为 Demo 增加规则和执行结果。
- 对 Vite 单 chunk 做拆包优化。
