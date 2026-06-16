# Laravel Design Patterns Lab - Codex Goal

## 0. 任务背景

当前项目已经是一个本地 Laravel 13 项目，并且已经包含其他演示内容。

本次任务是在当前 Laravel 13 项目中新增一个独立的“设计模式练习模块”，模块名称为：

```text
PatternLab
```

本模块用于系统练习 PHP / Laravel / 后端业务开发中的设计模式。

重要要求：

- 当前项目已经存在，不要重新创建 Laravel 项目。
- 不要破坏现有功能。
- 不要影响现有路由。
- 不要删除、重命名、覆盖已有业务代码。
- 不要引入复杂第三方包。
- 不要实现具体设计模式答案。
- 只搭建练习框架、说明文档、入口、TODO 骨架。
- 具体设计模式代码由用户自己练习实现。

可以参考以下项目的设计模式分类和思想：

```text
https://github.com/DesignPatternsPHP/DesignPatternsPHP
```

但是不要直接照抄它的实现代码。

------

## 1. 总体目标

请在当前 Laravel 13 项目中搭建一个设计模式练习模块。

模块目标：

1. 提供设计模式学习文档。
2. 提供每个设计模式的 Laravel / 后端业务使用场景说明。
3. 提供每个设计模式的常见使用位置说明。
4. 提供 Web 路由入口，用于查看所有模式和单个模式说明。
5. 提供 Artisan 命令入口，用于查看模式列表、单个模式说明和推荐下一个练习。
6. 为每个模式创建独立练习目录。
7. 为每个模式创建 `Exercise.php` 骨架。
8. 为每个模式创建 `README.md` 或 `TODO.md` 练习说明。
9. 为每个模式创建测试骨架。
10. 更新项目 README，但只追加 PatternLab 说明，不要覆盖原 README 的已有内容。

------

## 2. 核心限制

本次任务非常重要的限制：

```text
不要实现具体设计模式代码。
不要替用户完成练习。
不要写完整业务逻辑。
不要写完整测试逻辑。
不要生成模式答案。
```

允许生成：

- 目录结构
- 元信息 Registry
- 文档
- Blade 页面
- Controller
- Artisan Command
- Exercise.php 骨架
- 测试文件骨架
- TODO 注释
- README 说明

不允许生成：

- 完整策略类实现
- 完整工厂实现
- 完整适配器实现
- 完整观察者实现
- 完整业务计算逻辑
- 完整测试断言
- 完整可运行设计模式答案

每个 `Exercise.php` 只能返回“未实现 / TODO”提示。

每个测试文件只能使用 `markTestIncomplete()` 或注释形式提示用户自己实现测试。

------

## 3. 非侵入式改造要求

当前 Laravel 项目已经有其他演示内容。

请遵守：

1. 不要删除现有路由。
2. 不要重写 `routes/web.php`，只能追加 PatternLab 路由。
3. 不要重写 `routes/console.php`，只能追加或创建 PatternLab 相关命令注册逻辑。
4. 不要覆盖已有 Controller。
5. 不要覆盖已有 View。
6. 不要覆盖已有 README 内容，只追加一个新的章节。
7. 所有类名、命名空间、视图目录、路由名前缀都使用 `PatternLab` 或 `pattern-lab`，避免冲突。
8. Web 路由统一使用 `/pattern-lab` 前缀。
9. 路由名称统一使用 `pattern-lab.*`。
10. View 统一放在 `resources/views/pattern-lab/`。
11. 代码统一放在 `app/PatternLab/`。
12. 文档统一放在 `docs/pattern-lab/`。
13. 测试统一放在 `tests/Unit/PatternLab/` 和 `tests/Feature/PatternLab/`。

如果发现某个文件已经存在，请优先合并追加，不要直接覆盖。

------

## 4. 推荐目录结构

请创建或补充以下目录结构：

```text
app/
  PatternLab/
    PatternRegistry.php
    Support/
      PatternDefinition.php
      PatternCategory.php

    Creational/
      SimpleFactory/
        Exercise.php
        README.md
        TODO.md
      FactoryMethod/
        Exercise.php
        README.md
        TODO.md
      AbstractFactory/
        Exercise.php
        README.md
        TODO.md
      Builder/
        Exercise.php
        README.md
        TODO.md
      Prototype/
        Exercise.php
        README.md
        TODO.md
      Singleton/
        Exercise.php
        README.md
        TODO.md

    Structural/
      Adapter/
        Exercise.php
        README.md
        TODO.md
      Bridge/
        Exercise.php
        README.md
        TODO.md
      Composite/
        Exercise.php
        README.md
        TODO.md
      Decorator/
        Exercise.php
        README.md
        TODO.md
      Facade/
        Exercise.php
        README.md
        TODO.md
      Proxy/
        Exercise.php
        README.md
        TODO.md
      DataMapper/
        Exercise.php
        README.md
        TODO.md
      DependencyInjection/
        Exercise.php
        README.md
        TODO.md
      FluentInterface/
        Exercise.php
        README.md
        TODO.md
      Registry/
        Exercise.php
        README.md
        TODO.md

    Behavioral/
      Strategy/
        Exercise.php
        README.md
        TODO.md
      Observer/
        Exercise.php
        README.md
        TODO.md
      Command/
        Exercise.php
        README.md
        TODO.md
      ChainOfResponsibility/
        Exercise.php
        README.md
        TODO.md
      State/
        Exercise.php
        README.md
        TODO.md
      TemplateMethod/
        Exercise.php
        README.md
        TODO.md
      Specification/
        Exercise.php
        README.md
        TODO.md
      Iterator/
        Exercise.php
        README.md
        TODO.md
      Mediator/
        Exercise.php
        README.md
        TODO.md
      Visitor/
        Exercise.php
        README.md
        TODO.md
      NullObject/
        Exercise.php
        README.md
        TODO.md

    LaravelSpecific/
      Repository/
        Exercise.php
        README.md
        TODO.md
      ServiceLayer/
        Exercise.php
        README.md
        TODO.md
      Pipeline/
        Exercise.php
        README.md
        TODO.md
      EventListener/
        Exercise.php
        README.md
        TODO.md
      JobCommand/
        Exercise.php
        README.md
        TODO.md
      FormRequestValidation/
        Exercise.php
        README.md
        TODO.md

app/
  Http/
    Controllers/
      PatternLabController.php

app/
  Console/
    Commands/
      PatternLabListCommand.php
      PatternLabShowCommand.php
      PatternLabNextCommand.php

docs/
  pattern-lab/
    00-learning-roadmap.md
    01-how-to-practice.md
    02-pattern-summary-table.md
    03-pattern-vs-laravel.md
    creational/
      simple-factory.md
      factory-method.md
      abstract-factory.md
      builder.md
      prototype.md
      singleton.md
    structural/
      adapter.md
      bridge.md
      composite.md
      decorator.md
      facade.md
      proxy.md
      data-mapper.md
      dependency-injection.md
      fluent-interface.md
      registry.md
    behavioral/
      strategy.md
      observer.md
      command.md
      chain-of-responsibility.md
      state.md
      template-method.md
      specification.md
      iterator.md
      mediator.md
      visitor.md
      null-object.md
    laravel-specific/
      repository.md
      service-layer.md
      pipeline.md
      event-listener.md
      job-command.md
      form-request-validation.md

resources/
  views/
    pattern-lab/
      index.blade.php
      category.blade.php
      show.blade.php

tests/
  Unit/
    PatternLab/
      Creational/
      Structural/
      Behavioral/
      LaravelSpecific/
  Feature/
    PatternLab/
      PatternLabPageTest.php
      PatternLabCommandTest.php
```

