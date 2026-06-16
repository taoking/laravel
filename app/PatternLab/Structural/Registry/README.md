# Registry 注册表模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成注册表模式代码实现。

## 练习内容

**具体功能：导出器注册表**

你正在实现一个导出器注册中心，系统启动时登记 csv、excel、pdf 导出器，业务运行时按 key 查找。

具体要求：

- 设计注册、查找、列出可用导出器的注册表入口。
- 处理重复注册、未知 key 和状态禁用的情况。
- 在 Exercise.php::run() 中注册三个导出器并查找 `excel`。
- 补充测试验证注册表和 Laravel 服务容器的职责边界。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ReportComponentRegistry.php
- ExporterRegistry.php

## 运行入口

```bash
php artisan pattern-lab:show registry
```

## 测试入口

```bash
php artisan test --filter RegistryTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
