# PHP Language Features Interview Examples

本文档对应长期计划中的 PHP 新版本特性主线，用于复盘 PHP 8.1 到 PHP 8.5 的关键语言变化、升级风险和资深面试追问。

截至 2026-05-13，PHP 8.5 已于 2025-11-20 发布。当前项目主运行环境仍固定为 Docker PHP 8.3，因此本专题采用版本门控：PHP 8.1 到 8.3 示例可在当前容器直接运行，PHP 8.4 和 8.5 示例以片段、状态和面试要点展示，避免把 Laravel 10 主应用改成高版本语法硬依赖。

## 学习目标

- 能说清 PHP 8.1 到 8.5 的核心特性，而不是只背特性名。
- 能把新语法和 Laravel 业务代码、DTO、状态机、异常追踪、随机数、协程、静态分析联系起来。
- 能说明版本升级前要检查 PHP-FPM、CLI、Docker 镜像、Composer platform、扩展和 CI。
- 能区分“可直接落地的新特性”和“需要运行时升级后才能写入主源码的新语法”。

## 源码入口

- 示例逻辑：`app/Learning/LaravelInterview/Support/PhpLanguageFeatureExamples.php`
- Artisan 命令：`app/Learning/LaravelInterview/Console/InterviewPhpFeaturesCommand.php`
- 命令注册：`app/Learning/LaravelInterview/Providers/InterviewExampleServiceProvider.php`
- 测试：`tests/Feature/InterviewPhpFeaturesCommandTest.php`

## 运行方式

```bash
docker compose exec laravel.test php artisan interview:php-features
```

输出完整 JSON：

```bash
docker compose exec laravel.test php artisan interview:php-features --json
```

## 覆盖内容

### PHP 8.1

- Enum：把订单状态从散落字符串收敛成类型安全的领域值。
- Readonly property：构建不可变 DTO 和值对象。
- First-class callable：替代字符串函数名或数组 callable，提高重构安全性。
- Fiber：理解协程基础，不把 Fiber 误解成业务层必须直接使用的并发模型。
- Intersection type：表达对象必须同时满足多个接口。

### PHP 8.2

- Readonly class：不可变数据结构更简洁。
- DNF types：表达 `(A&B)|null` 这类复合类型。
- `true`、`false`、`null` 独立类型：让函数契约更窄。
- Randomizer：独立随机状态，测试可复现。
- `#[SensitiveParameter]`：异常 trace 中隐藏密码、token 等敏感参数。

### PHP 8.3

- Typed class constants：约束常量类型，降低继承或接口实现中的类型漂移。
- Dynamic class constant fetch：通过变量读取类常量时不再依赖 `constant()` 拼字符串。
- `#[Override]`：父类方法重构时提前发现拼写错误。
- `json_validate()`：只校验 JSON 合法性时避免不必要的反序列化。
- Randomizer additions、`mb_str_pad()`：补齐随机和多字节字符串处理能力。

### PHP 8.4

- Property hooks：把简单属性读写规则贴近属性定义。
- Asymmetric property visibility：表达 public read + restricted write。
- Lazy objects：框架、ORM、代理对象可延迟初始化昂贵依赖。
- `#[Deprecated]`：自定义 API 废弃信息更标准。

当前 PHP 8.3 容器不会执行 8.4 语法，只展示片段和升级检查点。

### PHP 8.5

- URI extension：按 RFC 3986 和 WHATWG URL 标准解析 URL。
- Pipe operator：多步转换按阅读顺序从左到右表达。
- Clone with：readonly 对象的 with-er 模式更轻量。
- `#[NoDiscard]`：提醒调用方不要忽略重要返回值。
- Closures and first-class callables in constant expressions。
- `array_first()`、`array_last()`。

当前 PHP 8.3 容器不会执行 8.5 语法，只展示片段和升级检查点。

## 面试问答

### Enum 适合替代所有常量吗？

不是。Enum 适合有限状态集合，例如订单状态、支付状态、审核状态。普通配置 key、位运算 flag 或开放字符串集合不一定适合 Enum。

### readonly 能保证深度不可变吗？

不能。`readonly` 约束属性不能重新赋值，但如果属性里放的是可变对象，对象内部状态仍可能变化。值对象要结合不可变对象、复制构造和清晰边界。

### Fiber 是线程吗？

不是。Fiber 是用户态协程基础能力，不等于操作系统线程，也不会让 CPU 密集任务自动并行。Laravel 中更常见的是通过 Swoole、Octane、Amp、ReactPHP 等抽象使用协程。

### `#[SensitiveParameter]` 能替代日志脱敏吗？

不能。它主要影响异常 backtrace 参数展示。业务日志、请求日志、第三方 SDK 日志仍需要独立脱敏策略。

### `#[Override]` 的价值是什么？

它能让 PHP 在编译/加载阶段确认当前方法确实覆盖了父类或接口方法，避免 `tearDown` 写成 `taerDown` 这类问题长期潜伏。

### 为什么 8.4/8.5 语法不能直接写进 PHP 8.3 项目源码？

PHP 会先解析文件。低版本运行时遇到未知语法会直接 fatal，甚至来不及执行版本判断。因此高版本语法必须放到高版本运行时、独立脚本、字符串片段或版本门控 eval 中。

## 资深追问

- PHP-FPM 和 CLI PHP 版本不一致会导致什么问题？
- Composer `config.platform.php` 和真实运行时版本不一致时，依赖解析会有什么风险？
- Docker 镜像升级 PHP 后，需要同步检查哪些扩展？
- 新特性如何影响静态分析、IDE、PHPStan/Psalm 和 Rector？
- `readonly`、DTO、Eloquent Model 三者边界如何划分？
- URI 解析为什么不能长期依赖简单字符串截取？
- Pipe operator 会不会降低调试可读性？团队规范应如何约束？

## 生产实践提示

- 先用 CI 跑全量测试、静态分析和 Rector，再升级生产镜像。
- 同时验证 PHP-FPM、队列 worker、Schedule、Octane、CLI 脚本的 PHP 版本。
- 升级前阅读官方 migration guide 中的 backward incompatible changes 和 deprecated features。
- 高版本语法进入主源码前，先确认最低运行时版本、部署镜像和开发环境都已统一。
- 对 Laravel 项目，先检查框架版本、扩展、Composer 依赖和长期支持窗口，再决定 PHP 升级路径。

## 官方资料

- PHP 8.1 Release Announcement：https://www.php.net/releases/8.1/en.php
- PHP 8.2 Release Announcement：https://www.php.net/releases/8.2/en.php
- PHP 8.3 Release Announcement：https://www.php.net/releases/8.3/en.php
- PHP 8.4 Migration New Features：https://www.php.net/manual/en/migration84.new-features.php
- PHP 8.5 Release Announcement：https://www.php.net/releases/8.5/en.php
- PHP 8.5 Migration New Features：https://www.php.net/manual/en/migration85.new-features.php
