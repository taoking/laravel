<?php

namespace App\Learning\LaravelInterview\Support;

class MessageQueueComparison
{
    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return [
            'matrix' => [
                'rabbitmq' => [
                    'model' => 'broker queue + exchange routing',
                    'strengths' => [
                        '灵活 routing：direct、topic、fanout、headers。',
                        'ACK、NACK、死信、重试队列模型清晰。',
                        '适合业务任务、通知、异步处理和复杂路由。',
                    ],
                    'tradeoffs' => [
                        '超高吞吐日志流不是最强项。',
                        '队列堆积和消费者 ack 行为需要精细监控。',
                    ],
                    'best_for' => [
                        '订单状态异步流转。',
                        '邮件、短信、站内信通知。',
                        '需要复杂 routing key 和死信处理的业务队列。',
                    ],
                ],
                'kafka' => [
                    'model' => 'append-only log + partition + consumer group',
                    'strengths' => [
                        '高吞吐、顺序追加、可回放。',
                        'offset 由消费者组管理，适合多订阅方消费同一数据流。',
                        '分区内有序，适合日志、埋点、CDC、事件流。',
                    ],
                    'tradeoffs' => [
                        '业务级延迟队列、单消息重试、复杂 routing 需要额外设计。',
                        '分区数、key 选择、rebalance 和 lag 监控复杂度更高。',
                    ],
                    'best_for' => [
                        '用户行为日志。',
                        '订单事件流和数据同步。',
                        '大吞吐、多消费者组、可回放的数据管道。',
                    ],
                ],
                'rocketmq' => [
                    'model' => 'topic + message queue + consumer group',
                    'strengths' => [
                        '业务消息能力丰富，支持延迟消息、顺序消息、事务消息等场景。',
                        '适合电商、支付、交易链路中的最终一致性。',
                        '消费重试和死信语义更贴近业务消息。',
                    ],
                    'tradeoffs' => [
                        '生态和团队经验要结合公司技术栈评估。',
                        '事务消息不能替代业务幂等和对账。',
                    ],
                    'best_for' => [
                        '交易系统最终一致。',
                        '订单超时关闭。',
                        '需要事务消息或业务延迟消息的场景。',
                    ],
                ],
            ],
            'selection_rules' => [
                '复杂路由、任务队列、死信补偿优先 RabbitMQ。',
                '高吞吐日志流、多消费者组、可回放优先 Kafka。',
                '交易消息、延迟消息、事务消息能力优先评估 RocketMQ。',
                'Laravel 应用内异步任务优先 Laravel Queue，跨系统集成再引入业务 MQ。',
                'MQ 选型不能只看吞吐，还要看一致性、运维能力、团队经验和故障恢复。',
            ],
            'reliability_patterns' => [
                'outbox' => '本地事务写业务表和 outbox 表，由后台任务可靠投递 MQ。',
                'idempotent_consumer' => '消费者用业务幂等键、唯一索引或消费记录表去重。',
                'dead_letter' => '超过最大重试次数进入死信队列，触发告警、人工修复或补偿任务。',
                'backpressure' => '消息堆积时先限流生产者，再扩容消费者或拆分队列。',
                'schema_evolution' => '跨系统消息要管理 schema 版本，避免生产者升级破坏旧消费者。',
            ],
            'interview_points' => [
                'RabbitMQ 更像任务和路由中心，Kafka 更像可回放的分布式日志，RocketMQ 更贴近交易业务消息。',
                '消息不丢要看生产确认、Broker 持久化、消费 ACK 和失败补偿，不是只靠 MQ 自身。',
                '顺序消息通常只能保证局部顺序，例如同一个订单 key 落到同一分区或队列。',
                '重复消费是常态，消费者幂等是底线。',
                '消息堆积处理要先判断生产速率、消费速率、失败率和下游瓶颈。',
            ],
        ];
    }
}