如果项目结构中某些目录不存在，请创建。

如果 Laravel 13 项目的命令注册方式与传统结构不同，请按当前项目实际结构处理，但命令类建议仍放在：

```text
app/Console/Commands/
```

------

## 5. PatternRegistry 要求

创建：

```text
app/PatternLab/PatternRegistry.php
```

它负责统一登记所有模式元信息。

不要在 Registry 中写业务逻辑。

建议提供这些方法：

```php
all(): array
categories(): array
find(string $key): ?array
byCategory(string $category): array
next(): ?array
```

每个模式元信息结构建议如下：

```php
[
    'key' => 'strategy',
    'name' => 'Strategy',
    'name_cn' => '策略模式',
    'category' => 'behavioral',
    'category_cn' => '行为型',
    'difficulty' => 'easy',
    'status' => 'todo',
    'summary' => '封装一组可替换算法，使它们可以在运行时切换。',
    'scenario' => '订单运费计算，根据不同配送方式选择不同计算规则。',
    'common_usage' => [
        '支付方式选择',
        '优惠券计算',
        '报表导出格式选择',
        '数据同步策略',
    ],
    'doc_path' => 'docs/pattern-lab/behavioral/strategy.md',
    'exercise_path' => 'app/PatternLab/Behavioral/Strategy',
    'test_path' => 'tests/Unit/PatternLab/Behavioral/StrategyTest.php',
]
```

注意：

- `status` 默认全部是 `todo`。
- 用户后续练习时可以改为 `doing` 或 `done`。
- `key` 使用短横线格式，例如 `factory-method`。
- 代码目录使用 PascalCase，例如 `FactoryMethod`。
- 分类包括：
  - `creational`
  - `structural`
  - `behavioral`
  - `laravel-specific`

------

## 6. Support 类

可以创建：

```text
app/PatternLab/Support/PatternDefinition.php
app/PatternLab/Support/PatternCategory.php
```

要求：

- 只作为元信息辅助类。
- 不要写复杂逻辑。
- 不要绑定数据库。
- 不要依赖外部服务。

如果觉得数组已经足够，也可以让 `PatternRegistry` 直接返回数组。

------

## 7. Web 路由入口

在 `routes/web.php` 中追加 PatternLab 路由。

不要覆盖原有内容。

建议路由：

```php
Route::prefix('pattern-lab')
    ->name('pattern-lab.')
    ->group(function () {
        Route::get('/', [PatternLabController::class, 'index'])->name('index');
        Route::get('/{category}', [PatternLabController::class, 'category'])->name('category');
        Route::get('/{category}/{pattern}', [PatternLabController::class, 'show'])->name('show');
    });
```

需要注意：

- 如果当前项目已有相同路由，请不要覆盖。
- 如果 `/pattern-lab` 已被占用，请停止并说明冲突。
- 需要引入正确的 Controller 命名空间。
- 页面只展示说明，不执行具体模式实现。

------

## 8. PatternLabController

创建：

```text
app/Http/Controllers/PatternLabController.php
```

方法：

```php
index()
category(string $category)
show(string $category, string $pattern)
```

功能：

### index()

展示所有设计模式列表：

- 英文名
- 中文名
- 分类
- 难度
- 状态
- 一句话说明
- 使用场景
- 常见使用地方
- 详情链接

### category()

按分类展示设计模式：

- 创建型
- 结构型
- 行为型
- Laravel 常用模式

### show()

展示单个模式详情：

- 模式名称
- 中文名
- 分类
- 难度
- 状态
- 一句话定义
- 解决什么问题
- 使用场景
- 常见使用地方
- 不使用模式可能的问题
- 练习目标
- 建议创建的类
- 文档路径
- 代码路径
- 测试路径
- TODO 清单

如果找不到分类或模式，返回 404 或友好的错误页面。

------

## 9. Blade 页面要求

创建：

```text
resources/views/pattern-lab/index.blade.php
resources/views/pattern-lab/category.blade.php
resources/views/pattern-lab/show.blade.php
```

页面要求：

- 简单清晰即可。
- 不需要引入前端框架。
- 不需要复杂 CSS。
- 可以使用普通 HTML 表格。
- 页面只用于查看练习说明。
- 不需要在线编辑。
- 不需要执行练习代码。

### index.blade.php

展示所有模式，建议表格字段：

```text
分类
英文名
中文名
难度
状态
使用场景
常见使用地方
详情
```

### category.blade.php

展示某个分类下的模式。

### show.blade.php

展示单个模式完整说明。

------

## 10. Artisan 命令

创建三个命令：

```bash
php artisan pattern-lab:list
php artisan pattern-lab:show {pattern}
php artisan pattern-lab:next
```

命令类：

```text
app/Console/Commands/PatternLabListCommand.php
app/Console/Commands/PatternLabShowCommand.php
app/Console/Commands/PatternLabNextCommand.php
```

### 10.1 pattern-lab:list

输出所有模式列表。

格式示例：

```text
[behavioral] Strategy 策略模式 - easy - todo
场景：订单运费计算，根据不同配送方式选择不同计算规则
常见：支付方式选择、优惠券计算、报表导出格式选择
```

### 10.2 pattern-lab:show {pattern}

示例：

```bash
php artisan pattern-lab:show strategy
php artisan pattern-lab:show factory-method
php artisan pattern-lab:show adapter
```

