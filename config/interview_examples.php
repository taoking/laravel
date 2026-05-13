<?php

return [
    // 默认关闭，避免学习样例影响真实业务路由。
    'enabled' => env('INTERVIEW_EXAMPLES_ENABLED', false),

    // 示例中间件读取该 token：X-Interview-Token: demo-token。
    'token' => env('INTERVIEW_EXAMPLES_TOKEN', 'demo-token'),

    // 示例邮件和队列任务可读取的收件人配置。
    'mail_to' => env('INTERVIEW_EXAMPLES_MAIL_TO', 'demo@example.com'),

    'infrastructure' => [
        'rabbitmq' => [
            'host' => env('RABBITMQ_HOST', 'rabbitmq'),
            'port' => (int) env('RABBITMQ_PORT', 5672),
            'management_port' => (int) env('RABBITMQ_MANAGEMENT_PORT', 15672),
            'user' => env('RABBITMQ_USER', 'laravel'),
            'password' => env('RABBITMQ_PASSWORD', 'secret'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
        ],

        'mailpit' => [
            'host' => env('MAIL_HOST', 'mailpit'),
            'port' => (int) env('MAIL_PORT', 1025),
        ],
    ],
];
