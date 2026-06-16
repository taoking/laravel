# Specification 规格模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成规格模式代码实现。

## 练习内容

**具体功能：优惠券可用性规则**

你正在实现优惠券是否可用的判断，规则包括是否过期、订单金额、商品分类、用户等级和新用户限制。

具体要求：

- 定义规格契约，每个业务规则独立成一个规格对象。
- 支持组合多个规格表达 and、or、not 的复杂条件。
- 在 Exercise.php::run() 中演示一个订单同时满足“未过期 + 满 100 元 + 指定分类”。
- 补充测试验证单个规则、组合规则和失败原因表达。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- CouponSpecificationInterface.php
- NotExpiredSpecification.php
- MinimumAmountSpecification.php
- UserLevelSpecification.php
- AndSpecification.php

## 运行入口

```bash
php artisan pattern-lab:show specification
```

## 测试入口

```bash
php artisan test --filter SpecificationTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