输出：

```text
模式：Strategy / 策略模式
分类：Behavioral / 行为型
难度：easy
状态：todo

一句话定义：
封装一组可替换算法，使它们可以在运行时切换。

使用场景：
订单运费计算，根据不同配送方式选择不同计算规则。

常见使用地方：
- 支付方式选择
- 优惠券计算
- 报表导出格式选择
- 数据同步策略

文档：
docs/pattern-lab/behavioral/strategy.md

代码目录：
app/PatternLab/Behavioral/Strategy

测试文件：
tests/Unit/PatternLab/Behavioral/StrategyTest.php

练习要求：
请阅读文档并自己实现，不要依赖自动生成答案。
```

### 10.3 pattern-lab:next

按推荐学习顺序，输出第一个 `status = todo` 的模式。

推荐顺序：

```text
Strategy
SimpleFactory
FactoryMethod
Adapter
Decorator
Observer
Command
Facade
Specification
State
ChainOfResponsibility
TemplateMethod
Builder
Composite
Proxy
AbstractFactory
Bridge
DataMapper
DependencyInjection
Repository
ServiceLayer
Pipeline
Iterator
Mediator
Visitor
NullObject
Prototype
Singleton
FluentInterface
Registry
EventListener
JobCommand
FormRequestValidation
```

------

## 11. 每个模式的 Exercise.php 骨架

每个模式目录中创建 `Exercise.php`。

示例：

```php
<?php

namespace App\PatternLab\Behavioral\Strategy;

class Exercise
{
    public function run(): string
    {
        // TODO: 请在完成策略模式练习后，在这里调用你的实现代码。
        // 注意：Codex 不要替用户实现具体设计模式逻辑。
        return 'Strategy exercise is not implemented yet. Please implement it yourself.';
    }
}
```

不同模式需要调整 namespace 和返回文案。

注意：

- 不要创建完整策略类。
- 不要创建完整算法类。
- 不要写完整业务逻辑。
- 只写 Exercise 入口和 TODO。
- 让用户自己创建接口、具体类、上下文类、测试逻辑。

------

## 12. 每个模式的 README.md 骨架

每个模式目录中创建 `README.md`。

例如：

```text
app/PatternLab/Behavioral/Strategy/README.md
```

内容包含：

~~~md
# Strategy 策略模式练习

## 练习目标

请你根据 `docs/pattern-lab/behavioral/strategy.md` 中的说明，自己完成策略模式代码实现。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ShippingFeeStrategyInterface.php
- NormalShippingStrategy.php
- ExpressShippingStrategy.php
- SameCityShippingStrategy.php
- ShippingFeeContext.php

## 运行入口

```bash
php artisan pattern-lab:show strategy
~~~

## 测试入口

```bash
php artisan test --filter StrategyTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。

```
---

## 13. 每个模式的 TODO.md 骨架

每个模式目录中创建 `TODO.md`。

示例：

​```md
# TODO

- [ ] 阅读模式说明文档
- [ ] 理解使用场景
- [ ] 理解常见使用地方
- [ ] 设计接口或抽象类
- [ ] 实现具体类
- [ ] 编写 Exercise.php 调用入口
- [ ] 编写单元测试
- [ ] 运行测试
- [ ] 总结优点、缺点、适用场景
- [ ] 将 PatternRegistry 中 status 改为 done
```

------

## 14. 测试骨架要求

为每个模式创建测试文件。

例如：

```text
tests/Unit/PatternLab/Behavioral/StrategyTest.php
```

示例内容：

```php
<?php

namespace Tests\Unit\PatternLab\Behavioral;

use Tests\TestCase;

class StrategyTest extends TestCase
{
    public function test_strategy_pattern_exercise(): void
    {
        $this->markTestIncomplete('TODO: 请完成策略模式实现后补充测试逻辑。');
    }
}
```

注意：

- 不要写完整断言。
- 不要替用户完成测试。
- 只提供测试文件和 TODO。
- 测试类命名要清晰。
- namespace 按 Laravel 项目测试结构处理。

------

## 15. Feature 测试骨架

可以创建：

```text
tests/Feature/PatternLab/PatternLabPageTest.php
tests/Feature/PatternLab/PatternLabCommandTest.php
```

这些测试也只写基础骨架或 TODO。

例如：

```php
public function test_pattern_lab_index_page(): void
{
    $this->markTestIncomplete('TODO: 如需测试页面访问，请在这里补充。');
}
```

------

## 16. 文档总目录

需要创建：

```text
docs/pattern-lab/00-learning-roadmap.md
docs/pattern-lab/01-how-to-practice.md
docs/pattern-lab/02-pattern-summary-table.md
docs/pattern-lab/03-pattern-vs-laravel.md
```

------

## 17. 00-learning-roadmap.md 内容要求

内容包括设计模式练习顺序。

建议：

```md
# 设计模式练习路线

## 第一阶段：最常用，优先练习

1. Strategy 策略模式
2. Simple Factory 简单工厂
3. Factory Method 工厂方法
4. Adapter 适配器
5. Decorator 装饰器
6. Observer 观察者
7. Command 命令模式
8. Facade 外观模式

目标：

- 理解如何减少 if/else
- 理解对象创建解耦
- 理解第三方接口适配
- 理解事件解耦
- 理解服务封装

## 第二阶段：复杂业务规则

1. Specification 规格模式
2. State 状态模式
3. Chain of Responsibility 责任链
4. Template Method 模板方法
5. Builder 建造者
6. Composite 组合模式
7. Proxy 代理模式

目标：

- 处理复杂条件判断
- 处理状态流转
- 处理流程编排
- 处理树结构
- 处理访问控制和延迟加载

## 第三阶段：架构理解

1. Abstract Factory 抽象工厂
2. Bridge 桥接
3. Data Mapper 数据映射
4. Dependency Injection 依赖注入
5. Repository 仓储模式
6. Service Layer 服务层
7. Pipeline 管道模式

目标：

- 理解分层架构
- 理解依赖反转
- 理解 Laravel 容器和服务组织
- 理解数据访问和业务逻辑分离

## 第四阶段：补充理解

1. Iterator 迭代器
2. Mediator 中介者
3. Visitor 访问者
4. Null Object 空对象
5. Prototype 原型
6. Singleton 单例
7. Fluent Interface 流式接口
8. Registry 注册表

目标：

