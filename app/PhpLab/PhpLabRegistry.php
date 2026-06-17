<?php

namespace App\PhpLab;

final class PhpLabRegistry
{
    /**
     * @return array<int, array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }>
     */
    public function all(): array
    {
        return [
            $this->arrayTransform(),
            $this->arrayFilterSearch(),
            $this->arrayAggregateSort(),
            $this->stringCleanFormat(),
            $this->stringSearchSplit(),
            $this->typeValidateConvert(),
            $this->jsonUrl(),
            $this->datePath(),
        ];
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }>
     */
    public function keyed(): array
    {
        $topics = [];

        foreach ($this->all() as $topic) {
            $topics[$topic['key']] = $topic;
        }

        return $topics;
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }|null
     */
    public function find(string $key): ?array
    {
        $key = str_replace('_', '-', strtolower(trim($key)));

        return $this->keyed()[$key] ?? null;
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function arrayTransform(): array
    {
        $orders = [
            ['id' => 1001, 'user' => ' Tao ', 'amount' => 199.9],
            ['id' => 1002, 'user' => ' Lin ', 'amount' => 88],
        ];

        $names = array_map(fn (array $order): string => trim($order['user']), $orders);

        array_walk($orders, function (array &$order): void {
            $order['user'] = trim($order['user']);
            $order['amount_label'] = number_format($order['amount'], 2, '.', '');
        });

        $amountsById = array_combine(
            array_column($orders, 'id'),
            array_column($orders, 'amount_label'),
        );

        return [
            'key' => 'arrays-transform',
            'title' => '数组遍历与转换函数',
            'level' => '高频',
            'summary' => '重点练 array_map、array_walk、array_column 这类日常数据整理函数。',
            'methods' => [
                'array_map()：返回新数组，适合纯转换，不应该依赖副作用。',
                'array_walk()：遍历数组并可原地修改元素，适合补字段、清洗字段。',
                'array_column()：从二维数组取一列，常用于取 id、code、name。',
                'array_combine()：把两个数组组合成 key => value 映射。',
                'array_keys() / array_values()：取键名、取值并重新整理索引。',
            ],
            'concepts' => [
                '要改原数组时用 array_walk，并让回调参数按引用传入。',
                '只做映射转换时用 array_map，更容易保持输入和输出边界清楚。',
                '二维数组整理时，array_column 通常比 foreach 临时变量更简洁。',
            ],
            'code' => <<<'PHP'
$orders = [
    ['id' => 1001, 'user' => ' Tao ', 'amount' => 199.9],
    ['id' => 1002, 'user' => ' Lin ', 'amount' => 88],
];

$names = array_map(fn (array $order): string => trim($order['user']), $orders);

array_walk($orders, function (array &$order): void {
    $order['user'] = trim($order['user']);
    $order['amount_label'] = number_format($order['amount'], 2, '.', '');
});

$amountsById = array_combine(
    array_column($orders, 'id'),
    array_column($orders, 'amount_label'),
);
PHP,
            'output' => [
                'names='.implode(', ', $names),
                'first_user='.$orders[0]['user'],
                'amount_1002='.$amountsById[1002],
            ],
            'practice' => [
                '用 array_map 把用户列表转换成只包含 id、name、email 的公开 DTO。',
                '用 array_walk 给订单数组补充 amount_label、status_label 两个展示字段。',
                '用 array_column + array_combine 把分类数组整理成 id => name 的下拉选项。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function arrayFilterSearch(): array
    {
        $users = [
            ['id' => 1, 'name' => 'Tao', 'role' => 'admin', 'enabled' => true],
            ['id' => 2, 'name' => 'Lin', 'role' => 'editor', 'enabled' => true],
            ['id' => 3, 'name' => 'Ning', 'role' => 'viewer', 'enabled' => false],
        ];
        $requiredRoles = ['admin', 'editor', 'analyst'];
        $roles = array_column($users, 'role');
        $enabledUsers = array_filter(
            $users,
            fn (array $user): bool => $user['enabled'] && in_array($user['role'], $requiredRoles, true),
        );
        $missingRoles = array_diff($requiredRoles, array_unique($roles));
        $firstEditorIndex = array_search('editor', $roles, true);
        $firstUserHasEmailKey = array_key_exists('email', $users[0]) ? 'yes' : 'no';

        return [
            'key' => 'arrays-filter-search',
            'title' => '数组筛选与查找函数',
            'level' => '高频',
            'summary' => '整理 array_filter、in_array、array_search、array_diff 等集合判断函数。',
            'methods' => [
                'array_filter()：按条件筛选数组，注意会保留原 key。',
                'in_array()：判断值是否存在，第三个参数建议传 true 开启严格比较。',
                'array_search()：查找值所在 key，返回值可能是 0，判断时要用 !== false。',
                'array_key_exists() / isset()：判断 key 是否存在；isset 对 null 返回 false。',
                'array_unique()：去重，常用于角色、标签、状态集合。',
                'array_diff() / array_intersect()：求差集、交集，适合权限和标签比对。',
            ],
            'concepts' => [
                '筛选后如果要返回 JSON 列表，通常需要 array_values() 重排索引。',
                '查找函数返回 0 时容易被当成 false，这是 PHP 常见坑。',
                '权限、状态、标签这类集合判断要优先开启严格比较。',
            ],
            'code' => <<<'PHP'
$users = [
    ['id' => 1, 'name' => 'Tao', 'role' => 'admin', 'enabled' => true],
    ['id' => 2, 'name' => 'Lin', 'role' => 'editor', 'enabled' => true],
    ['id' => 3, 'name' => 'Ning', 'role' => 'viewer', 'enabled' => false],
];

$requiredRoles = ['admin', 'editor', 'analyst'];
$roles = array_column($users, 'role');

$enabledUsers = array_filter(
    $users,
    fn (array $user): bool => $user['enabled'] && in_array($user['role'], $requiredRoles, true),
);

$missingRoles = array_diff($requiredRoles, array_unique($roles));
$firstEditorIndex = array_search('editor', $roles, true);
$firstUserHasEmailKey = array_key_exists('email', $users[0]) ? 'yes' : 'no';
PHP,
            'output' => [
                'enabled_ids='.implode(', ', array_column($enabledUsers, 'id')),
                'missing_roles='.implode(', ', $missingRoles),
                'first_editor_index='.(string) $firstEditorIndex,
                'first_user_has_email_key='.$firstUserHasEmailKey,
            ],
            'practice' => [
                '用 array_filter + array_values 输出启用用户列表，保证 JSON 是数组不是对象。',
                '用 in_array(..., true) 判断请求状态是否在白名单内。',
                '用 array_diff 找出用户缺少的权限编码。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function arrayAggregateSort(): array
    {
        $items = [
            ['name' => 'cache', 'score' => 80, 'weight' => 2],
            ['name' => 'queue', 'score' => 95, 'weight' => 3],
            ['name' => 'database', 'score' => 88, 'weight' => 4],
        ];
        $weightedTotal = array_reduce(
            $items,
            fn (int $carry, array $item): int => $carry + $item['score'] * $item['weight'],
            0,
        );
        $scoreSum = array_sum(array_column($items, 'score'));

        usort($items, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        $config = array_replace(
            ['timeout' => 3, 'retries' => 1, 'trace' => false],
            ['retries' => 2, 'trace' => true],
        );

        return [
            'key' => 'arrays-aggregate-sort',
            'title' => '数组聚合、合并与排序函数',
            'level' => '高频',
            'summary' => '练 array_reduce、array_sum、array_merge/replace、sort/usort 等常用整理函数。',
            'methods' => [
                'array_reduce()：把数组折叠成一个结果，适合复杂汇总。',
                'array_sum() / count()：快速求和、计数，常配合 array_column。',
                'array_merge()：合并数组；字符串 key 后者覆盖，数字 key 重新编号。',
                'array_replace()：按 key 覆盖，更适合默认配置被用户配置覆盖。',
                'sort() / asort() / ksort() / usort()：按值、保留 key、按 key、自定义排序。',
                'array_multisort()：多列排序时常用，但可读性要谨慎控制。',
            ],
            'concepts' => [
                '配置覆盖优先考虑 array_replace，避免数字 key 被 array_merge 重排带来误解。',
                '业务对象排序通常用 usort 和太空船操作符 <=>。',
                'array_reduce 适合复杂聚合，但简单求和用 array_sum 更直接。',
            ],
            'code' => <<<'PHP'
$items = [
    ['name' => 'cache', 'score' => 80, 'weight' => 2],
    ['name' => 'queue', 'score' => 95, 'weight' => 3],
    ['name' => 'database', 'score' => 88, 'weight' => 4],
];

$weightedTotal = array_reduce(
    $items,
    fn (int $carry, array $item): int => $carry + $item['score'] * $item['weight'],
    0,
);
$scoreSum = array_sum(array_column($items, 'score'));

usort($items, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

$config = array_replace(
    ['timeout' => 3, 'retries' => 1, 'trace' => false],
    ['retries' => 2, 'trace' => true],
);
PHP,
            'output' => [
                'weighted_total='.$weightedTotal,
                'score_sum='.$scoreSum,
                'top_item='.$items[0]['name'],
                'config_retries='.$config['retries'],
            ],
            'practice' => [
                '用 array_reduce 汇总订单总金额、最大金额和订单数。',
                '用 usort 按 created_at 倒序排列任务列表。',
                '用 array_replace 实现默认查询条件和请求查询条件合并。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function stringCleanFormat(): array
    {
        $rawCode = '  revenue amount  ';
        $code = strtoupper(str_replace(' ', '_', trim($rawCode)));
        $title = ucwords(str_replace('_', ' ', strtolower('MONTHLY_REVENUE')));
        $orderNo = str_pad('42', 6, '0', STR_PAD_LEFT);
        $message = sprintf('%s: %s', $orderNo, number_format(1288.5, 2, '.', ','));

        return [
            'key' => 'strings-clean-format',
            'title' => '字符串清理与格式化函数',
            'level' => '高频',
            'summary' => '整理 trim、大小写转换、sprintf、number_format、str_pad 等展示层常用函数。',
            'methods' => [
                'trim() / ltrim() / rtrim()：清理两侧、左侧、右侧空白或指定字符。',
                'strtolower() / strtoupper()：英文大小写转换；多字节文本看 mb_strtolower。',
                'ucfirst() / ucwords()：首字母、每个单词首字母大写。',
                'sprintf()：固定格式字符串，适合日志、编号、提示文案。',
                'number_format()：金额、百分比、统计数值展示格式化。',
                'str_pad()：左补零、右补空格，常用于编号对齐。',
            ],
            'concepts' => [
                '输入清洗通常先 trim，再做格式转换或校验。',
                '展示格式和存储值要分开，金额不要直接存 number_format 后的字符串。',
                '处理中文长度和截取时优先考虑 mb_* 系列函数。',
            ],
            'code' => <<<'PHP'
$rawCode = '  revenue amount  ';
$code = strtoupper(str_replace(' ', '_', trim($rawCode)));

$title = ucwords(str_replace('_', ' ', strtolower('MONTHLY_REVENUE')));
$orderNo = str_pad('42', 6, '0', STR_PAD_LEFT);
$message = sprintf('%s: %s', $orderNo, number_format(1288.5, 2, '.', ','));
PHP,
            'output' => [
                'code='.$code,
                'title='.$title,
                'message='.$message,
            ],
            'practice' => [
                '把用户输入的指标名称整理成大写下划线编码。',
                '用 sprintf + str_pad 生成固定长度的订单号。',
                '用 number_format 生成金额展示文案，但保留原始 float/int 用于计算。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function stringSearchSplit(): array
    {
        $email = 'tao@example.com';
        $path = '/api/v1/metrics/revenue';
        $tags = ' php, array , string ';
        $domain = substr($email, strpos($email, '@') + 1);
        $segments = explode('/', trim($path, '/'));
        $cleanTags = array_map('trim', explode(',', $tags));
        $routeName = str_replace('/', '.', trim($path, '/'));
        $isMetricCode = preg_match('/^metric_[a-z_]+$/', 'metric_revenue_amount') === 1;

        return [
            'key' => 'strings-search-split',
            'title' => '字符串查找、替换与拆分函数',
            'level' => '高频',
            'summary' => '整理 str_contains、strpos、substr、explode、implode、str_replace 和 preg_*。',
            'methods' => [
                'str_contains() / str_starts_with() / str_ends_with()：语义化判断包含、前缀、后缀。',
                'strpos()：查找位置，返回 0 时要用 !== false 判断。',
                'substr() / strlen()：截取和长度；中文内容优先考虑 mb_substr、mb_strlen。',
                'explode() / implode()：字符串和数组之间转换。',
                'str_replace()：简单替换，适合固定字符或固定片段。',
                'preg_match() / preg_replace()：复杂模式匹配和替换。',
            ],
            'concepts' => [
                '只判断是否包含时优先用 str_contains，可读性比 strpos 更直接。',
                '拆分用户输入后通常要 array_map("trim", $items) 再过滤空值。',
                '正则适合复杂规则，但简单替换不要过早使用 preg_replace。',
            ],
            'code' => <<<'PHP'
$email = 'tao@example.com';
$path = '/api/v1/metrics/revenue';
$tags = ' php, array , string ';

$domain = substr($email, strpos($email, '@') + 1);
$segments = explode('/', trim($path, '/'));
$cleanTags = array_map('trim', explode(',', $tags));
$routeName = str_replace('/', '.', trim($path, '/'));
$isMetricCode = preg_match('/^metric_[a-z_]+$/', 'metric_revenue_amount') === 1;
PHP,
            'output' => [
                'domain='.$domain,
                'segments='.implode(' > ', $segments),
                'tags='.implode('|', $cleanTags),
                'route='.$routeName,
                'is_metric_code='.($isMetricCode ? 'yes' : 'no'),
            ],
            'practice' => [
                '用 explode + array_map("trim", ...) 解析逗号分隔的标签输入。',
                '用 str_contains / str_ends_with 判断上传文件名是否符合规则。',
                '用 preg_match 校验 metric_xxx 格式的指标编码。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function typeValidateConvert(): array
    {
        $input = [
            'page' => '2',
            'email' => ' tao@example.com ',
            'active' => '1',
            'limit' => null,
        ];
        $page = filter_var($input['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL) ?: 'invalid';
        $active = filter_var($input['active'], FILTER_VALIDATE_BOOLEAN);
        $limit = isset($input['limit']) ? (int) $input['limit'] : 20;

        return [
            'key' => 'type-validate-convert',
            'title' => '类型判断、过滤与转换函数',
            'level' => '常用',
            'summary' => '整理 is_*、filter_var、intval/floatval/boolval、isset/empty 等输入处理函数。',
            'methods' => [
                'is_int() / is_string() / is_array() / is_numeric()：基础类型判断。',
                'get_debug_type()：调试时查看变量真实类型，比 gettype 输出更贴近现代 PHP。',
                'filter_var()：校验 email、url、int、boolean 等输入。',
                'intval() / floatval() / boolval()：显式基础类型转换。',
                'isset()：判断变量或数组 key 存在且不为 null。',
                'empty()：判断空值，但 "0" 也会被认为 empty，业务判断要谨慎。',
            ],
            'concepts' => [
                '外部输入先过滤和转换，再进入业务逻辑。',
                'filter_var 校验失败可能返回 false，和合法值 0 要区分清楚。',
                'isset 与 array_key_exists 在 null 字段上的结果不同。',
            ],
            'code' => <<<'PHP'
$input = [
    'page' => '2',
    'email' => ' tao@example.com ',
    'active' => '1',
    'limit' => null,
];

$page = filter_var($input['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL) ?: 'invalid';
$active = filter_var($input['active'], FILTER_VALIDATE_BOOLEAN);
$limit = isset($input['limit']) ? (int) $input['limit'] : 20;
PHP,
            'output' => [
                'page='.$page,
                'email='.$email,
                'active='.($active ? 'true' : 'false'),
                'limit='.$limit,
            ],
            'practice' => [
                '用 filter_var 校验 email、url、page 三个请求字段。',
                '比较 isset($data["x"]) 和 array_key_exists("x", $data) 在 null 值上的差异。',
                '写一组 empty 判断样例，观察 0、"0"、""、[]、null 的结果。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function jsonUrl(): array
    {
        $payload = ['metric' => 'revenue', 'filters' => ['region' => 'cn', 'page' => 2]];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $query = http_build_query(['q' => 'PHP array_map', 'page' => 2]);
        $url = 'https://example.com/search?'.$query;
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);

        return [
            'key' => 'json-url',
            'title' => 'JSON、URL 与查询字符串函数',
            'level' => '常用',
            'summary' => '整理 json_encode/json_decode、http_build_query、parse_url、parse_str 等接口常用函数。',
            'methods' => [
                'json_encode()：数组或对象转 JSON，接口输出常用。',
                'json_decode()：JSON 转数组或对象，建议配合 JSON_THROW_ON_ERROR。',
                'http_build_query()：数组生成 query string。',
                'parse_url()：拆 URL 的 scheme、host、path、query。',
                'parse_str()：把 query string 解析为数组。',
                'urlencode() / rawurlencode()：URL 参数编码，rawurlencode 更符合 RFC 3986。',
            ],
            'concepts' => [
                'JSON 解析失败不要静默吞掉，JSON_THROW_ON_ERROR 更适合后端接口。',
                'query string 不建议手拼，http_build_query 能处理编码细节。',
                'parse_str 会写入传入的数组变量，避免不传第二个参数。',
            ],
            'code' => <<<'PHP'
$payload = ['metric' => 'revenue', 'filters' => ['region' => 'cn', 'page' => 2]];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

$query = http_build_query(['q' => 'PHP array_map', 'page' => 2]);
$url = 'https://example.com/search?'.$query;
parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);
PHP,
            'output' => [
                'json='.$json,
                'metric='.$decoded['metric'],
                'query='.$query,
                'q='.$params['q'],
            ],
            'practice' => [
                '用 JSON_THROW_ON_ERROR 解析一段接口响应，并捕获异常。',
                '用 http_build_query 生成搜索 URL 的查询参数。',
                '用 parse_url + parse_str 从回调 URL 中取出 code 和 state。',
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     level: string,
     *     summary: string,
     *     methods: array<int, string>,
     *     concepts: array<int, string>,
     *     code: string,
     *     output: array<int, string>,
     *     practice: array<int, string>
     * }
     */
    private function datePath(): array
    {
        $now = new \DateTimeImmutable('2026-06-17 09:30:00');
        $nextWeek = $now->modify('+7 days');
        $days = $now->diff($nextWeek)->days;
        $path = '/var/app/storage/reports/monthly.csv';
        $info = pathinfo($path);

        return [
            'key' => 'date-path',
            'title' => '日期时间与文件路径函数',
            'level' => '常用',
            'summary' => '整理 DateTimeImmutable、strtotime/date、basename/dirname/pathinfo 等日常工具函数。',
            'methods' => [
                'DateTimeImmutable：不可变日期对象，适合业务代码避免意外修改原值。',
                'format() / modify() / diff()：格式化、偏移、计算日期差。',
                'strtotime() / date()：老代码常见，简单场景可读性尚可。',
                'basename() / dirname()：取文件名、目录名。',
                'pathinfo()：一次性取 dirname、basename、extension、filename。',
                'file_exists() / is_file() / is_dir()：文件和目录存在性判断。',
            ],
            'concepts' => [
                '新业务日期逻辑优先使用 DateTimeImmutable，避免原对象被 modify 改掉。',
                '路径拆解用 pathinfo 比手写 explode 更稳。',
                '文件存在性判断要区分路径存在、普通文件、目录三种语义。',
            ],
            'code' => <<<'PHP'
$now = new DateTimeImmutable('2026-06-17 09:30:00');
$nextWeek = $now->modify('+7 days');
$days = $now->diff($nextWeek)->days;

$path = '/var/app/storage/reports/monthly.csv';
$info = pathinfo($path);
PHP,
            'output' => [
                'today='.$now->format('Y-m-d'),
                'next_week='.$nextWeek->format('Y-m-d'),
                'days='.$days,
                'basename='.$info['basename'],
                'extension='.$info['extension'],
            ],
            'practice' => [
                '用 DateTimeImmutable 计算本月第一天和下月第一天。',
                '用 diff 计算任务截止日期距离今天还有几天。',
                '用 pathinfo 校验上传文件扩展名并生成新文件名。',
            ],
        ];
    }
}
