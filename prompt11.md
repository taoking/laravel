继续执行当前 Laravel 13 + Docker BI 分析工具项目的下一阶段任务：

# Phase 18：Demo 测试数据 / 网站基础调试 / 验收报告

## 一、当前项目背景

当前项目是 Laravel 13 + Docker 构建的 BI 分析工具，已包含或计划包含：

1. Laravel 后端 API。
2. Vue 前端工程。
3. 数据源管理。
4. 数据集建模。
5. 查询引擎。
6. 图表管理。
7. 仪表盘管理。
8. Redis 查询缓存。
9. 查询日志。
10. 数据权限。
11. 指标库 / 语义层。
12. ClickHouse 查询加速。
13. StarRocks / Doris OLAP 数据源接入。
14. 元数据目录 / 血缘 / 影响分析。
15. 数据质量 / 告警规划。

本阶段目标不是继续新增大功能，而是：

```text
生成一套可演示的 BI 测试数据
启动后端和前端
通过 Codex 客户端/浏览器进行基础页面调试
验证核心业务链路
修复明显错误
输出验收报告
```

## 二、本阶段目标

请完成以下任务：

1. 生成 Demo 测试数据。
2. 创建或完善 Seeder / Factory。
3. 提供一键初始化 Demo 数据命令。
4. 启动 Laravel 后端。
5. 启动 Vue 前端。
6. 检查 API 是否正常。
7. 使用浏览器或 Codex 客户端打开前端页面。
8. 按核心业务流程进行基础验收。
9. 修复发现的明显报错。
10. 输出测试账号、测试数据说明、验收结果和遗留问题。

## 三、明确边界

本阶段不要做：

1. 不做大范围功能重构。
2. 不新增复杂业务模块。
3. 不重写查询引擎。
4. 不重写前端工程。
5. 不做大型性能压测。
6. 不做完整 E2E 测试平台。
7. 不强制接真实 StarRocks / Doris 集群。
8. 不强制接真实 ClickHouse，如果本地环境没有则跳过并说明。
9. 不破坏已有 migrations。
10. 不删除已有业务数据，除非明确是本地开发环境。

本阶段只做：

```text
Demo 数据
基础启动
基础联调
基础验收
问题修复
验收文档
```

## 四、执行前先扫描项目

请先检查：

1. README.md
2. plan.md
3. docker-compose.yml
4. .env.example
5. database/migrations
6. database/seeders
7. database/factories
8. routes/api.php
9. app/Models
10. app/Http/Controllers
11. app/Services
12. frontend/package.json
13. frontend/src/router
14. frontend/src/api
15. frontend/src/views
16. 当前认证方式
17. 当前用户、角色、权限表结构
18. 当前数据源、数据集、字段、图表、仪表盘表结构
19. 当前指标库、语义层、查询日志、数据权限相关表结构

要求：

1. 先理解项目已有结构。
2. 不要凭空创建重复表。
3. Seeder 要适配现有 Model 和字段。
4. 如果字段名和下面计划不一致，以当前项目代码为准。
5. 如果某些模块尚未实现，跳过并记录在验收报告中。

## 五、Demo 数据设计

请生成一套适合 BI 系统演示的数据，主题为：

```text
电商销售分析 Demo
```

核心数据集：

```text
sales_orders
```

建议字段：

```text
id
order_no
order_date
province
city
department_id
department_name
sales_user_id
sales_user_name
customer_id
customer_name
customer_type
product_id
product_name
product_category
channel
payment_method
amount
quantity
discount_amount
refund_amount
cost_amount
profit_amount
order_status
created_at
updated_at
```

数据要求：

1. 生成 5000 到 20000 行订单数据。
2. 时间跨度至少覆盖最近 12 个月。
3. 省份包含广东、湖南、湖北、广西、浙江、江苏、四川、北京、上海等。
4. 城市和省份要大致对应。
5. 产品分类包含手机、电脑、家电、服饰、食品、图书、户外、数码配件。
6. 渠道包含官网、小程序、天猫、京东、抖音、线下门店。
7. 客户类型包含新客户、老客户、会员客户、企业客户。
8. 订单状态包含 paid、refunded、cancelled、pending。
9. amount、quantity、profit_amount 要有合理范围。
10. refund_amount 可以部分为 0，部分有退款。
11. 数据中可以故意保留少量质量问题，用于数据质量演示，例如少量 customer_name 为空、少量 amount 为负数、少量 order_no 重复，但比例不要太高。

