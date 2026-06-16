# Strategy 策略模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成策略模式代码实现。

## 练习内容

**具体功能：订单运费计算器**

你正在实现订单运费计算功能，不同配送方式有不同计费规则，例如普通快递、加急配送、同城配送和会员免邮。

具体要求：

- 定义统一运费计算策略，输入订单金额、重量、距离和会员等级。
- 至少设计普通快递、加急配送、同城配送三种策略。
- 在 Exercise.php::run() 中演示同一订单切换不同配送方式得到不同运费说明。
- 补充测试验证不同策略的选择、边界金额和会员免邮场景。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- ShippingFeeStrategyInterface.php
- NormalShippingStrategy.php
- ExpressShippingStrategy.php
- SameCityShippingStrategy.php
- ShippingFeeContext.php

## 运行入口

```bash
php artisan pattern-lab:show strategy
```

## 测试入口

```bash
php artisan test --filter StrategyTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
