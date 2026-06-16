# 设计模式汇总表

| 分类 | 模式 | 中文名 | 难度 | 使用场景 | 常见使用地方 | 状态 |
|---|---|---|---|---|---|---|
| 创建型 | Simple Factory | 简单工厂 | easy | 根据导出类型创建不同导出器。 | 报表导出、文件生成、消息格式化、第三方服务客户端创建、简单对象创建分支 | todo |
| 创建型 | Factory Method | 工厂方法 | medium | 不同通知渠道创建不同消息发送器。 | 通知系统、支付渠道、文件存储驱动、第三方平台接入、多渠道业务处理 | todo |
| 创建型 | Abstract Factory | 抽象工厂 | hard | 不同支付渠道创建一组支付相关对象。 | 支付系统、多租户主题组件、多数据库方言适配、多云服务 SDK 适配、多产品族对象创建 | todo |
| 创建型 | Builder | 建造者模式 | medium | 构建复杂报表查询条件。 | 查询条件构建、报表配置构建、API 请求参数构建、消息体构建、复杂 DTO 构建 | todo |
| 创建型 | Prototype | 原型模式 | medium | 复制已有图表配置，并在副本上修改部分字段。 | 图表模板复制、报表模板复制、表单模板复制、权限配置复制、复杂对象克隆 | todo |
| 创建型 | Singleton | 单例模式 | easy | 全局配置读取器。 | 配置管理、日志管理、连接管理、但在 Laravel 中通常由服务容器管理生命周期 | todo |
| 结构型 | Adapter | 适配器模式 | easy | 统一不同第三方短信平台接口。 | 第三方 SDK 接入、支付平台接入、短信平台接入、地图服务接入、文件存储服务接入、老系统接口兼容 | todo |
| 结构型 | Bridge | 桥接模式 | hard | 消息内容类型和发送渠道分离。 | 消息系统、多维度组合业务、多平台渲染、多渠道发送、报表展示方式和导出方式分离 | todo |
| 结构型 | Composite | 组合模式 | medium | 菜单树 / 权限树。 | 菜单管理、权限管理、部门组织树、分类树、文件目录树、评论树 | todo |
| 结构型 | Decorator | 装饰器模式 | medium | 给报表查询服务增加缓存、日志、性能统计。 | Service 增强、缓存包装、日志包装、性能监控、权限校验、API 客户端增强 | todo |
| 结构型 | Facade | 外观模式 | easy | 订单创建流程统一入口。 | 复杂业务流程入口、多服务协调、Controller 调用业务流程、支付流程、报表生成流程、文件上传处理流程 | todo |
| 结构型 | Proxy | 代理模式 | medium | 远程文件服务访问代理。 | 远程服务访问、文件服务、API 客户端、权限控制、缓存代理、延迟加载 | todo |
| 结构型 | Data Mapper | 数据映射模式 | hard | 将数据库记录和领域对象分离。 | 领域驱动设计、复杂业务模型、多数据源映射、避免领域对象依赖 ORM、替代 Active Record 的复杂场景 | todo |
| 结构型 | Dependency Injection | 依赖注入 | easy | 订单服务依赖支付服务、库存服务、通知服务。 | Laravel Service、Controller 构造函数、Job 构造函数、Event Listener、Repository、第三方客户端 | todo |
| 结构型 | Fluent Interface | 流式接口 | medium | 报表查询条件链式构建。 | Laravel Query Builder、Eloquent 查询、报表查询构建、API 参数构建、表单配置构建 | todo |
| 结构型 | Registry | 注册表模式 | medium | 统一登记可用报表组件或导出器。 | 插件系统、组件系统、策略列表管理、服务查找、配置集中管理 | todo |
| 行为型 | Strategy | 策略模式 | easy | 订单运费计算，根据不同配送方式选择不同计算规则。 | 支付方式选择、优惠券计算、运费计算、报表导出格式、数据同步策略、第三方接口调用策略 | todo |
| 行为型 | Observer | 观察者模式 | easy | 订单支付成功后触发多个后续动作。 | Laravel Event / Listener、模型事件、订单状态变化、用户注册成功、支付成功回调、消息通知 | todo |
| 行为型 | Command | 命令模式 | medium | 后台任务封装。 | Laravel Job、Artisan Command、队列任务、后台操作、批处理任务、可重试任务 | todo |
| 行为型 | Chain of Responsibility | 责任链模式 | medium | 请求风控校验。 | Laravel Middleware、Pipeline、请求过滤、数据导入校验、风控规则、审批流程 | todo |
| 行为型 | State | 状态模式 | medium | 订单状态流转。 | 订单系统、审批系统、工单系统、支付单状态、发票状态、任务状态机 | todo |
| 行为型 | Template Method | 模板方法 | medium | 不同数据导入流程。 | 数据导入、文件处理、报表生成、支付回调处理、定时任务流程、数据清洗流程 | todo |
| 行为型 | Specification | 规格模式 | hard | 优惠券是否可用判断。 | 优惠券系统、权限判断、风控规则、商品筛选、用户资格判断、复杂业务规则组合 | todo |
| 行为型 | Iterator | 迭代器模式 | medium | 分页读取大量订单或日志。 | 大数据量分页处理、LazyCollection、文件逐行读取、游标查询、批量任务 | todo |
| 行为型 | Mediator | 中介者模式 | hard | 表单多个字段联动。 | 表单联动、工作流协调、多组件通信、复杂 UI 后端配置、业务对象协作 | todo |
| 行为型 | Visitor | 访问者模式 | hard | 对不同报表节点执行不同操作。 | AST 处理、报表节点处理、表达式解析、权限扫描、复杂对象结构遍历 | todo |
| 行为型 | Null Object | 空对象模式 | easy | 用户没有默认地址时返回空地址对象。 | 用户资料、配置读取、权限对象、购物车、默认值处理、避免 null 判断 | todo |
| Laravel 常用模式 | Repository | 仓储模式 | medium | 用户数据读取、订单数据读取、复杂查询复用。 | Service 层调用数据访问、复杂查询封装、多数据源切换、测试时替换数据访问实现、领域模型数据获取 | todo |
| Laravel 常用模式 | Service Layer | 服务层模式 | easy | 订单创建、支付确认、报表生成。 | Controller 调用业务流程、订单业务、支付业务、用户业务、报表业务、数据同步业务 | todo |
| Laravel 常用模式 | Pipeline | 管道模式 | medium | 数据处理流水线、请求过滤、导入校验。 | Laravel Pipeline、Middleware、数据导入校验、内容过滤、请求预处理、报表数据加工 | todo |
| Laravel 常用模式 | Event / Listener | 事件监听模式 | easy | 订单支付成功后通知、积分、日志。 | Laravel Event、Laravel Listener、用户注册、支付成功、订单完成、审批通过、文件上传完成 | todo |
| Laravel 常用模式 | Job / Command | 任务命令模式 | easy | 异步导出报表、发送邮件、生成文件。 | Laravel Queue、报表导出、邮件发送、文件生成、图片处理、数据同步 | todo |
| Laravel 常用模式 | Form Request Validation | 表单请求验证 | easy | 创建订单、提交表单、接口参数验证。 | Controller 入参验证、API 请求验证、表单提交、后台管理、创建和更新操作 | todo |