- 补齐 GoF 设计模式知识
- 理解模式边界
- 避免过度设计
```

------

## 18. 01-how-to-practice.md 内容要求

内容包括练习方法。

建议写明：

```md
# 如何练习设计模式

## 推荐练习方式

1. 先阅读模式说明文档。
2. 理解该模式解决什么问题。
3. 看使用场景和常见使用地方。
4. 不看答案，自己设计类结构。
5. 自己实现 Exercise.php 中的调用。
6. 自己补充测试。
7. 跑通测试。
8. 总结优缺点。
9. 和相似模式做对比。
10. 最后再让 Codex review，而不是让 Codex 直接写答案。

## 每个模式练习时必须回答的问题

- 这个模式解决什么问题？
- 不使用这个模式会怎样？
- Laravel 项目中哪里可能用到？
- 当前业务是否真的需要这个模式？
- 使用后增加了哪些类？
- 使用后降低了什么复杂度？
- 有没有过度设计风险？

## 不建议的练习方式

- 直接让 AI 生成完整代码。
- 只背 UML。
- 只背定义。
- 不写测试。
- 不结合业务场景。
```

------

## 19. 02-pattern-summary-table.md 内容要求

创建汇总表。

字段：

```md
| 分类 | 模式 | 中文名 | 难度 | 使用场景 | 常见使用地方 | 状态 |
|---|---|---|---|---|---|---|
```

需要把所有模式列进去。

------

## 20. 03-pattern-vs-laravel.md 内容要求

说明设计模式和 Laravel 常用机制之间的关系。

至少包括：

```md
# 设计模式与 Laravel

## Laravel 容器和依赖注入

Laravel 服务容器天然支持依赖注入，很多时候不需要手写复杂工厂或单例。

## Laravel Facade 和 GoF Facade 的区别

Laravel Facade 是静态代理风格的服务容器访问方式，不完全等同于 GoF 外观模式。

## Event / Listener 和 Observer

Laravel 事件监听机制和观察者模式思想接近，都用于解耦事件发生方和响应方。

## Job / Queue 和 Command

Laravel Job 可以看作命令模式在异步任务场景中的应用。

## Pipeline 和责任链

Laravel Pipeline 和责任链模式相似，都适合处理多个连续步骤。

## FormRequest 和 Specification

FormRequest 适合请求参数验证，Specification 更偏复杂业务规则判断。

## Repository 是否一定需要

简单 CRUD 不一定需要 Repository。复杂领域、数据源切换、查询逻辑复用较多时再考虑。
```

------

## 21. 每个模式文档模板

每个模式都需要生成一个 Markdown 文档。

模板如下：

```md
# 模式英文名 中文名

## 1. 一句话定义

用一句话说明该模式。

## 2. 解决什么问题

说明这个模式主要解决什么代码问题或业务问题。

## 3. 使用场景

说明具体业务背景。

必须写清楚：

- 在什么业务下会遇到这个问题
- 为什么普通写法会变复杂
- 使用该模式可以如何拆分职责

## 4. 常见使用地方

说明 Laravel / PHP / 后端项目中常见使用位置。

例如：

- Controller
- Service
- Repository
- Job
- Event / Listener
- Middleware
- FormRequest
- 第三方服务集成
- 支付系统
- 订单系统
- 报表系统
- 消息通知系统
- 文件服务
- 数据同步

## 5. 不使用模式可能的问题

列出不用该模式时的常见问题。

## 6. 适合使用的情况

列出适合使用该模式的条件。

## 7. 不适合使用的情况

说明过度设计风险。

## 8. Laravel 中的关联点

说明该模式和 Laravel 常用机制的关系。

## 9. 本练习场景

给出本项目中该模式的练习场景。

## 10. 练习目标

列出用户需要自己完成的目标。

## 11. 建议创建的类

列出建议类名，但不要实现代码。

## 12. 测试建议

说明应该测试什么，但不要写测试代码。

## 13. 面试表达

