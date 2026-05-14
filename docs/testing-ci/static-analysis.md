# 静态分析基线

更新日期：2026-05-14  
任务编号：P1-02  
状态：已完成

当前优先级：P1 工程质量基线，后续 Kafka、Redis、队列和 API 开发前必须保持通过。

本文记录 PHPStan、Larastan、Psalm 的接入方式和后续提升路径。目标是在后续继续开发 Kafka、Redis、队列和后台模块时，尽早发现类型错误、Laravel 动态调用风险和不可达代码。

## 工具定位

| 工具 | 作用 |
| --- | --- |
| PHPStan | 通用 PHP 静态分析，检查类型、调用、返回值和明显错误 |
| Larastan | 基于 PHPStan 增强 Laravel 语义，理解 Eloquent、容器、Facade 和 Collection |
| Psalm | 强类型推导、死代码检查、接口约束和更细粒度类型规则 |

## 本地命令

```bash
composer analyse
composer analyse:phpstan
composer analyse:psalm
```

配置文件：

- PHPStan/Larastan：`phpstan.neon`
- Psalm：`psalm.xml`

## 当前基线

- PHPStan/Larastan 当前级别：`level: 1`。
- Psalm 当前级别：`errorLevel="8"`。
- 分析路径：`app`、`routes`、`tests`。
- 目标：先建立可运行基线，再逐步提高规则等级。
- 当前验收结果：`composer analyse:phpstan` 和 `composer analyse:psalm` 均已通过。

## 接入说明

- Laravel Resource 使用 `@mixin` 绑定实际 Eloquent 模型，避免把 Resource 动态字段访问简单屏蔽掉。
- 覆盖 Laravel、PHPUnit 父类方法时使用 PHP 8.3+ `#[\Override]`，让 Psalm 能识别真实 override 关系。
- `routes/console.php` 的闭包命令中 `$this` 由 Laravel 绑定到底层 Command 实例，已通过局部 `@psalm-suppress InvalidScope` 标明框架运行时语义。
- 当前没有引入 Psalm baseline 文件，也没有把真实类型错误整体忽略；后续新增 suppress 必须写明框架原因或第三方库边界。

## 后续开发准入

后续 Codex agent 开发 Kafka、Redis、MQ、OpenAPI 或页面联动任务时，提交前必须至少运行：

```bash
composer analyse
php artisan test
./vendor/bin/pint --test
```

如果新增前端页面或改动资源构建，还必须运行：

```bash
npm run build
```

## 后续提升路径

1. 为 Service、Query Object、Job、Event、Listener 增加明确返回值类型。
2. 为 Eloquent Collection 和 Resource 输出补充 PHPDoc 泛型。
3. 将 PHPStan 提升到 level 2-4。
4. 收紧 Psalm errorLevel，逐步启用 unused code 和 dead code 检查。
5. GitHub Actions 已加入 `composer analyse`，后续可继续提高 PHPStan/Psalm 等级。
6. Kafka 模块开发时，Producer、Consumer、Message DTO、Handler、幂等模型和 Artisan 命令都必须补充明确参数、返回值和数组结构 PHPDoc。

## 面试表达

可以这样说明：

> 我在项目中同时接入了 PHPStan/Larastan 和 Psalm。Larastan 主要解决 Laravel 动态能力带来的类型分析问题，例如 Eloquent、Facade、Collection 和容器解析；Psalm 用来补充更强的类型推导和接口约束。项目先以低风险级别建立可持续通过的基线，再随着模块稳定逐步提高规则等级，避免为了追求工具覆盖一次性引入大量无效改动。
