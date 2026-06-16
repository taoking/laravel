# Chain of Responsibility 责任链模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成责任链模式代码实现。

## 练习内容

**具体功能：登录风控校验链**

你正在实现登录前风控校验，请求需要依次经过账号状态、权限、黑名单和频率限制校验。

具体要求：

- 定义处理器契约，每个处理器只判断一个风控规则。
- 让请求沿链路传递，某个处理器拒绝时终止后续处理。
- 在 Exercise.php::run() 中演示一个被黑名单处理器拦截的登录请求。
- 补充测试验证处理顺序、短路行为和全部通过的场景。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- RiskCheckHandlerInterface.php
- LoginCheckHandler.php
- PermissionCheckHandler.php
- BlacklistCheckHandler.php
- RateLimitCheckHandler.php

## 运行入口

```bash
php artisan pattern-lab:show chain-of-responsibility
```

## 测试入口

```bash
php artisan test --filter ChainOfResponsibilityTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
