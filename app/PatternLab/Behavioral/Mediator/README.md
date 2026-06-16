# Mediator 中介者模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成中介者模式代码实现。

## 练习内容

**具体功能：门店库存筛选联动**

你正在实现一个库存筛选表单，省份、城市、门店和库存结果互相影响，但字段之间不应直接相互调用。

具体要求：

- 设计中介者协调省份、城市、门店、库存四个字段或组件。
- 字段变化时只通知中介者，由中介者决定其他字段如何更新。
- 在 Exercise.php::run() 中演示选择省份后刷新城市和门店范围。
- 补充测试验证新增字段不会让旧字段之间产生直接依赖。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- FormMediator.php
- ProvinceField.php
- CityField.php
- StoreField.php
- InventoryField.php

## 运行入口

```bash
php artisan pattern-lab:show mediator
```

## 测试入口

```bash
php artisan test --filter MediatorTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
