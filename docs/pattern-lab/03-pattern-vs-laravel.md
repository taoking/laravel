# 设计模式与 Laravel

## Laravel 容器和依赖注入

Laravel 服务容器天然支持依赖注入，很多时候不需要手写复杂工厂或单例。练习设计模式时，应优先理解容器解决了什么问题，再决定是否需要额外模式类。

## Laravel Facade 和 GoF Facade 的区别

Laravel Facade 是静态代理风格的服务容器访问方式，不完全等同于 GoF 外观模式。GoF Facade 更强调为复杂子系统提供统一高层入口。

## Event / Listener 和 Observer

Laravel 事件监听机制和观察者模式思想接近，都用于解耦事件发生方和响应方。业务练习中可以先理解事件、监听器和模型事件的使用边界。

## Job / Queue 和 Command

Laravel Job 可以看作命令模式在异步任务场景中的应用。Job 把一个可执行操作封装成对象，再交给队列系统调度、重试和失败处理。

## Pipeline 和责任链

Laravel Pipeline 和责任链模式相似，都适合处理多个连续步骤。Middleware 是最常见的框架级示例。

## FormRequest 和 Specification

FormRequest 适合请求参数验证，Specification 更偏复杂业务规则判断。不要把复杂领域资格判断全部塞进 FormRequest。

## Repository 是否一定需要

简单 CRUD 不一定需要 Repository。复杂领域、数据源切换、查询逻辑复用较多时再考虑，否则会增加无意义的层级。
