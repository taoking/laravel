<?php

namespace Tests\Feature\PhpLab;

use Tests\TestCase;

class PhpLabPageTest extends TestCase
{
    public function test_php_lab_index_page_lists_practice_topics(): void
    {
        $this->get('/php-lab')
            ->assertOk()
            ->assertSee('PHP 基础练习实验室')
            ->assertSee('变量与基础类型')
            ->assertSee('数组与常用数组函数')
            ->assertSee('类、对象、接口与枚举');
    }

    public function test_php_lab_topic_page_shows_code_output_and_practice_tasks(): void
    {
        $this->get('/php-lab/arrays')
            ->assertOk()
            ->assertSee('数组与常用数组函数')
            ->assertSee('array_filter')
            ->assertSee('passed=function, object')
            ->assertSee('从商品数组中过滤出库存大于 0 的商品');
    }

    public function test_php_lab_topic_page_returns_404_for_unknown_topic(): void
    {
        $this->get('/php-lab/missing-topic')
            ->assertNotFound();
    }
}
