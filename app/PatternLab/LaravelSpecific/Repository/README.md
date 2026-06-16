# Repository 仓储模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成仓储模式代码实现。

## 练习内容

**具体功能：订单查询仓储**

你正在实现订单列表查询，业务层需要读取最近已支付订单、按用户筛选订单和查询订单详情。

具体要求：

- 定义仓储契约，把复杂查询从业务服务中移出。
- 设计 Eloquent 实现和测试替身的边界。
- 在 Exercise.php::run() 中演示业务服务通过仓储读取“最近 10 笔已支付订单”。
- 补充测试验证业务服务可以替换为内存仓储而不依赖数据库。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- UserRepositoryInterface.php
- EloquentUserRepository.php
- OrderRepositoryInterface.php

## 运行入口

```bash
php artisan pattern-lab:show repository
```

## 测试入口

```bash
php artisan test --filter RepositoryTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
