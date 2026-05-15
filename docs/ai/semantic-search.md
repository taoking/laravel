# 指标语义搜索与 AI 加分模块

本文对应 P3-05 语义搜索和 AI 加分模块。目标是在不依赖外部密钥的前提下，提供一个可运行、可测试、可面试讲解的语义检索入口，并说明后续替换为真实 embedding/vector store 的边界。

## 代码入口

| 类型 | 路径 |
| --- | --- |
| API | `GET /api/v1/metrics/semantic-search?q=income%20sales` |
| Controller | `app/Http/Controllers/Api/V1/Metrics/SemanticMetricSearchController.php` |
| Service | `app/Domains/Metrics/Services/SemanticMetricSearchService.php` |
| OpenAPI | `public/docs/openapi.yaml` |
| Test | `tests/Feature/PhaseEighteenSemanticSearchTest.php` |

## 当前实现

当前版本使用本地 `local-token-vector` 引擎：

1. 将查询词和指标文档统一分词。
2. 指标文档包含 `code`、`name`、`description`、分类编码、分类名称和分类描述。
3. 对业务常见词做同义词扩展，例如 `income`、`sales` 可召回 `revenue`，`people`、`activity` 可召回 `active users`。
4. 使用余弦相似度排序，并对完整短语命中增加轻量加分。
5. 返回 `metric`、`score`、`matched_terms` 和 `engine`。

这个实现不是替代专业向量库，而是用于本项目面试演示：

- 不需要 OpenAI、云厂商或私有模型密钥。
- CI 和本地测试稳定可运行。
- 能展示“召回、排序、降级、成本和边界”的架构思路。

## API 示例

```http
GET /api/v1/metrics/semantic-search?q=income%20sales&limit=3
```

响应核心结构：

```json
{
  "success": true,
  "data": {
    "query": "income sales",
    "results": [
      {
        "metric": {
          "code": "revenue_amount",
          "name": "Revenue Amount"
        },
        "score": 0.6214,
        "matched_terms": ["revenue", "amount", "finance"],
        "engine": "local-token-vector"
      }
    ]
  },
  "trace_id": "..."
}
```

## 生产演进路径

| 阶段 | 方案 | 适用场景 |
| --- | --- | --- |
| 当前 | 本地 token vector + 同义词 | 学习项目、CI、无密钥演示 |
| 第二阶段 | 数据库持久化 embedding 字段 | 指标数量增长，需要避免每次全表计算 |
| 第三阶段 | pgvector、Milvus、Elasticsearch kNN | 需要高性能向量召回和过滤 |
| 第四阶段 | Laravel AI SDK / 外部 embedding 服务 | 需要跨语言语义理解、自然语言问答和 RAG |

替换为真实 embedding 时，建议把 `SemanticMetricSearchService` 抽成接口：

- `LocalTokenMetricSearchEngine`
- `VectorMetricSearchEngine`
- `AiMetricSearchEngine`

Controller 不直接依赖具体模型供应商，只依赖统一搜索服务。

## 生产风险

- 全表实时向量计算不适合大数据量；当前实现只适合学习和小数据演示。
- 同义词表需要业务维护，否则容易召回不准。
- 外部 embedding 服务会引入成本、延迟、限流、密钥管理和数据合规问题。
- 真正的语义检索通常需要“向量召回 + 权限过滤 + 关键词兜底 + rerank”，不能只靠一个相似度分数。
- 如果指标存在租户隔离或数据范围，向量检索必须在召回后再次做权限过滤。

## 验收命令

```bash
php artisan test --filter=PhaseEighteenSemanticSearchTest
php artisan test --filter=PhaseTwelveOpenApiContractTest
composer analyse
```

当前验收结果：

- PhaseEighteenSemanticSearchTest 通过：4 个测试、16 个断言。
- PhaseTwelveOpenApiContractTest 通过：2 个测试、45 个断言。
- `composer analyse` 通过。

## 面试追问

基础问题：

1. 语义搜索和普通 `LIKE` 搜索有什么区别？
2. 为什么测试环境不能依赖真实外部 AI 密钥？
3. 当前 `local-token-vector` 做了哪些降级？
4. `score` 是否能直接作为业务排序唯一标准？
5. OpenAPI 为什么要记录搜索引擎类型？

资深追问：

1. 指标数量从 100 条增长到 100 万条时，当前实现哪里会先出问题？
2. 向量召回后如何保证 RBAC、租户隔离和数据范围？
3. embedding 更新失败时，如何保证搜索结果和数据库一致？
4. 使用外部 AI 服务时，如何控制成本、限流和密钥泄露风险？
5. 为什么生产搜索通常需要关键词兜底和 rerank？
