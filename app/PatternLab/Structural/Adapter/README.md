# Adapter 适配器模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成适配器模式代码实现。

## 练习内容

**具体功能：统一短信平台发送接口**

你正在实现短信发送功能，阿里云、腾讯云和华为云 SDK 的方法名、参数和返回结构都不一致。

具体要求：

- 定义统一短信发送接口，输入手机号、模板编号和模板变量。
- 为至少两个模拟短信平台创建适配器，把不同 SDK 调用转换成统一结果。
- 在 Exercise.php::run() 中演示业务层只依赖统一接口发送验证码短信。
- 补充测试验证不同平台返回结果被规范化成同一种结构。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- SmsSenderInterface.php
- AliyunSmsAdapter.php
- TencentSmsAdapter.php
- HuaweiSmsAdapter.php

## 运行入口

```bash
php artisan pattern-lab:show adapter
```

## 测试入口

```bash
php artisan test --filter AdapterTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
