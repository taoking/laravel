# Iterator 迭代器模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成迭代器模式代码实现。

## 练习内容

**具体功能：分页订单批处理迭代器**

你正在实现一个批处理任务，需要分页读取大量订单或日志，避免一次性加载全部数据。

具体要求：

- 设计一个迭代器隐藏分页或游标读取细节。
- 调用方只关心逐条处理订单，不关心当前是第几页。
- 在 Exercise.php::run() 中演示遍历 3 页模拟订单并统计处理数量。
- 补充测试验证空数据、最后一页不足和大批量读取不会一次性加载。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- OrderCursorIterator.php
- PaginatedOrderCollection.php
- OrderBatchReader.php

## 运行入口

```bash
php artisan pattern-lab:show iterator
```

## 测试入口

```bash
php artisan test --filter IteratorTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
