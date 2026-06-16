# Proxy 代理模式练习

## 练习目标

请你根据 `PATTERN.md` 中的说明，自己完成代理模式代码实现。

## 练习内容

**具体功能：远程文件下载代理**

你正在实现文件下载功能，真实远程文件服务访问前需要做权限检查、元信息缓存和延迟加载。

具体要求：

- 定义文件服务契约，真实服务只表达远程读取能力。
- 创建代理对象，在访问真实服务前加入权限检查和缓存判断。
- 在 Exercise.php::run() 中演示用户下载报表文件前先经过代理校验。
- 补充测试验证无权限不会访问真实服务，有缓存时减少远程调用。

## 当前状态

todo

## 本目录说明

本目录只提供练习入口和 TODO，不提供完整答案。

## 建议你自己创建的文件

- FileServiceInterface.php
- RemoteFileService.php
- AuthorizedFileServiceProxy.php
- CachedFileServiceProxy.php

## 运行入口

```bash
php artisan pattern-lab:show proxy
```

## 测试入口

```bash
php artisan test --filter ProxyTest
```

## 注意

请先自己实现，再让 Codex review。不要直接让 Codex 替你完成。
