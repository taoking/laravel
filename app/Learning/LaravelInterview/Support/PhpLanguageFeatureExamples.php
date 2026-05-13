<?php

namespace App\Learning\LaravelInterview\Support;

use Fiber;
use JsonSerializable;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Throwable;

class PhpLanguageFeatureExamples
{
    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return [
            'runtime' => [
                'php_version' => PHP_VERSION,
                'php_version_id' => PHP_VERSION_ID,
                'sapi' => PHP_SAPI,
            ],
            'php_81' => [
                'status' => $this->versionStatus(80100),
                'examples' => [
                    'enum' => $this->php81EnumExample(),
                    'readonly_property' => $this->php81ReadonlyPropertyExample(),
                    'first_class_callable' => $this->php81FirstClassCallableExample(),
                    'fiber' => $this->php81FiberExample(),
                    'intersection_type' => $this->php81IntersectionTypeExample(),
                ],
            ],
            'php_82' => [
                'status' => $this->versionStatus(80200),
                'examples' => $this->runWhenAvailable(80200, fn (): array => [
                    'readonly_class' => $this->php82ReadonlyClassExample(),
                    'dnf_types' => $this->php82DnfTypeExample(),
                    'standalone_types' => $this->php82StandaloneTypeExample(),
                    'randomizer' => $this->php82RandomizerExample(),
                    'sensitive_parameter' => $this->php82SensitiveParameterExample(),
                ], $this->plannedFeatureList(80200, [
                    'readonly_class',
                    'dnf_types',
                    'standalone_true_false_null_types',
                    'random_extension',
                    'sensitive_parameter',
                ])),
            ],
            'php_83' => [
                'status' => $this->versionStatus(80300),
                'examples' => $this->runWhenAvailable(80300, fn (): array => [
                    'typed_class_constant_and_dynamic_fetch' => $this->php83TypedConstantExample(),
                    'override_attribute' => $this->php83OverrideAttributeExample(),
                    'json_validate' => $this->php83JsonValidateExample(),
                    'randomizer_additions' => $this->php83RandomizerAdditionExample(),
                    'mb_str_pad' => $this->php83MbStrPadExample(),
                ], $this->plannedFeatureList(80300, [
                    'typed_class_constants',
                    'dynamic_class_constant_fetch',
                    'override_attribute',
                    'json_validate',
                    'randomizer_additions',
                ])),
            ],
            'php_84' => [
                'status' => $this->versionStatus(80400),
                'examples' => $this->runWhenAvailable(80400, fn (): array => [
                    'property_hooks' => $this->php84PropertyHookExample(),
                    'reflection_lazy_objects' => [
                        'available' => method_exists(\ReflectionClass::class, 'newLazyGhost'),
                        'interview_point' => 'Lazy object 适合 ORM、容器或代理对象延迟初始化，但要关注调试和生命周期边界。',
                    ],
                    'rounding_mode_enum' => [
                        'available' => enum_exists(\RoundingMode::class),
                        'cases' => enum_exists(\RoundingMode::class) ? array_map(
                            fn (\RoundingMode $mode): string => $mode->name,
                            \RoundingMode::cases(),
                        ) : [],
                    ],
                ], [
                    'property_hooks' => [
                        'status' => 'requires PHP 8.4',
                        'snippet' => $this->php84PropertyHookSnippet(),
                    ],
                    'asymmetric_property_visibility' => [
                        'status' => 'requires PHP 8.4',
                        'interview_point' => '读写可见性拆分后，DTO/实体可表达 public read + restricted write。',
                    ],
                    'lazy_objects' => [
                        'status' => 'requires PHP 8.4',
                        'interview_point' => '框架可用 Reflection lazy object 推迟昂贵依赖初始化。',
                    ],
                    'deprecated_attribute' => [
                        'status' => 'requires PHP 8.4',
                        'interview_point' => '内建 #[Deprecated] 让自定义 API 废弃信息更标准。',
                    ],
                ]),
            ],
            'php_85' => [
                'status' => $this->versionStatus(80500),
                'examples' => $this->runWhenAvailable(80500, fn (): array => [
                    'pipe_operator' => $this->php85PipeOperatorExample(),
                    'clone_with' => $this->php85CloneWithExample(),
                    'array_first_last' => [
                        'first' => array_first(['draft', 'paid', 'shipped']),
                        'last' => array_last(['draft', 'paid', 'shipped']),
                    ],
                    'uri_extension' => [
                        'available' => class_exists('Uri\Rfc3986\Uri'),
                        'interview_point' => 'URI 扩展比 parse_url 更适合标准化 URL 解析，但迁移前要确认框架和扩展兼容性。',
                    ],
                ], [
                    'pipe_operator' => [
                        'status' => 'requires PHP 8.5',
                        'snippet' => $this->php85PipeOperatorSnippet(),
                    ],
                    'clone_with' => [
                        'status' => 'requires PHP 8.5',
                        'snippet' => $this->php85CloneWithSnippet(),
                    ],
                    'no_discard_attribute' => [
                        'status' => 'requires PHP 8.5',
                        'interview_point' => '返回值不可忽略的 API 可用 #[NoDiscard] 暴露调用方错误。',
                    ],
                    'uri_extension' => [
                        'status' => 'requires PHP 8.5',
                        'interview_point' => '官方 URI 扩展覆盖 RFC 3986 和 WHATWG URL 场景。',
                    ],
                ]),
            ],
            'interview_points' => [
                '新语法不能只背特性名，要能说明它解决的类型安全、可维护性或运行时风险。',
                'Laravel 项目升级 PHP 版本时，要同步检查 composer 平台约束、扩展、镜像、CI 和线上 FPM/CLI 版本。',
                '8.4/8.5 新语法不要直接写进 PHP 8.3 主应用源码，否则低版本运行时会在解析阶段失败。',
                '面试中被追问新特性时，应主动补充 BC break、弃用项、静态分析和框架兼容性。',
            ],
            'official_sources' => [
                'php_81' => 'https://www.php.net/releases/8.1/en.php',
                'php_82' => 'https://www.php.net/releases/8.2/en.php',
                'php_83' => 'https://www.php.net/releases/8.3/en.php',
                'php_84' => 'https://www.php.net/manual/en/migration84.new-features.php',
                'php_85' => 'https://www.php.net/releases/8.5/en.php',
                'php_85_migration' => 'https://www.php.net/manual/en/migration85.new-features.php',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function php81EnumExample(): array
    {
        $status = PhpFeatureOrderStatus::Paid;

        return [
            'value' => $status->value,
            'label' => $status->label(),
            'cases' => array_map(fn (PhpFeatureOrderStatus $case): string => $case->value, PhpFeatureOrderStatus::cases()),
            'interview_point' => 'Enum 让状态集合从散落字符串变成受类型系统约束的领域值。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function php81ReadonlyPropertyExample(): array
    {
        $dto = new Php81FeatureDto('readonly property', ['dto', 'value-object']);

        return [
            'topic' => $dto->topic,
            'tags' => $dto->tags,
            'interview_point' => 'readonly property 初始化后不可再赋值，适合不可变 DTO 和值对象。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function php81FirstClassCallableExample(): array
    {
        $length = strlen(...);
        $normalizer = $this->normalizeTitle(...);

        return [
            'length' => $length('Laravel'),
            'normalized' => $normalizer('  Senior PHP Interview  '),
            'interview_point' => 'First-class callable 比字符串函数名和数组 callable 更利于重构和静态分析。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function php81FiberExample(): array
    {
        $fiber = new Fiber(function (): string {
            Fiber::suspend('waiting-for-io');

            return 'fiber-finished';
        });

        try {
            $first = $fiber->start();
            $fiber->resume();

            return [
                'first_suspend_value' => $first,
                'final_return' => $fiber->getReturn(),
                'interview_point' => 'Fiber 是协程基础能力，业务通常通过 Swoole、Amp、ReactPHP 等抽象间接使用。',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'not switchable in current execution context',
                'error' => $exception::class,
                'interview_point' => 'Fiber 能力存在，但测试框架或调试器等上下文可能禁止切换 Fiber。',
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function php81IntersectionTypeExample(): array
    {
        $payload = new PhpFeaturePayload('intersection-type', ['Stringable', 'JsonSerializable']);

        return $this->renderSerializablePayload($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function php82ReadonlyClassExample(): array
    {
        $className = 'Php82ReadonlyDto'.str_replace('.', '', uniqid('_', true));
        $code = <<<PHP
readonly class {$className}
{
    public function __construct(
        public string \$topic,
        public int \$versionId,
    ) {
    }
}

\$dto = new {$className}('readonly class', 80200);

return [
    'topic' => \$dto->topic,
    'version_id' => \$dto->versionId,
    'interview_point' => 'readonly class 让类的所有实例属性默认只读，适合不可变数据结构。',
];
PHP;

        return $this->evaluateArray($code);
    }

    /**
     * @return array<string, mixed>
     */
    private function php82DnfTypeExample(): array
    {
        $value = new PhpFeaturePayload('dnf-types', ['Stringable', 'JsonSerializable']);
        $code = <<<'PHP'
return (static function ((\Stringable&\JsonSerializable)|null $value): array {
    return [
        'accepted' => $value !== null,
        'string' => $value === null ? null : (string) $value,
        'json' => $value?->jsonSerialize(),
        'interview_point' => 'DNF types 可以表达 (A&B)|null 这类更精确的输入约束。',
    ];
})($value);
PHP;

        return $this->evaluateArray($code, ['value' => $value]);
    }

    /**
     * @return array<string, mixed>
     */
    private function php82StandaloneTypeExample(): array
    {
        $code = <<<'PHP'
return [
    'true' => (static fn (): true => true)(),
    'false' => (static fn (): false => false)(),
    'null' => (static fn (): null => null)(),
    'interview_point' => 'true、false、null 可作为独立返回类型，适合表达更窄的契约。',
];
PHP;

        return $this->evaluateArray($code);
    }

    /**
     * @return array<string, mixed>
     */
    private function php82RandomizerExample(): array
    {
        if (! class_exists(Randomizer::class)) {
            return [
                'available' => false,
                'interview_point' => 'Random 扩展不可用时，应检查 PHP 版本和编译扩展。',
            ];
        }

        $randomizer = new Randomizer(new Mt19937(2026));

        return [
            'available' => true,
            'engine' => Mt19937::class,
            'int' => $randomizer->getInt(1, 100),
            'picked_keys' => $randomizer->pickArrayKeys(['a' => 1, 'b' => 2, 'c' => 3], 2),
            'interview_point' => 'Randomizer 避免全局随机状态污染，测试中可以用固定 seed 复现结果。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function php82SensitiveParameterExample(): array
    {
        $code = <<<'PHP'
$handler = static function (#[\SensitiveParameter] string $password): void {
    throw new \RuntimeException('sensitive failure');
};

try {
    $handler('secret-pass');
} catch (\Throwable $exception) {
    $argument = $exception->getTrace()[0]['args'][0] ?? null;

    return [
        'argument_debug_type' => get_debug_type($argument),
        'redacted' => is_object($argument) && $argument::class === \SensitiveParameterValue::class,
        'interview_point' => 'SensitiveParameter 可避免密码、token 等敏感参数出现在异常 trace 中。',
    ];
}

return ['redacted' => false];
PHP;

        return $this->evaluateArray($code);
    }

    /**
     * @return array<string, mixed>
     */
    private function php83TypedConstantExample(): array
    {
        $code = <<<'PHP'
$object = new class {
    public const string NAME = 'typed class constant';
    public const int VERSION = 80300;
};

$constant = 'NAME';

return [
    'name' => $object::{$constant},
    'version' => $object::VERSION,
    'interview_point' => 'Typed class constants 防止子类或实现类把常量改成不兼容类型。',
];
PHP;

        return $this->evaluateArray($code);
    }

    /**
     * @return array<string, mixed>
     */
    private function php83OverrideAttributeExample(): array
    {
        $code = <<<'PHP'
$renderer = new class extends \App\Learning\LaravelInterview\Support\PhpFeatureBaseRenderer {
    #[\Override]
    public function render(): string
    {
        return 'override checked';
    }
};

$method = new \ReflectionMethod($renderer, 'render');

return [
    'rendered' => $renderer->render(),
    'has_override_attribute' => $method->getAttributes(\Override::class) !== [],
    'interview_point' => '#[Override] 可以在重构父类方法名时提前暴露拼写错误。',
];
PHP;

        return $this->evaluateArray($code);
    }

    /**
     * @return array<string, mixed>
     */
    private function php83JsonValidateExample(): array
    {
        return [
            'valid_payload' => function_exists('json_validate') ? json_validate('{"topic":"php83"}') : null,
            'invalid_payload' => function_exists('json_validate') ? json_validate('{"topic":') : null,
            'interview_point' => 'json_validate 适合只校验 JSON 合法性且不需要反序列化结果的场景。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function php83RandomizerAdditionExample(): array
    {
        $randomizer = new Randomizer(new Mt19937(2026));

        return [
            'get_bytes_from_string_available' => method_exists($randomizer, 'getBytesFromString'),
            'sample' => method_exists($randomizer, 'getBytesFromString')
                ? $randomizer->getBytesFromString('abcdef', 8)
                : null,
            'interview_point' => '随机 API 升级后，业务 token 仍要区分测试可复现随机和安全随机。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function php83MbStrPadExample(): array
    {
        return [
            'available' => function_exists('mb_str_pad'),
            'result' => function_exists('mb_str_pad') ? mb_str_pad('PHP', 8, '.') : null,
            'interview_point' => '多字节字符串处理要优先使用 mb_* API，避免中文长度和截断错误。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function php84PropertyHookExample(): array
    {
        return $this->evaluateArray($this->php84PropertyHookSnippet());
    }

    /**
     * @return array<string, mixed>
     */
    private function php85PipeOperatorExample(): array
    {
        return $this->evaluateArray($this->php85PipeOperatorSnippet());
    }

    /**
     * @return array<string, mixed>
     */
    private function php85CloneWithExample(): array
    {
        return $this->evaluateArray($this->php85CloneWithSnippet());
    }

    private function normalizeTitle(string $title): string
    {
        return strtolower(str_replace(' ', '-', trim($title)));
    }

    /**
     * @return array<string, mixed>
     */
    private function renderSerializablePayload(\Stringable&JsonSerializable $payload): array
    {
        return [
            'string' => (string) $payload,
            'json' => $payload->jsonSerialize(),
            'interview_point' => 'Intersection type 要求对象同时满足多个接口，比运行时 if 判断更明确。',
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function runWhenAvailable(int $versionId, callable $callback, array $fallback): array
    {
        if ($versionId > PHP_VERSION_ID) {
            return $fallback;
        }

        try {
            return $callback();
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'error' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function versionStatus(int $versionId): string
    {
        if ($versionId <= PHP_VERSION_ID) {
            return 'available on current runtime';
        }

        return 'requires PHP '.$this->versionLabel($versionId);
    }

    private function versionLabel(int $versionId): string
    {
        return intdiv($versionId, 10000).'.'.(intdiv($versionId, 100) % 100);
    }

    /**
     * @param  list<string>  $features
     * @return array<string, mixed>
     */
    private function plannedFeatureList(int $versionId, array $features): array
    {
        return [
            'status' => 'requires PHP '.$this->versionLabel($versionId),
            'features' => $features,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateArray(string $code, array $variables = []): array
    {
        extract($variables, EXTR_SKIP);

        $result = eval($code);

        return is_array($result) ? $result : ['result' => $result];
    }

    private function php84PropertyHookSnippet(): string
    {
        return <<<'PHP'
return (static function (): array {
    $person = new class {
        public string $firstName {
            set => ucfirst(strtolower($value));
        }

        public string $lastName {
            set {
                if (strlen($value) < 2) {
                    throw new \InvalidArgumentException('Too short');
                }

                $this->lastName = $value;
            }
        }

        public string $fullName {
            get => $this->firstName.' '.$this->lastName;
        }
    };

    $person->firstName = 'ada';
    $person->lastName = 'Lovelace';

    return [
        'full_name' => $person->fullName,
        'interview_point' => 'Property hooks 可把简单属性读写规则贴近属性本身，但复杂业务规则仍应放在领域方法。',
    ];
})();
PHP;
    }

    private function php85PipeOperatorSnippet(): string
    {
        return <<<'PHP'
$title = ' PHP 8.5 Released ';
$slug = $title
    |> trim(...)
    |> (fn (string $value): string => str_replace(' ', '-', $value))
    |> (fn (string $value): string => str_replace('.', '', $value))
    |> strtolower(...);

return [
    'slug' => $slug,
    'interview_point' => 'Pipe operator 让多步数据转换按阅读顺序表达，减少临时变量和嵌套调用。',
];
PHP;
    }

    private function php85CloneWithSnippet(): string
    {
        return <<<'PHP'
$color = new readonly class(79, 91, 147) {
    public function __construct(
        public int $red,
        public int $green,
        public int $blue,
        public int $alpha = 255,
    ) {
    }

    public function withAlpha(int $alpha): self
    {
        return clone($this, ['alpha' => $alpha]);
    }
};

$transparent = $color->withAlpha(128);

return [
    'original_alpha' => $color->alpha,
    'transparent_alpha' => $transparent->alpha,
    'interview_point' => 'clone with 让 readonly 对象的 with-er 模式更轻量。',
];
PHP;
    }
}

enum PhpFeatureOrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待支付',
            self::Paid => '已支付',
            self::Cancelled => '已取消',
        };
    }
}

final class Php81FeatureDto
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $topic,
        public readonly array $tags,
    ) {}
}

final class PhpFeaturePayload implements \Stringable, JsonSerializable
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        private readonly string $topic,
        private readonly array $tags,
    ) {}

    public function __toString(): string
    {
        return $this->topic;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'topic' => $this->topic,
            'tags' => $this->tags,
        ];
    }
}

class PhpFeatureBaseRenderer
{
    public function render(): string
    {
        return 'base';
    }
}