## 六、系统元数据 Demo

除了物理测试表，还需要生成 BI 系统自己的元数据。

### 1. 用户和角色

创建测试用户：

```text
管理员：
email: admin@example.com
password: password

分析师：
email: analyst@example.com
password: password

普通用户：
email: viewer@example.com
password: password
```

如果系统已有默认用户规范，以现有规范为准。

角色建议：

```text
admin
analyst
viewer
```

权限建议：

```text
admin：全部权限
analyst：可以管理数据源、数据集、图表、仪表盘、指标
viewer：只能查看仪表盘和图表
```

### 2. 数据源

创建一个 Demo MySQL 数据源，指向当前应用数据库或当前 Docker MySQL。

名称：

```text
Demo MySQL Sales
```

类型：

```text
mysql
```

说明：

```text
用于演示 BI 销售分析的数据源
```

### 3. 数据集

基于 sales_orders 创建数据集：

```text
销售订单数据集
```

字段元数据需要同步到 dataset_fields。

字段分类建议：

维度字段：

```text
order_date
province
city
department_name
sales_user_name
customer_type
product_category
channel
payment_method
order_status
```

指标字段：

```text
amount
quantity
discount_amount
refund_amount
cost_amount
profit_amount
```

### 4. 指标库

如果项目已经有语义层 / 指标库，请创建以下指标：

基础指标：

```text
销售额 sales_amount = sum(amount)
订单数 order_count = count(order_no)
销量 total_quantity = sum(quantity)
退款金额 refund_amount_sum = sum(refund_amount)
成本 cost_amount_sum = sum(cost_amount)
利润 profit_amount_sum = sum(profit_amount)
```

复合指标：

```text
客单价 avg_order_amount = sales_amount / order_count
利润率 profit_rate = profit_amount_sum / sales_amount
退款率 refund_rate = refund_amount_sum / sales_amount
```

如果指标库模块还没有实现，跳过并记录。

### 5. 维度库

如果项目已有 dimensions，请创建：

```text
订单日期 order_date
省份 province
城市 city
部门 department_name
销售人员 sales_user_name
客户类型 customer_type
产品分类 product_category
销售渠道 channel
支付方式 payment_method
订单状态 order_status
```

### 6. 图表

创建一组 Demo 图表：

```text
销售额指标卡
订单数指标卡
利润率指标卡
最近 12 个月销售趋势折线图
省份销售额柱状图
产品分类销售额饼图
销售渠道订单数柱状图
客户类型销售额柱状图
城市销售额排行表格
销售明细表格
```

如果项目图表配置格式不同，请适配当前 chart config。

### 7. 仪表盘

创建仪表盘：

```text
电商销售分析看板
```

包含：

1. 销售额指标卡。
2. 订单数指标卡。
3. 利润率指标卡。
4. 最近 12 个月销售趋势。
5. 省份销售额排行。
6. 产品分类销售占比。
7. 销售渠道订单数。
8. 城市销售额排行表格。
9. 销售明细表格。

### 8. 数据权限 Demo

如果系统已有数据权限模块，请创建示例：

```text
viewer 用户只能查看 province = 广东 的数据
analyst 用户可以查看全部数据
admin 用户可以查看全部数据
```

如果列级权限已实现，可以设置：

```text
viewer 用户隐藏 customer_name
```

### 9. 查询加速 Demo

如果 ClickHouse 加速模块已实现，并且本地 ClickHouse 可用：

1. 为销售订单数据集创建 detail_table 加速配置。
2. 构建 ClickHouse 明细表。
3. 创建一个月度省份销售额预聚合。
4. 验证销售趋势图或省份销售图可以命中加速。

如果 ClickHouse 不可用：

1. 跳过加速构建。
2. 在验收报告中说明 ClickHouse 未启用。
3. 不要让 Demo 初始化失败。

### 10. 数据质量 Demo

如果数据质量模块已实现，请创建规则：

```text
订单号不能为空
订单号唯一性检查
销售金额不能小于 0
订单日期新鲜度检查
每日订单量波动检查
```

如果模块未实现，跳过并记录。

## 七、Seeder / Command 要求

优先实现：

```text
database/seeders/DemoBiSeeder.php
```

并在 DatabaseSeeder 中可选调用。

同时建议新增 Artisan 命令：

```text
php artisan bi:demo:seed
```

命令要求：