用 1-2 段话总结该模式。
```

------

## 22. 所有模式及场景要求

下面是每个模式必须登记到 PatternRegistry，并生成对应文档、练习目录和测试骨架。

------

# 创建型模式 Creational

## 22.1 Simple Factory 简单工厂

key:

```text
simple-factory
```

中文名：

```text
简单工厂
```

难度：

```text
easy
```

一句话定义：

```text
通过一个工厂类根据参数创建不同对象，隐藏对象创建细节。
```

使用场景：

```text
根据导出类型创建不同导出器。
```

业务例子：

- CSV 导出
- Excel 导出
- PDF 导出
- JSON 导出

常见使用地方：

- 报表导出
- 文件生成
- 消息格式化
- 第三方服务客户端创建
- 简单对象创建分支

练习目标：

- 定义统一导出器接口
- 设计工厂类
- 根据类型返回不同导出器
- 避免在业务层直接写多个 new

注意：

不要实现具体导出器，只创建文档和 TODO。

------

## 22.2 Factory Method 工厂方法

key:

```text
factory-method
```

中文名：

```text
工厂方法
```

难度：

```text
medium
```

一句话定义：

```text
把对象创建延迟到子类，由具体工厂决定创建哪种产品。
```

使用场景：

```text
不同通知渠道创建不同消息发送器。
```

业务例子：

- 邮件通知
- 短信通知
- Webhook 通知
- 站内信通知

常见使用地方：

- 通知系统
- 支付渠道
- 文件存储驱动
- 第三方平台接入
- 多渠道业务处理

练习目标：

- 定义通知发送器接口
- 定义工厂抽象
- 由不同具体工厂创建发送器
- 对比简单工厂和工厂方法区别

------

## 22.3 Abstract Factory 抽象工厂

key:

```text
abstract-factory
```

中文名：

```text
抽象工厂
```

难度：

```text
hard
```

一句话定义：

```text
创建一组相关或相互依赖的对象，而不指定具体类。
```

使用场景：

```text
不同支付渠道创建一组支付相关对象。
```

业务例子：

支付宝需要：

- 支付请求构造器
- 回调解析器
- 退款处理器

微信支付需要：

- 支付请求构造器
- 回调解析器
- 退款处理器

常见使用地方：

- 支付系统
- 多租户主题组件
- 多数据库方言适配
- 多云服务 SDK 适配
- 多产品族对象创建

练习目标：

- 定义支付组件族接口
- 定义抽象工厂
- 为不同渠道创建具体工厂
- 理解产品族概念

------

## 22.4 Builder 建造者

key:

```text
builder
```

中文名：

```text
建造者模式
```

难度：

```text
medium
```

一句话定义：

```text
把复杂对象的构建过程拆成多个步骤，使构建过程更清晰。
```

使用场景：

```text
构建复杂报表查询条件。
```

业务例子：

- 时间范围
- 指标字段
- 维度字段
- 过滤条件
- 排序规则
- 分页参数
- 权限条件

常见使用地方：

- 查询条件构建
- 报表配置构建
- API 请求参数构建
- 消息体构建
- 复杂 DTO 构建

练习目标：

- 设计报表查询对象
- 设计 Builder
- 分步骤设置查询条件
- 避免构造函数参数过多

------

## 22.5 Prototype 原型

key:

```text
prototype
```

中文名：

```text
原型模式
```

难度：

```text
medium
```

一句话定义：

```text
通过复制已有对象来创建新对象。
```

使用场景：

```text
复制已有图表配置，并在副本上修改部分字段。
```

业务例子：

- 复制柱状图配置
- 修改标题
- 修改数据源
- 修改筛选条件
- 修改权限配置

常见使用地方：

- 图表模板复制
- 报表模板复制
- 表单模板复制
- 权限配置复制
- 复杂对象克隆

练习目标：

- 理解浅拷贝和深拷贝
- 复制已有配置对象
- 修改副本不影响原对象
- 思考 PHP clone 的边界

------

## 22.6 Singleton 单例

key:

```text
singleton
```

中文名：

```text
单例模式
```

难度：

```text
easy
```

一句话定义：

```text
保证一个类只有一个实例，并提供全局访问点。
```

使用场景：

```text
全局配置读取器。
```

业务例子：

- 配置读取
- 全局上下文
- 应用运行参数

常见使用地方：

- 配置管理
- 日志管理
- 连接管理
- 但在 Laravel 中通常由服务容器管理生命周期

练习目标：

- 理解单例写法
- 理解单例的测试困难
- 理解全局状态问题
- 理解为什么 Laravel 中通常不推荐手写单例

------

# 结构型模式 Structural

## 22.7 Adapter 适配器

key:

```text
adapter
```

中文名：

```text
适配器模式
```

难度：

```text
easy
```

一句话定义：

```text
把不兼容的接口转换成客户端期望的接口。
```

使用场景：

```text
统一不同第三方短信平台接口。
```

业务例子：

- 阿里云短信
- 腾讯云短信
- 华为云短信
- 自建短信网关

常见使用地方：

- 第三方 SDK 接入
- 支付平台接入
- 短信平台接入
- 地图服务接入
- 文件存储服务接入
- 老系统接口兼容

练习目标：

- 定义统一短信发送接口
- 为不同平台创建适配器
- 屏蔽不同 SDK 方法差异
- 让业务层只依赖统一接口

------

## 22.8 Bridge 桥接

key:

```text
bridge
```

中文名：

```text
桥接模式
```

难度：

```text
hard
```

一句话定义：

```text
把抽象部分和实现部分分离，使它们可以独立变化。
```

使用场景：

```text
消息内容类型和发送渠道分离。
```

业务例子：

消息类型：

- 验证码消息
- 营销消息
- 系统告警消息

发送渠道：

- 邮件
- 短信
- Webhook
- 站内信

常见使用地方：

- 消息系统
- 多维度组合业务
- 多平台渲染
- 多渠道发送
- 报表展示方式和导出方式分离

练习目标：

- 避免类型和渠道组合爆炸
- 理解两个变化维度
- 让消息类型和发送渠道独立扩展

------

## 22.9 Composite 组合

key:

```text
composite
```

中文名：

```text
组合模式
```

难度：

```text
medium
```

一句话定义：

```text
把对象组合成树形结构，使单个对象和组合对象可以被一致处理。
```

使用场景：

```text
菜单树 / 权限树。
```

业务例子：

- 一级菜单
- 二级菜单
- 按钮权限
- 数据权限节点

常见使用地方：

- 菜单管理
- 权限管理
- 部门组织树
- 分类树
- 文件目录树
- 评论树

练习目标：

- 定义统一节点接口
- 区分叶子节点和组合节点
- 统一遍历树结构
- 计算权限或菜单层级

------

## 22.10 Decorator 装饰器

key:

```text
decorator
```

中文名：

```text
装饰器模式
```

难度：

```text
medium
```

一句话定义：

```text
在不修改原对象的情况下，动态增加额外功能。
```

使用场景：

```text
给报表查询服务增加缓存、日志、性能统计。
```

业务例子：

- 原始报表查询
- 增加缓存
- 增加日志
- 增加执行耗时统计
- 增加权限过滤

常见使用地方：

- Service 增强
- 缓存包装
- 日志包装
- 性能监控
- 权限校验
- API 客户端增强

练习目标：

- 保持原服务接口不变
- 用装饰器包装原服务
- 理解装饰器和继承的区别
- 理解装饰器和代理的区别

------

## 22.11 Facade 外观

key:

```text
facade
```

中文名：

```text
外观模式
```

难度：

```text
easy
```

一句话定义：

```text
为复杂子系统提供一个简单统一的入口。
```

使用场景：

```text
订单创建流程统一入口。
```

业务例子：

- 创建订单
- 扣减库存
- 创建支付单
- 发送通知
- 写操作日志

常见使用地方：

- 复杂业务流程入口
- 多服务协调
- Controller 调用业务流程
- 支付流程
- 报表生成流程
- 文件上传处理流程

练习目标：

- 设计一个高层入口类
- 隐藏内部多个服务调用
- 保持 Controller 简洁
- 理解 Laravel Facade 和 GoF Facade 区别

------

## 22.12 Proxy 代理

key:

```text
proxy
```

中文名：

```text
代理模式
```

难度：

```text
medium
```

一句话定义：

```text
通过代理对象控制对真实对象的访问。
```

使用场景：

```text
远程文件服务访问代理。
```

业务例子：

- 权限检查
- 缓存文件元信息
- 延迟加载远程文件
- 控制下载访问

常见使用地方：

- 远程服务访问
- 文件服务
- API 客户端
- 权限控制
- 缓存代理
- 延迟加载

练习目标：

- 定义真实服务接口
- 创建代理类
- 在代理中增加权限或缓存逻辑
- 理解代理和装饰器区别

------

## 22.13 Data Mapper 数据映射

key:

```text
data-mapper
```

中文名：

```text
数据映射模式
```

难度：

```text
hard
```

一句话定义：

```text
把领域对象和数据库持久化逻辑分离。
```

使用场景：

```text
将数据库记录和领域对象分离。
```

业务例子：

- 用户领域对象
- 订单领域对象
- 数据库查询和保存由 Mapper 完成

常见使用地方：

- 领域驱动设计
- 复杂业务模型
- 多数据源映射
- 避免领域对象依赖 ORM
- 替代 Active Record 的复杂场景

练习目标：

- 理解 Active Record 和 Data Mapper 区别
- 设计领域对象
- 设计 Mapper
- 避免领域对象直接操作数据库

------

## 22.14 Dependency Injection 依赖注入

key:

```text
dependency-injection
```

中文名：

```text
依赖注入
```

难度：

```text
easy
```

一句话定义：

```text
把对象依赖从外部传入，而不是在类内部创建。
```

使用场景：

```text
订单服务依赖支付服务、库存服务、通知服务。
```

业务例子：

- OrderService 依赖 PaymentService
- OrderService 依赖 StockService
- OrderService 依赖 NotificationService

常见使用地方：

- Laravel Service
- Controller 构造函数
- Job 构造函数
- Event Listener
- Repository
- 第三方客户端

练习目标：

- 使用构造函数注入
- 避免在类内部 new 依赖
- 理解 Laravel 服务容器
- 理解接口依赖和实现绑定

------

## 22.15 Fluent Interface 流式接口

key:

```text
fluent-interface
```

中文名：

```text
流式接口
```

难度：

```text
medium
```

一句话定义：

```text
通过链式调用让代码表达更接近自然语言。
```

使用场景：

```text
报表查询条件链式构建。
```

业务例子：

- 选择指标
- 按维度分组
- 按日期过滤
- 排序
- 分页

常见使用地方：

- Laravel Query Builder
- Eloquent 查询
- 报表查询构建
- API 参数构建
- 表单配置构建

练习目标：

- 每个方法返回 `$this`
- 链式构建查询配置
- 理解可读性和调试成本
- 避免链式调用过长

------

## 22.16 Registry 注册表

key:

```text
registry
```

中文名：

```text
注册表模式
```

难度：

```text
medium
```

一句话定义：

```text
通过一个集中位置保存和获取对象或配置。
```

使用场景：

```text
统一登记可用报表组件或导出器。
```

业务例子：

- 图表组件注册
- 导出器注册
- 数据处理器注册
- 设计模式元信息注册

常见使用地方：

- 插件系统
- 组件系统
- 策略列表管理
- 服务查找
- 配置集中管理

练习目标：

- 创建注册表
- 支持注册、查找、列出
- 理解注册表和服务容器区别
- 避免全局状态滥用

------

# 行为型模式 Behavioral

## 22.17 Strategy 策略

key:

```text
strategy
```

中文名：

```text
策略模式
```

难度：

```text
easy
```

一句话定义：

```text
封装一组可替换算法，使它们可以在运行时切换。
```

使用场景：

```text
订单运费计算，根据不同配送方式选择不同计算规则。
```

业务例子：

- 普通快递
- 顺丰快递
- 同城配送
- 会员免邮
- 满减包邮

常见使用地方：

- 支付方式选择
- 优惠券计算
- 运费计算
- 报表导出格式
- 数据同步策略
- 第三方接口调用策略

练习目标：

- 定义策略接口
- 实现多个具体策略
- 创建 Context 类
- 通过组合替代大量 if/else

------

## 22.18 Observer 观察者

key:

```text
observer
```

中文名：

```text
观察者模式
```

难度：

```text
easy
```

一句话定义：

```text
对象状态变化时，自动通知多个依赖对象。
```

使用场景：

```text
订单支付成功后触发多个后续动作。
```

业务例子：

- 发送通知
- 增加积分
- 写日志
- 推送统计数据
- 发放优惠券

常见使用地方：

- Laravel Event / Listener
- 模型事件
- 订单状态变化
- 用户注册成功
- 支付成功回调
- 消息通知

练习目标：

- 理解事件发布和订阅
- 解耦事件发生者和响应者
- 对比 Laravel Event / Listener
- 避免主流程塞满后续动作

------

## 22.19 Command 命令模式

key:

```text
command
```

中文名：

```text
命令模式
```

难度：

```text
medium
```

一句话定义：

```text
把请求封装成对象，使请求可以排队、记录、撤销或重试。
```

使用场景：

```text
后台任务封装。
```

业务例子：

- 导出报表
- 发送通知
- 生成账单
- 清理缓存
- 同步数据

常见使用地方：

- Laravel Job
- Artisan Command
- 队列任务
- 后台操作
- 批处理任务
- 可重试任务

练习目标：

- 把操作封装为命令对象
- 设计命令执行入口
- 理解命令和任务队列的关系
- 对比 Laravel Job

------

## 22.20 Chain of Responsibility 责任链

key:

```text
chain-of-responsibility
```

中文名：

```text
责任链模式
```

难度：

```text
medium
```

一句话定义：

```text
让多个处理器按链路依次处理请求，直到请求被处理或链路结束。
```

使用场景：

```text
请求风控校验。
```

业务例子：

- 登录校验
- 权限校验
- 参数校验
- 黑名单校验
- 频率限制校验

常见使用地方：

- Laravel Middleware
- Pipeline
- 请求过滤
- 数据导入校验
- 风控规则
- 审批流程

练习目标：

- 定义处理器接口
- 让请求沿链路传递
- 每个处理器只关注一个职责
- 对比 Laravel Middleware 和 Pipeline

------

## 22.21 State 状态

key:

```text
state
```

中文名：

```text
状态模式
```

难度：

```text
medium
```

一句话定义：

```text
对象在不同状态下表现出不同行为，并把状态行为封装到状态类中。
```

使用场景：

```text
订单状态流转。
```

业务例子：

- 待支付
- 已支付
- 已发货
- 已完成
- 已取消

常见使用地方：

- 订单系统
- 审批系统
- 工单系统
- 支付单状态
- 发票状态
- 任务状态机

练习目标：

- 定义状态接口
- 为每个状态创建类
- 把状态行为从订单类中拆出
- 减少状态 if/else

------

## 22.22 Template Method 模板方法

key:

```text
template-method
```

中文名：

```text
模板方法
```

难度：

```text
medium
```

一句话定义：

```text
父类定义算法流程骨架，子类实现具体步骤。
```

使用场景：

```text
不同数据导入流程。
```

业务例子：

共同流程：

- 读取文件
- 校验数据
- 转换数据
- 保存数据
- 生成导入报告

不同导入：

- 用户导入
- 订单导入
- 商品导入
- 报表数据导入

常见使用地方：

- 数据导入
- 文件处理
- 报表生成
- 支付回调处理
- 定时任务流程
- 数据清洗流程

练习目标：

- 抽象共同流程
- 子类实现差异步骤
- 理解继承带来的约束
- 对比策略模式

------

## 22.23 Specification 规格模式

key:

```text
specification
```

中文名：

```text
规格模式
```

难度：

```text
hard
```

一句话定义：

```text
把复杂业务判断封装为可组合的规则对象。
```

使用场景：

```text
优惠券是否可用判断。
```

业务例子：

判断条件：

- 是否过期
- 是否达到最低金额
- 是否限制商品分类
- 是否限制用户等级
- 是否新用户专享

常见使用地方：

- 优惠券系统
- 权限判断
- 风控规则
- 商品筛选
- 用户资格判断
- 复杂业务规则组合

练习目标：

- 定义规格接口
- 实现多个具体规格
- 支持 and / or / not 组合
- 避免复杂 if/else 堆积

------

## 22.24 Iterator 迭代器

key:

```text
iterator
```

中文名：

```text
迭代器模式
```

难度：

```text
medium
```

一句话定义：

```text
提供一种统一方式顺序访问集合元素，而不暴露集合内部结构。
```

使用场景：

```text
分页读取大量订单或日志。
```

业务例子：

- 分页遍历订单
- 批量处理日志
- 游标读取数据
- 分批同步数据

常见使用地方：

- 大数据量分页处理
- LazyCollection
- 文件逐行读取
- 游标查询
- 批量任务

练习目标：

- 封装集合遍历逻辑
- 隐藏分页细节
- 理解 PHP Iterator
- 对比 Laravel LazyCollection

------

## 22.25 Mediator 中介者

key:

```text
mediator
```

中文名：

```text
中介者模式
```

难度：

```text
hard
```

一句话定义：

```text
通过中介对象协调多个对象交互，减少对象之间直接依赖。
```

使用场景：

```text
表单多个字段联动。
```

业务例子：

- 选择省份后加载城市
- 选择城市后加载门店
- 选择门店后加载库存
- 不同筛选条件互相影响

常见使用地方：

- 表单联动
- 工作流协调
- 多组件通信
- 复杂 UI 后端配置
- 业务对象协作

练习目标：

- 减少对象互相调用
- 创建中介者协调交互
- 理解中介者和观察者区别
- 避免中介者变成上帝类

------

## 22.26 Visitor 访问者

key:

```text
visitor
```

中文名：

```text
访问者模式
```

难度：

```text
hard
```

一句话定义：

```text
在不修改对象结构的前提下，为对象结构增加新的操作。
```

使用场景：

```text
对不同报表节点执行不同操作。
```

业务例子：

节点类型：

- 文本节点
- 图表节点
- 表格节点
- 筛选器节点

操作类型：

- 导出
- 权限检查
- 字段统计
- 渲染预览

常见使用地方：

- AST 处理
- 报表节点处理
- 表达式解析
- 权限扫描
- 复杂对象结构遍历

练习目标：

- 定义访问者接口
- 不修改节点类的情况下增加操作
- 理解访问者适合稳定对象结构
- 理解其复杂度和使用边界

------

## 22.27 Null Object 空对象

key:

```text
null-object
```

中文名：

```text
空对象模式
```

难度：

```text
easy
```

一句话定义：

```text
用一个空对象替代 null，减少空值判断。
```

使用场景：

```text
用户没有默认地址时返回空地址对象。
```

业务例子：

- 用户默认地址
- 默认配置
- 匿名用户
- 空优惠券
- 空权限对象

常见使用地方：

- 用户资料
- 配置读取
- 权限对象
- 购物车
- 默认值处理
- 避免 null 判断

练习目标：

- 定义真实对象和空对象统一接口
- 用空对象替代 null
- 减少调用方 if 判断
- 理解不要滥用空对象隐藏错误

------

# Laravel 常用模式扩展 LaravelSpecific

## 22.28 Repository

key:

```text
repository
```

中文名：

```text
仓储模式
```

难度：

```text
medium
```

一句话定义：

```text
封装数据访问逻辑，为业务层提供集合式的数据访问接口。
```

使用场景：

```text
用户数据读取、订单数据读取、复杂查询复用。
```

常见使用地方：

- Service 层调用数据访问
- 复杂查询封装
- 多数据源切换
- 测试时替换数据访问实现
- 领域模型数据获取

练习目标：

- 理解 Repository 的价值和争议
- 简单 CRUD 不强行使用 Repository
- 复杂查询或多数据源时再使用
- 对比 Eloquent 直接查询

------

## 22.29 Service Layer

key:

```text
service-layer
```

中文名：

```text
服务层模式
```

难度：

```text
easy
```

一句话定义：

```text
把业务流程从 Controller 中抽离到 Service 层。
```

使用场景：

```text
订单创建、支付确认、报表生成。
```

常见使用地方：

- Controller 调用业务流程
- 订单业务
- 支付业务
- 用户业务
- 报表业务
- 数据同步业务

练习目标：

- 保持 Controller 简洁
- 把业务编排放到 Service
- 理解 Service 不等于工具类
- 理解 Service 和 Repository 分工

------

## 22.30 Pipeline

key:

```text
pipeline
```

中文名：

```text
管道模式
```

难度：

```text
medium
```

一句话定义：

```text
让数据依次经过多个处理步骤，每个步骤处理后交给下一个步骤。
```

使用场景：

```text
数据处理流水线、请求过滤、导入校验。
```

常见使用地方：

- Laravel Pipeline
- Middleware
- 数据导入校验
- 内容过滤
- 请求预处理
- 报表数据加工

练习目标：

- 理解多个步骤串联
- 每个步骤只做一件事
- 对比责任链模式
- 理解 Laravel Pipeline 使用场景

------

## 22.31 Event / Listener

key:

```text
event-listener
```

中文名：

```text
事件监听模式
```

难度：

```text
easy
```

一句话定义：

```text
通过事件和监听器解耦主流程和后续动作。
```

使用场景：

```text
订单支付成功后通知、积分、日志。
```

常见使用地方：

- Laravel Event
- Laravel Listener
- 用户注册
- 支付成功
- 订单完成
- 审批通过
- 文件上传完成

练习目标：

- 理解事件发布
- 理解监听器响应
- 对比观察者模式
- 避免主业务流程过重

------

## 22.32 Job / Command

key:

```text
job-command
```

中文名：

```text
任务命令模式
```

难度：

```text
easy
```

一句话定义：

```text
把耗时任务封装为 Job，交给队列异步执行。
```

使用场景：

```text
异步导出报表、发送邮件、生成文件。
```

常见使用地方：

- Laravel Queue
- 报表导出
- 邮件发送
- 文件生成
- 图片处理
- 数据同步

练习目标：

- 理解 Job 和 Command 模式关系
- 理解同步任务和异步任务
- 理解重试和失败处理
- 不在请求中执行耗时操作

------

## 22.33 Form Request Validation

key:

```text
form-request-validation
```

中文名：

```text
表单请求验证
```

难度：

```text
easy
```

一句话定义：

```text
把请求参数验证逻辑从 Controller 中拆分出去。
```

使用场景：

```text
创建订单、提交表单、接口参数验证。
```

常见使用地方：

- Controller 入参验证
- API 请求验证
- 表单提交
- 后台管理
- 创建和更新操作

练习目标：

- 理解验证逻辑和业务逻辑分离
- 保持 Controller 简洁
- 对比 Specification 模式
- 理解 FormRequest 适合输入验证，不适合复杂业务资格判断

------

## 23. 每个模式文档必须强调的内容

每个模式的 Markdown 文档都必须有以下标题：

```md
# 模式名 中文名

