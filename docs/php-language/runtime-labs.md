# PHP 语言底层运行实验

本文对应 `P1-07 PHP 语言底层代码示例`，目标是把 PHP 语言基础从背诵题变成可运行、可观察、可复盘的实验材料。

## 1. 代码入口

- 实验命令：`app/Console/Commands/PhpLanguageLabCommand.php`
- 测试：`tests/Feature/PhaseElevenPhpLanguageLabTest.php`
- 运行全部实验：

```bash
php artisan php:language-lab all --rows=5000
```

单项实验：

```bash
php artisan php:language-lab weak-types
php artisan php:language-lab cow --rows=5000
php artisan php:language-lab references
php artisan php:language-lab objects
php artisan php:language-lab generator --rows=5000
php artisan php:language-lab modern
```

## 2. 实验覆盖范围

| 实验 | 覆盖知识点 | 面试价值 |
| --- | --- | --- |
| `weak-types` | `==`、弱类型比较、标量参数自动转换 | 能解释 PHP 8 对非数字字符串比较的变化，以及为什么生产代码优先用 `===` |
| `cow` | PHP 数组、list/map、Copy-on-Write、内存变化 | 能回答“赋值什么时候复制”和“大数组为什么要避免无意义写操作” |
| `references` | 普通赋值、引用赋值、别名关系 | 能区分 copy-on-write 与引用，解释引用如何打破 COW |
| `objects` | 对象赋值、对象句柄、`clone` | 能说明“对象赋值不是深拷贝”，也不是普通数组的值复制 |
| `generator` | `yield`、流式处理、数组全量加载对比 | 能解释大文件导入为什么适合 Generator 或 LazyCollection |
| `modern` | Union Type、Enum、Readonly、Attribute、Closure、Arrow Function、`#[\Override]` | 能把 PHP 8.x 新特性绑定到 Laravel 项目设计 |

## 3. PHP 数组、HashTable 与 COW

PHP 数组底层是有序 HashTable，所以它既能作为连续 list 使用，也能作为 key-value map 使用：

```php
$list = [1, 2, 3];
$map = ['metric' => 'revenue_amount', 'status' => 'active'];
```

普通赋值不会立即复制整份数组。PHP 会让两个变量共享同一份 zval，直到其中一个变量发生写操作时才复制，这就是 Copy-on-Write：

```php
$copy = $list;      // 共享
$copy[0] = 999;    // 写入时分离
```

面试表达：

- PHP 数组赋值默认不是立刻复制完整内存。
- 写操作、引用、某些函数参数传递和数组修改可能触发分离。
- 大数组在循环中被无意义复制或修改，会造成内存抖动。
- 读多写少场景 COW 能节省内存，但不能把它当成业务层缓存策略。

运行观察：

```bash
php artisan php:language-lab cow --rows=50000
```

输出里的 `after $copy = $list` 和 `after $copy[0] = 999` 用于观察赋值和写入后的内存差异。不同平台的内存分配器会导致数字不完全一致，面试时重点解释趋势和机制。

## 4. 引用和对象赋值

引用不是“浅拷贝”，而是让两个变量名指向同一个变量容器：

```php
$reference = &$value;
$reference['status'] = 'referenced';
```

对象赋值复制的是对象句柄，两个变量仍然指向同一个对象。只有 `clone` 才创建新对象：

```php
$assigned = $counter;
$cloned = clone $counter;
```

面试表达：

- 数组普通赋值受 COW 保护，写入时分离。
- 引用赋值会创建别名，修改任一变量都会影响同一个变量容器。
- 对象变量保存的是对象标识符，赋值后多个变量指向同一对象。
- `clone` 是对象层面的复制，但对象内部引用属性是否深拷贝取决于 `__clone()` 实现。

## 5. Generator 与大文件导入

`generator` 实验对比两种方式：

- `rowsAsArray()`：把所有行先放进数组。
- `rowsAsGenerator()`：每次 `yield` 一行，消费完当前行后继续下一行。

运行：

```bash
php artisan php:language-lab generator --rows=100000
```

面试表达：

