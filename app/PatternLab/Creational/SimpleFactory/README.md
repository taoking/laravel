# Simple Factory 简单工厂练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成简单工厂代码实现。

## 练习内容

**具体功能：报表导出器选择器**

你正在实现一个报表导出入口，用户传入导出类型 `csv`、`excel`、`pdf` 或 `json`，系统选择对应导出器处理同一份指标数据。

具体要求：

- 设计一个统一导出器契约，接收行数据和导出选项。
- 通过工厂根据导出类型返回对应导出器，调用方不要直接 new 具体导出器。
- 在 Exercise.php::run() 中演示选择 `excel` 导出 3 行指标数据，并返回一段说明文本。
- 补充测试覆盖四种导出类型和未知类型的处理。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ExporterInterface.php
- CsvExporter.php
- ExcelExporter.php
- PdfExporter.php
- JsonExporter.php
- ExporterFactory.php

## 运行入口

```bash
php artisan pattern-lab:show simple-factory
```

## 测试入口

```bash
php artisan test --filter SimpleFactoryTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
