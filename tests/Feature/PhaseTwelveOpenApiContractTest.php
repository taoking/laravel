<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PhaseTwelveOpenApiContractTest extends TestCase
{
    public function test_openapi_documents_all_api_v1_routes(): void
    {
        $yaml = $this->openApiYaml();
        $documentedPaths = $this->documentedPaths($yaml);

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1')) {
                continue;
            }

            $this->assertContains('/'.$uri, $documentedPaths, "OpenAPI is missing /{$uri}");
        }

        $this->assertContains('/login', $documentedPaths);
        $this->assertContains('/logout', $documentedPaths);
    }

    public function test_openapi_uses_chinese_descriptions_and_common_error_examples(): void
    {
        $yaml = $this->openApiYaml();

        $this->assertStringContainsString('summary: 用户登录', $yaml);
        $this->assertStringContainsString('summary: 指标列表', $yaml);
        $this->assertStringContainsString('summary: 创建 CSV 导入任务', $yaml);
        $this->assertStringContainsString('未登录', $yaml);
        $this->assertStringContainsString('无权限', $yaml);
        $this->assertStringContainsString('字段验证失败', $yaml);
        $this->assertStringContainsString('Idempotency-Key', $yaml);
        $this->assertStringContainsString('trace_id', $yaml);
    }

    private function openApiYaml(): string
    {
        return (string) file_get_contents(public_path('docs/openapi.yaml'));
    }

    /**
     * @return list<string>
     */
    private function documentedPaths(string $yaml): array
    {
        preg_match_all('/^  (\/[^:]+):$/m', $yaml, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