- Generator 不会一次性把全部数据放入内存，适合 CSV/日志/大结果集的流式处理。
- Generator 不是并发工具，也不会自动提升 CPU 计算速度。
- Generator 只能降低“保存全部中间数据”的内存压力，如果每次 yield 后仍把结果追加到大数组，就失去意义。
- 当前项目的 CSV 导入 Job 可用 `yield` 按行读取，避免一次性加载大文件。

## 6. PHP 8.x 新特性与项目绑定

`modern` 实验里包含以下示例：

| 特性 | 命令中的示例 | 项目应用 |
| --- | --- | --- |
| Union Type | `normalizeCode(int|string $code)` | 明确允许的输入类型，替代隐式混用 |
| Enum | `LanguageLabMetricStatus` | 指标状态、任务状态、消息事件类型白名单 |
| Readonly | `LanguageLabCriteria` | 查询条件 DTO、Kafka 消息对象、不可变配置 |
| Attribute | `LanguageLabColumn` | 字段元数据、审计字段、导出列、权限标记 |
| Closure | `$closure = function (...) use (...)` | Middleware、Collection、策略回调 |
| Arrow Function | `fn (...) => ...` | 简短回调，自动按值捕获外部变量 |
| `#[\Override]` | `LanguageLabInterviewPresenter::present()` | 父类或接口方法签名变化时尽早失败 |

项目里已经实际使用的现代 PHP 能力：

- Eloquent 模型上的 `#[Fillable]`、`#[Hidden]`。
- Kafka Message、Criteria 等 readonly 值对象。
- 测试基类、Resource、Handler、ServiceProvider 中的 `#[\Override]`。
- CSV 导入中的 `yield`。
- Middleware 中的 Closure 请求链。

## 7. PHP 7.4 到 8.4 面试差异

| 版本 | 关键变化 | 面试表达 |
| --- | --- | --- |
| PHP 7.4 | Typed Property、Arrow Function、Preloading | 现代 PHP 开始强化类型约束和性能预加载 |
| PHP 8.0 | Union Type、Named Arguments、Attributes、Constructor Property Promotion、Match、Nullsafe Operator、JIT | 语言从弱约束走向更强类型表达，Attribute 让元数据声明进入语言层 |
| PHP 8.1 | Enum、Readonly Property、Fiber、Intersection Type、First-class Callable Syntax | Enum 和 readonly 让领域状态和值对象更安全 |
| PHP 8.2 | Readonly Class、DNF Types、动态属性弃用 | 避免运行期随意挂属性，提升静态分析可靠性 |
| PHP 8.3 | `#[\Override]`、typed class constants、json_validate | Override 能在继承和接口实现变更时提前暴露问题 |
| PHP 8.4 | Property Hooks、非对称可见性、改进 DOM/HTML5 解析、Lazy Objects | 继续强化对象建模能力，框架和 ORM 可减少样板代码 |

## 8. 高频问题

基础问题：

1. PHP 数组为什么既能当数组又能当 Map？
2. `==` 和 `===` 的区别是什么？
3. `empty($arr['missing'])` 为什么不报错？
4. 引用赋值和普通赋值有什么区别？
5. 对象赋值和数组赋值有什么区别？

资深追问：

1. COW 什么时候触发？引用会怎样影响 COW？
2. 大数组作为函数参数一定会复制吗？什么时候会出现额外内存？
3. Generator 为什么省内存？什么情况下不省内存？
4. Enum 比常量类好在哪里？会带来什么迁移成本？
5. Readonly DTO 在 Laravel 项目中解决什么问题，又有什么限制？
6. Attribute 适合放业务规则吗？什么时候只应该放元数据？
7. `#[\Override]` 和 PHPStan/Psalm 的关系是什么？

## 9. 验收命令

```bash
php artisan php:language-lab all --rows=1000
php artisan test --filter=PhaseElevenPhpLanguageLabTest
composer analyse
```

验收标准：

- 命令能输出弱类型、COW、引用、对象、Generator 和现代 PHP 特性的实验结果。
- 测试能证明命令入口有效，非法 action 会失败。
- 文档能回答 COW 触发时机、Generator 内存优势和 PHP 8.x 新特性解决的问题。
