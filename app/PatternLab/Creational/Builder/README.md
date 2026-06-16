# Builder 建造者模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成建造者模式代码实现。

## 练习内容

**具体功能：复杂报表查询条件构建器**

你正在实现一个指标报表查询功能，用户可以选择指标、维度、时间范围、过滤条件、排序和分页参数。

具体要求：

- 设计一个报表查询条件对象，表达 metric、dimension、date range、filters、sort、page。
- 使用 Builder 分步骤设置查询条件，避免构造函数参数过长。
- 在 Exercise.php::run() 中构建“最近 30 天按地区统计收入”的查询条件。
- 补充测试验证必填项、默认分页和链式构建结果。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ReportQuery.php
- ReportQueryBuilder.php
- ReportFilter.php
- ReportSort.php

## 运行入口

```bash
php artisan pattern-lab:show builder
```

## 测试入口

```bash
php artisan test --filter BuilderTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
