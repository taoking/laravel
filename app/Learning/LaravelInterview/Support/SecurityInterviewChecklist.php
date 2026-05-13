<?php

namespace App\Learning\LaravelInterview\Support;

class SecurityInterviewChecklist
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'authentication' => [
                'title' => '认证 Authentication',
                'risk' => '认证回答“你是谁”。风险集中在弱密码、凭证泄漏、会话固定、token 存储不当和暴力破解。',
                'laravel_defenses' => [
                    '密码使用 Hash facade，不自行实现哈希算法。',
                    '登录、短信验证码、找回密码接口使用 RateLimiter。',
                    'Session cookie 启用 http_only、secure、same_site。',
                    'API token 使用 Sanctum 或 Passport 时明确 token 作用域和过期策略。',
                ],
                'pitfalls' => [
                    '把 token 明文写入日志。',
                    '登录失败提示区分账号不存在和密码错误。',
                    'FPM、队列、定时任务使用不同 APP_KEY 或配置。',
                ],
                'interview_answer' => '认证要从密码哈希、会话安全、token 生命周期、登录限流和日志脱敏一起设计。',
            ],
            'authorization' => [
                'title' => '授权 Authorization',
                'risk' => '授权回答“你能不能做这件事”。最常见风险是越权访问、IDOR、后台权限过宽。',
                'laravel_defenses' => [
                    '使用 Gate/Policy 表达模型级权限。',
                    '列表查询必须按租户、组织或用户边界过滤。',
                    '后台操作按角色、资源、动作拆分权限点。',
                    '关键操作记录审计日志。',
                ],
                'pitfalls' => [
                    '只在前端隐藏按钮，不在后端校验。',
                    '详情接口按 id 查询但没有校验资源归属。',
                    '管理员权限没有最小化和审批链路。',
                ],
                'interview_answer' => '授权必须在服务端做，既要校验动作权限，也要校验数据归属。',
            ],
            'sql-injection' => [
                'title' => 'SQL 注入',
                'risk' => '用户输入被拼接进 SQL 后，攻击者可能读取、修改或删除数据。',
                'laravel_defenses' => [
                    '优先使用 Query Builder 和 Eloquent 参数绑定。',
                    'Raw SQL 必须使用 bindings，不直接拼接用户输入。',
                    '排序字段、表名、列名这类不能绑定的部分必须白名单。',
                    '数据库账号按最小权限配置。',
                ],
                'pitfalls' => [
                    'orderBy、groupBy、selectRaw 中拼接 request 参数。',
                    '为了排查方便把完整 SQL 和敏感 bindings 打进日志。',
                    '认为用了 ORM 就绝对不会注入。',
                ],
                'interview_answer' => 'Laravel 默认参数绑定能防大部分值注入，但字段名、排序方向、raw 表达式必须额外白名单。',
            ],
            'xss' => [
                'title' => 'XSS',
                'risk' => '恶意脚本在用户浏览器执行，可能窃取会话、篡改页面或发起 CSRF 链式攻击。',
                'laravel_defenses' => [
                    'Blade 默认 `{{ }}` 会 HTML escape。',
                    '富文本内容进入前先净化，输出时按白名单渲染。',
                    'Cookie 设置 http_only，降低脚本读取风险。',
                    '关键后台页面可结合 CSP 降低脚本注入影响面。',
                ],
                'pitfalls' => [
                    '滥用 `{!! !!}` 输出用户内容。',
                    '只在输入时过滤，没有按输出上下文编码。',
                    '把 Markdown、富文本、SVG 当普通文本处理。',
                ],
                'interview_answer' => 'XSS 防护核心是按输出上下文编码，富文本要白名单净化，不能简单依赖输入过滤。',
            ],
            'csrf' => [
                'title' => 'CSRF',
                'risk' => '攻击者诱导已登录用户浏览器发起非预期请求。',
                'laravel_defenses' => [
                    'Web 表单启用 CSRF middleware 和 `@csrf`。',
                    '状态变更接口不要使用 GET。',
                    'Cookie 配置 SameSite，敏感操作二次确认。',
                    'API token 场景要区分 cookie 会话和 bearer token。',
                ],
                'pitfalls' => [
                    '为了调试把业务路由加入 CSRF except。',
                    'GET 接口执行删除、支付、审核等状态变更。',
                    '混用 Web session 和跨站 API 调用边界不清。',
                ],
                'interview_answer' => 'CSRF 是浏览器自动携带凭证导致的问题，Laravel Web 路由默认有 token 校验，但状态变更方法和 SameSite 同样重要。',
            ],
            'ssrf' => [
                'title' => 'SSRF',
                'risk' => '服务端按用户输入访问 URL，攻击者可能探测内网、云元数据或本机服务。',
                'laravel_defenses' => [
                    '用户可控 URL 必须做协议、host、端口白名单。',
                    '禁止访问内网 IP、localhost、link-local、metadata 地址。',
                    'HTTP client 设置短 timeout、禁止无限重定向。',
                    '下载远程资源走隔离 worker 或代理服务。',
                ],
                'pitfalls' => [
                    '只校验字符串前缀，没有处理 DNS rebinding。',
                    '允许 file、gopher 等非 HTTP 协议。',
                    '图片抓取、Webhook 测试、URL 预览没有出口限制。',
                ],
                'interview_answer' => 'SSRF 不是简单判断 URL 格式，要校验解析后的协议、IP、端口、重定向和网络出口。',
            ],
            'file-upload' => [
                'title' => '文件上传',
                'risk' => '上传入口可能导致木马、路径穿越、超大文件、内容嗅探或敏感文件覆盖。',
                'laravel_defenses' => [
                    'FormRequest 校验大小、扩展名、MIME 和业务类型。',
                    '文件名重新生成，不信任原始文件名。',
                    '上传文件存储到非可执行目录，公开访问走对象存储或受控下载。',
                    '图片处理前限制尺寸和像素总量。',
                ],
                'pitfalls' => [
                    '只检查后缀，不检查真实内容类型。',
                    '把用户文件直接放进 public 并允许执行。',
                    '没有限制压缩包解压后的路径和体积。',
                ],
                'interview_answer' => '上传安全要同时处理校验、重命名、存储隔离、访问控制和资源消耗限制。',
            ],
            'secrets-logging' => [
                'title' => '密钥与日志脱敏',
                'risk' => '日志、异常、调试工具和队列 payload 可能泄漏密码、token、身份证、手机号等敏感信息。',
                'laravel_defenses' => [
                    '.env 不提交仓库，生产密钥使用 secret 管理。',
                    '日志 context 中对 token、password、authorization、cookie 脱敏。',
                    '异常上报前过滤敏感 request 字段。',
                    '队列 payload 避免携带明文敏感数据。',
                ],
                'pitfalls' => [
                    'APP_DEBUG 在生产开启。',
                    '把请求头、完整 request body 原样打日志。',
                    '测试环境和生产环境共用第三方密钥。',
                ],
                'interview_answer' => '密钥安全不只是不提交 .env，还要管日志、异常、队列、备份和第三方监控中的敏感字段。',
            ],
            'webhook-signature' => [
                'title' => 'Webhook 签名校验',
                'risk' => '支付、物流、消息回调如果不验签，攻击者可以伪造状态变更。',
                'laravel_defenses' => [
                    '使用原始 body、时间戳、nonce 和共享密钥计算签名。',
                    '校验时间窗口，防止重放。',
                    '签名比较使用 hash_equals。',
                    '业务处理使用幂等键和状态机。',
                ],
                'pitfalls' => [
                    '使用解析后的 JSON 重新编码参与验签。',
                    '只验签不校验订单金额和状态流转。',
                    '回调处理成功但响应失败后重复通知导致重复入账。',
                ],
                'interview_answer' => 'Webhook 安全要验签、防重放、幂等和状态机一起做，支付类还要核对金额、商户号和订单归属。',
            ],
            'rate-limit' => [
                'title' => '限流与防刷',
                'risk' => '登录、验证码、搜索、导出和昂贵接口可能被刷爆或撞库。',
                'laravel_defenses' => [
                    'RateLimiter 按用户、IP、设备、业务 key 组合限流。',
                    '昂贵接口设置配额、分页上限和异步导出。',
                    '验证码接口加冷却时间和图形/行为校验。',
                    '异常流量触发降级或临时封禁。',
                ],
                'pitfalls' => [
                    '只按 IP 限流，代理池绕过。',
                    '所有接口同一限流阈值，没有业务分级。',
                    '限流结果没有监控，误伤正常用户。',
                ],
                'interview_answer' => '限流要按业务成本和身份维度设计，核心是保护资源而不是简单返回 429。',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function topic(string $key): ?array
    {
        $topics = $this->all();

        return $topics[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