## 1. 一句话定义

## 2. 解决什么问题

## 3. 使用场景

## 4. 常见使用地方

## 5. 不使用模式可能的问题

## 6. 适合使用的情况

## 7. 不适合使用的情况

## 8. Laravel 中的关联点

## 9. 本练习场景

## 10. 练习目标

## 11. 建议创建的类

## 12. 测试建议

## 13. 面试表达
```

特别注意：

- “使用场景”要写业务背景。
- “常见使用地方”要写 Laravel / 后端项目中经常出现的位置。
- 不要只写概念定义。
- 不要写完整实现代码。
- 不要给出完整答案。
- 可以列出建议类名。
- 可以列出 TODO。
- 可以提示测试方向。

------

## 24. README 更新要求

项目根目录 `README.md` 如果存在，不要覆盖。

只在文件末尾追加一个章节：

~~~md
## Laravel Design Patterns Lab

本项目新增了一个设计模式练习模块：PatternLab。

访问页面：

```bash
php artisan serve
http://127.0.0.1:8000/pattern-lab
~~~

可用命令：

```bash
php artisan pattern-lab:list
php artisan pattern-lab:show strategy
php artisan pattern-lab:next
```

文档目录：

```text
docs/pattern-lab/
```

练习代码目录：

```text
app/PatternLab/
```

