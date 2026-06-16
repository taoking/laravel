<?php

namespace App\Http\Controllers;

use App\PatternLab\PatternRegistry;
use Illuminate\Contracts\View\View;

class PatternLabController extends Controller
{
    public function __construct(
        private readonly PatternRegistry $patterns,
    ) {}

    public function index(): View
    {
        return view('pattern-lab.index', [
            'categories' => $this->patterns->categories(),
            'patterns' => $this->patterns->all(),
        ]);
    }

    public function category(string $category): View
    {
        $category = $this->normalizeKey($category);
        $categories = $this->patterns->categories();

        abort_unless(array_key_exists($category, $categories), 404, 'PatternLab category not found.');

        return view('pattern-lab.category', [
            'category' => $categories[$category],
            'categories' => $categories,
            'patterns' => $this->patterns->byCategory($category),
        ]);
    }

    public function show(string $category, string $pattern): View
    {
        $category = $this->normalizeKey($category);
        $pattern = $this->patterns->find($pattern);

        abort_if($pattern === null || $pattern['category'] !== $category, 404, 'PatternLab pattern not found.');

        return view('pattern-lab.show', [
            'categories' => $this->patterns->categories(),
            'pattern' => $pattern,
        ]);
    }

    private function normalizeKey(string $key): string
    {
        return str_replace('_', '-', strtolower(trim($key)));
    }
}
