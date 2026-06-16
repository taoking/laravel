# Null Object 空对象模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成空对象模式代码实现。

## 练习内容

**具体功能：用户默认地址空对象**

你正在实现结算页地址展示，用户可能没有默认地址，但页面仍需要稳定返回收货人、电话和地址摘要。

具体要求：

- 定义地址契约，让真实地址和空地址对象拥有一致方法。
- 没有默认地址时返回空地址对象，而不是返回 null。
- 在 Exercise.php::run() 中演示有地址用户和无地址用户的结算摘要。
- 补充测试验证调用方不需要写多处 null 判断，同时不会隐藏必须报错的场景。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- AddressInterface.php
- UserAddress.php
- NullAddress.php

## 运行入口

```bash
php artisan pattern-lab:show null-object
```

## 测试入口

```bash
php artisan test --filter NullObjectTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
