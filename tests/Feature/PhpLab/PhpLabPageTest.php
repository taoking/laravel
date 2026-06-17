<?php

namespace Tests\Feature\PhpLab;

use Tests\TestCase;

class PhpLabPageTest extends TestCase
{
    public function test_php_lab_index_page_lists_practice_topics(): void
    {
        $this->get('/php-lab')
            ->assertOk()
            ->assertSee('PHP 常用函数练习实验室')
            ->assertSee('数组遍历与转换函数')
            ->assertSee('字符串清理与格式化函数')
            ->assertSee('JSON、URL 与查询字符串函数');
    }

    public function test_php_lab_topic_page_shows_code_output_and_practice_tasks(): void
    {
        $this->get('/php-lab/arrays-transform')
            ->assertOk()
            ->assertSee('数组遍历与转换函数')
            ->assertSee('array_walk')
            ->assertSee('array_map')
            ->assertSee('amount_1002=88.00')
            ->assertSee('用 array_walk 给订单数组补充 amount_label、status_label 两个展示字段');
    }

    public function test_php_lab_string_topic_lists_common_string_functions(): void
    {
        $this->get('/php-lab/strings-search-split')
            ->assertOk()
            ->assertSee('字符串查找、替换与拆分函数')
            ->assertSee('str_contains')
            ->assertSee('explode')
            ->assertSee('preg_match')
            ->assertSee('tags=php|array|string');
    }

    public function test_php_lab_topic_page_returns_404_for_unknown_topic(): void
    {
        $this->get('/php-lab/missing-topic')
            ->assertNotFound();
    }
}
