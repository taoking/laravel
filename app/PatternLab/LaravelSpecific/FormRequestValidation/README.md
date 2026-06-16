# Form Request Validation 表单请求验证练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成表单请求验证代码实现。

## 练习内容

**具体功能：创建订单请求验证**

你正在实现创建订单接口，请求参数包括商品明细、收货地址、优惠券编号和支付方式，需要先完成输入验证。

具体要求：

- 设计 FormRequest 规则，校验商品列表、数量、地址、优惠券格式和支付方式。
- 把输入验证从 Controller 中移走，Controller 只接收 validated 数据。
- 在 Exercise.php::run() 中演示一组合法请求和一组缺少地址的非法请求摘要。
- 补充测试验证必填、类型、枚举值和嵌套数组校验。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- StoreOrderRequest.php
- UpdateProfileRequest.php
- SubmitFormRequest.php

## 运行入口

```bash
php artisan pattern-lab:show form-request-validation
```

## 测试入口

```bash
php artisan test --filter FormRequestValidationTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