1. 支持重复执行。
2. 重复执行不要无限创建重复用户、重复数据源、重复图表。
3. 可以用 updateOrCreate。
4. 可以支持 --fresh 参数。
5. 可以支持 --orders=10000 参数。
6. 可以支持 --skip-large-data 参数。
7. 可以输出创建结果摘要。

命令示例：

```bash
php artisan bi:demo:seed --orders=10000
```

输出示例：

```text
Demo users created: 3
Sales orders created: 10000
Data source created: Demo MySQL Sales
Dataset created: 销售订单数据集
Charts created: 10
Dashboard created: 电商销售分析看板
Metrics created: 9
Quality rules created: 5
```

## 八、基础启动验收

请尝试执行以下命令，根据项目实际情况调整：

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan bi:demo:seed --orders=10000
php artisan route:list
php artisan test
```

如果使用 Docker：

```bash
docker compose up -d
docker compose ps
```

如果使用前端：

```bash
cd frontend
npm install
npm run build
npm run dev
```

如果项目已经有更合适的启动方式，以 README 和项目配置为准。

## 九、API 验收清单

请用 curl、php artisan test、前端请求或浏览器验证以下 API。

### 1. 健康检查

验证：

```text
健康检查接口正常
数据库连接正常
Redis 连接正常，如果有
```

### 2. 登录

验证：

```text
admin@example.com / password 可以登录
analyst@example.com / password 可以登录
viewer@example.com / password 可以登录
```

### 3. 数据源

验证：

```text
数据源列表能看到 Demo MySQL Sales
测试连接成功
可以查看表列表
可以查看 sales_orders 字段
```

### 4. 数据集

验证：

```text
数据集列表能看到 销售订单数据集
数据集字段正常
数据预览正常
```

### 5. 指标

如果实现了指标库，验证：

```text
指标列表能看到销售额、订单数、客单价、利润率
指标公式校验正常
```

### 6. 图表

验证：

```text
图表列表能看到 Demo 图表
销售额指标卡能返回数据
销售趋势图能返回最近 12 个月数据
省份销售额柱状图能返回数据
产品分类饼图能返回数据
明细表格能分页返回
```

### 7. 仪表盘

验证：

```text
仪表盘列表能看到 电商销售分析看板
仪表盘详情能加载
仪表盘中的图表能返回数据
全局筛选 province = 广东 能生效，如果已实现
```

### 8. 数据权限

如果已实现，验证：

```text
viewer 用户只能看到广东数据
viewer 用户看不到隐藏字段 customer_name
admin 用户可以看到全部省份数据
```

### 9. 查询日志

验证：

```text
图表查询后 query_logs 有记录
query_logs 记录 dataset_id、chart_id、duration_ms、status
如果有缓存，第二次查询 cache_hit = true
如果有加速，记录 acceleration_mode
```

### 10. 加速

如果 ClickHouse 可用，验证：

```text
detail_table 加速配置 active
预聚合配置 active
符合条件的图表查询命中 aggregate_table 或 detail_table
fallback 逻辑正常
```

### 11. 元数据 / 血缘

如果已实现，验证：

```text
metadata sync 命令可执行
销售额指标能看到依赖 amount 字段
仪表盘能看到包含哪些图表
字段影响分析能返回受影响图表
```

### 12. 数据质量

如果已实现，验证：

```text
质量规则可以手动执行
amount < 0 规则能发现异常
质量检查结果能记录
告警能生成
```

## 十、浏览器 / Codex 客户端验收

请使用可用的浏览器能力或 Codex 客户端打开前端页面。

默认尝试：

```text
http://localhost:5173
```

如果项目使用其他端口，以实际输出为准。

页面验收顺序：

```text
1. 打开登录页
2. 使用 admin@example.com / password 登录
3. 进入首页
4. 打开数据源管理
5. 查看 Demo MySQL Sales
6. 打开数据集管理
7. 查看 销售订单数据集
8. 打开图表管理
9. 预览销售趋势图
10. 预览省份销售额图
11. 打开仪表盘
12. 查看 电商销售分析看板
13. 测试筛选器，如果有
14. 打开查询日志
15. 确认刚才的查询有日志
16. 切换 viewer 用户，验证权限效果，如果已实现
```

调试要求：

1. 如果页面白屏，检查浏览器控制台错误。
2. 如果 API 404，检查 routes/api.php 和前端 api 封装。
3. 如果 401，检查 token 存储和请求头。
4. 如果 403，检查权限配置。
5. 如果 500，检查 Laravel log。
6. 如果图表不显示，检查 ECharts 数据结构。
7. 如果跨域失败，检查 CORS 和 Vite proxy。
8. 如果接口返回结构不一致，在前端 request 层做轻量兼容。
9. 修复明显低风险问题。
10. 不要为了修复页面问题大范围重构后端。

## 十一、自动化测试建议

如项目已有测试体系，请补充基础测试：

```text
DemoBiSeederTest
DashboardSmokeTest
ChartQuerySmokeTest
PermissionSmokeTest
MetricQuerySmokeTest
```

最低限度测试：

1. Demo Seeder 可执行。
2. admin 用户存在。
3. sales_orders 表有数据。
4. 销售订单数据集存在。
5. 至少一个图表查询成功。
6. 仪表盘详情接口成功。
7. query_logs 能记录查询。
8. viewer 数据权限生效，如果权限模块已实现。

## 十二、验收报告

请新增文档：

```text
docs/demo-acceptance-report.md
```

内容包括：

1. 测试环境说明。
2. 启动命令。
3. Demo 用户账号。
4. Demo 数据规模。
5. 生成的数据表。
6. 生成的数据源。
7. 生成的数据集。
8. 生成的指标。
9. 生成的图表。
10. 生成的仪表盘。
11. 执行过的 API 验收。
12. 执行过的页面验收。
13. 通过项。
14. 失败项。
15. 已修复问题。
16. 未修复问题。
17. 后续建议。

## 十三、README 更新

请更新 README 或新增 Demo 使用说明：

```text
如何初始化 Demo 数据
如何启动后端
如何启动前端
测试账号
Demo 看板入口
常见问题
```

示例：

```bash
php artisan migrate
php artisan bi:demo:seed --orders=10000