注意：

本模块只提供设计模式说明、场景、TODO、练习骨架和测试骨架，不提供完整实现。具体设计模式代码需要学习者自己完成。

```
如果 README.md 不存在，则创建一个新的 README.md，并包含上述内容。

---

## 25. 路由检查要求

完成后请运行或提示用户运行：

​```bash
php artisan route:list
```

确认新增路由：

```text
GET pattern-lab
GET pattern-lab/{category}
GET pattern-lab/{category}/{pattern}
```

并确认没有覆盖原有演示路由。

------

## 26. 命令检查要求

完成后请运行或提示用户运行：

```bash
php artisan list | grep pattern-lab
```

应该看到：

```text
pattern-lab:list
pattern-lab:show
pattern-lab:next
```

------

## 27. 测试检查要求

完成后请运行或提示用户运行：

```bash
php artisan test
```

由于测试文件中使用 `markTestIncomplete()`，可能会出现 incomplete 提示，这是正常的。

也可以只运行：

```bash
php artisan test --filter PatternLab
```

------

## 28. 最终输出格式

完成任务后，请输出：

~~~md
## 本次完成内容

### 新增目录

- app/PatternLab/...
- docs/pattern-lab/...
- resources/views/pattern-lab/...
- tests/Unit/PatternLab/...
- tests/Feature/PatternLab/...

### 新增核心文件

- app/PatternLab/PatternRegistry.php
- app/Http/Controllers/PatternLabController.php
- app/Console/Commands/PatternLabListCommand.php
- app/Console/Commands/PatternLabShowCommand.php
- app/Console/Commands/PatternLabNextCommand.php

### 新增路由

- GET /pattern-lab
- GET /pattern-lab/{category}
- GET /pattern-lab/{category}/{pattern}

### 新增命令

- php artisan pattern-lab:list
- php artisan pattern-lab:show {pattern}
- php artisan pattern-lab:next

### 新增文档

- docs/pattern-lab/00-learning-roadmap.md
- docs/pattern-lab/01-how-to-practice.md
- docs/pattern-lab/02-pattern-summary-table.md
- docs/pattern-lab/03-pattern-vs-laravel.md
- 每个模式的独立 Markdown 文档

### 新增练习骨架

- 每个模式的 Exercise.php
- 每个模式的 README.md
- 每个模式的 TODO.md
- 每个模式的测试骨架

## 如何访问

```bash
php artisan serve
~~~

