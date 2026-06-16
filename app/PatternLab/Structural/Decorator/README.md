# Decorator 装饰器模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成装饰器模式代码实现。

## 练习内容

**具体功能：报表查询服务增强**

你正在实现报表查询服务，并希望在不改原服务的情况下增加缓存、日志和耗时统计。

具体要求：

- 定义报表查询服务契约，原始服务只负责查询数据。
- 用装饰器分别包装缓存、日志和耗时统计能力。
- 在 Exercise.php::run() 中演示原始服务被缓存装饰器和日志装饰器包裹后的调用顺序。
- 补充测试验证装饰器不改变原服务接口，并能按顺序叠加能力。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ReportQueryServiceInterface.php
- ReportQueryService.php
- CachedReportQueryService.php
- LoggedReportQueryService.php

## 运行入口

```bash
php artisan pattern-lab:show decorator
```

## 测试入口

```bash
php artisan test --filter DecoratorTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
