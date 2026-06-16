# Visitor 访问者模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成访问者模式代码实现。

## 练习内容

**具体功能：报表节点操作扩展**

你正在实现一个报表编辑器，报表由文本、图表、表格、筛选器节点组成，需要支持导出、权限扫描和字段统计。

具体要求：

- 定义报表节点结构，并保持节点结构相对稳定。
- 定义访问者来承载导出、权限扫描或字段统计等新增操作。
- 在 Exercise.php::run() 中演示对一个小报表节点树执行字段统计访问者。
- 补充测试验证新增操作不需要修改节点类。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ReportNodeInterface.php
- TextNode.php
- ChartNode.php
- TableNode.php
- ReportNodeVisitorInterface.php
- ExportVisitor.php

## 运行入口

```bash
php artisan pattern-lab:show visitor
```

## 测试入口

```bash
php artisan test --filter VisitorTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