cd frontend
npm install
npm run dev
```

测试账号：

```text
admin@example.com / password
analyst@example.com / password
viewer@example.com / password
```

## 十四、修复策略

允许修复：

1. Seeder 字段不匹配。
2. Factory 缺失。
3. 前端 API 路径错误。
4. 前端返回结构适配问题。
5. 登录 token 读取问题。
6. 图表数据格式适配问题。
7. CORS / Vite proxy 问题。
8. 明显的 migration 缺失或默认值问题。
9. Demo 数据导致的查询报错。
10. 文档缺失。

不要做：

1. 大范围重构查询引擎。
2. 大范围重写权限系统。
3. 大范围重写前端布局。
4. 删除已有功能。
5. 修改生产逻辑来迁就 Demo，除非是明显 bug。

## 十五、最终验收标准

完成后需要满足：

1. 可以执行 Demo 数据初始化命令。
2. 可以生成测试用户。
3. 可以生成 sales_orders 测试数据。
4. 可以生成 Demo 数据源。
5. 可以生成 Demo 数据集。
6. 可以生成 Demo 图表。
7. 可以生成 Demo 仪表盘。
8. 后端 route:list 正常。
9. 后端基础测试尽量通过。
10. 前端 build 尽量通过。
11. 登录页面可用。
12. 数据源页面可用。
13. 数据集页面可用。
14. 图表页面至少一个图表可预览。
15. 仪表盘页面可加载。
16. 查询日志能记录访问。
17. 权限 Demo 尽量可验证。
18. 生成 docs/demo-acceptance-report.md。
19. README 有 Demo 启动说明。
20. 输出本次发现和修复的问题。

## 十六、运行命令

尽量运行并记录结果：

```bash
composer install
php artisan migrate
php artisan bi:demo:seed --orders=10000
php artisan route:list
php artisan test
```

如果有 Docker：

```bash
docker compose up -d
docker compose ps
```

如果有前端：

```bash
cd frontend
npm install
npm run build
npm run dev
```

如果有浏览器测试能力：

```text
打开前端页面，按验收流程逐页测试。
```

## 十七、最终输出要求

任务完成后，请输出：

1. 本次完成内容。
2. 新增或修改文件列表。
3. 新增 Seeder / Factory / Command。
4. Demo 数据规模。
5. Demo 账号。
6. Demo 数据源。
7. Demo 数据集。
8. Demo 指标。
9. Demo 图表。
10. Demo 仪表盘。
11. 后端启动和测试结果。
12. 前端启动和 build 结果。
13. 浏览器页面验收结果。
14. API 验收结果。
15. 修复的问题。
16. 未修复的问题。
17. 新增文档位置。
18. 下一阶段建议。