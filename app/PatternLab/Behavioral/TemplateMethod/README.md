# Template Method 模板方法练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成模板方法代码实现。

## 练习内容

**具体功能：数据导入流程模板**

你正在实现数据导入功能，用户导入、订单导入和商品导入都有读取、校验、转换、保存、生成报告的共同流程。

具体要求：

- 定义抽象导入模板，固定共同流程顺序。
- 让具体导入类只实现差异步骤，例如字段校验和转换规则。
- 在 Exercise.php::run() 中演示用户导入流程执行到生成报告。
- 补充测试验证共同流程顺序稳定，子类只替换差异步骤。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- AbstractImportTemplate.php
- UserImport.php
- OrderImport.php
- ProductImport.php

## 运行入口

```bash
php artisan pattern-lab:show template-method
```

## 测试入口

```bash
php artisan test --filter TemplateMethodTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