访问：

```text
http://127.0.0.1:8000/pattern-lab
```

## 如何使用命令

```bash
php artisan pattern-lab:list
php artisan pattern-lab:show strategy
php artisan pattern-lab:next
```

## 如何开始练习

建议从 Strategy 策略模式开始：

文档：

```text
docs/pattern-lab/behavioral/strategy.md
```

代码目录：

```text
app/PatternLab/Behavioral/Strategy
```

测试文件：

```text
tests/Unit/PatternLab/Behavioral/StrategyTest.php
```

## 注意

本次只搭建设计模式练习框架，没有实现任何具体设计模式逻辑。

```
---

## 29. 第一阶段执行范围

本次只执行第一阶段：

1. 在当前 Laravel 13 项目中新增 PatternLab 模块。
2. 创建目录。
3. 创建 PatternRegistry。
4. 创建 Controller。
5. 创建 Blade 页面。
6. 创建 Web 路由。
7. 创建 Artisan 命令。
8. 创建所有模式文档。
9. 创建所有 Exercise.php 骨架。
10. 创建所有 README.md / TODO.md。
11. 创建测试骨架。
12. 追加 README 说明。

不要继续实现任何具体设计模式。

---

## 30. 最重要的提醒

再次强调：

​```text
本任务不是实现设计模式。
本任务是搭建设计模式练习环境。
设计模式具体实现由用户自己完成。
```

请严格遵守。