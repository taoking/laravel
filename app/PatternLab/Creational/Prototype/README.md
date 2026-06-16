# Prototype 原型模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成原型模式代码实现。

## 练习内容

**具体功能：图表配置模板复制**

你正在实现一个仪表盘配置功能，用户可以复制已有图表模板，再修改标题、数据源和筛选条件生成新图表。

具体要求：

- 设计图表配置对象，包含标题、图表类型、数据源、筛选条件和序列配置。
- 通过复制已有配置生成新配置，修改副本时不能影响原模板。
- 在 Exercise.php::run() 中复制一个柱状图模板并改成“华东收入趋势”。
- 补充测试区分浅拷贝和深拷贝对嵌套配置的影响。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ChartConfig.php
- ChartSeries.php
- ChartConfigCloner.php

## 运行入口

```bash
php artisan pattern-lab:show prototype
```

## 测试入口

```bash
php artisan test --filter PrototypeTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
