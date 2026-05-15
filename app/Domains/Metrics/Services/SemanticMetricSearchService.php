<?php

namespace App\Domains\Metrics\Services;

use App\Domains\Metrics\Models\Metric;
use Illuminate\Support\Collection;

class SemanticMetricSearchService
{
    private const SYNONYMS = [
        'income' => ['revenue', 'amount', 'finance'],
        'sales' => ['revenue', 'business'],
        'money' => ['revenue', 'finance'],
        'customer' => ['users', 'active'],
        'customers' => ['users', 'active'],
        'people' => ['users', 'active'],
        'activity' => ['active', 'users'],
        'usage' => ['active', 'users'],
    ];

    /**
     * @return list<array{metric: Metric, score: float, matched_terms: list<string>}>
     */
    public function search(string $query, int $limit = 10): array
    {
        $queryVector = $this->vectorize($query);

        if ($queryVector === []) {
            return [];
        }

        $queryText = mb_strtolower($query);

        /** @var Collection<int, Metric> $metrics */
        $metrics = Metric::query()
            ->with(['category', 'latestValue.region', 'latestValue.frequency'])
            ->where('status', 'active')
            ->get();

        $results = $metrics
            ->map(function (Metric $metric) use ($queryText, $queryVector): ?array {
                $document = $this->document($metric);
                $documentVector = $this->vectorize($document);
                $score = $this->cosine($queryVector, $documentVector);

                if (str_contains(mb_strtolower($document), $queryText)) {
                    $score += 0.35;
                }

                if ($score <= 0.0) {
                    return null;
                }

                $matchedTerms = array_values(array_intersect(array_keys($queryVector), array_keys($documentVector)));

                return [
                    'metric' => $metric,
                    'score' => round($score, 4),
                    'matched_terms' => $matchedTerms,
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->values()
            ->take($limit);

        return $results->all();
    }

    private function document(Metric $metric): string
    {
        return implode(' ', array_filter([
            $metric->code,
            str_replace('_', ' ', $metric->code),
            $metric->name,
            $metric->description,
            $metric->category?->code,
            $metric->category?->name,
            $metric->category?->description,
        ]));
    }

    /**
     * @return array<string, float>
     */
    private function vectorize(string $text): array
    {
        $tokens = $this->tokens($text);
        $vector = [];

        foreach ($tokens as $token) {
            $vector[$token] = ($vector[$token] ?? 0.0) + 1.0;

            foreach (self::SYNONYMS[$token] ?? [] as $synonym) {
                $vector[$synonym] = ($vector[$synonym] ?? 0.0) + 0.6;
            }
        }

        return $vector;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $normalized = mb_strtolower(str_replace(['_', '-'], ' ', $text));
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [];

        return array_values(array_filter(
            array_map('trim', $tokens),
            fn (string $token): bool => mb_strlen($token) >= 2,
        ));
    }

    /**
     * @param  array<string, float>  $left
     * @param  array<string, float>  $right
     */
    private function cosine(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $dot = 0.0;

        foreach ($left as $term => $weight) {
            $dot += $weight * ($right[$term] ?? 0.0);
        }

        $leftNorm = sqrt(array_sum(array_map(fn (float $weight): float => $weight ** 2, $left)));
        $rightNorm = sqrt(array_sum(array_map(fn (float $weight): float => $weight ** 2, $right)));

        if ($leftNorm == 0.0 || $rightNorm == 0.0) {
            return 0.0;
        }

        return $dot / ($leftNorm * $rightNorm);
    }
}
