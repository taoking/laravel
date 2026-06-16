# Fluent Interface 流式接口练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成流式接口代码实现。

## 练习内容

**具体功能：报表查询链式 DSL**

你正在实现一个报表查询配置器，让调用方用链式调用表达指标、维度、时间范围、排序和分页。

具体要求：

- 设计每个配置方法都返回当前对象或新的配置对象。
- 链式调用应能表达 `metric()->groupBy()->between()->sortBy()->paginate()`。
- 在 Exercise.php::run() 中构建“收入按城市分组并按金额倒序”的查询配置。
- 补充测试验证链式顺序、最终配置和过长链式调用的可读性边界。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ReportCriteria.php
- ReportCriteriaBuilder.php

## 运行入口

```bash
php artisan pattern-lab:show fluent-interface
```

## 测试入口

```bash
php artisan test --filter FluentInterfaceTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
