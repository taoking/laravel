<?php

namespace App\PatternLab\Support;

final readonly class PatternDefinition
{
    /**
     * @param  array<int, string>  $commonUsage
     * @param  array<int, string>  $exerciseGoals
     * @param  array<int, string>  $exerciseContentRequirements
     * @param  array<int, string>  $suggestedClasses
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $nameCn,
        public string $category,
        public string $categoryCn,
        public string $difficulty,
        public string $status,
        public string $summary,
        public string $scenario,
        public array $commonUsage,
        public array $exerciseGoals,
        public string $exerciseContentTitle,
        public string $exerciseContentDescription,
        public array $exerciseContentRequirements,
        public array $suggestedClasses,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(array $definition): self
    {
        $exerciseContent = is_array($definition['exercise_content'] ?? null)
            ? $definition['exercise_content']
            : [];

        return new self(
            key: (string) $definition['key'],
            name: (string) $definition['name'],
            nameCn: (string) $definition['name_cn'],
            category: (string) $definition['category'],
            categoryCn: (string) $definition['category_cn'],
            difficulty: (string) $definition['difficulty'],
            status: (string) $definition['status'],
            summary: (string) $definition['summary'],
            scenario: (string) $definition['scenario'],
            commonUsage: self::stringList($definition['common_usage'] ?? []),
            exerciseGoals: self::stringList($definition['exercise_goals'] ?? []),
            exerciseContentTitle: (string) ($exerciseContent['title'] ?? ''),
            exerciseContentDescription: (string) ($exerciseContent['description'] ?? ''),
            exerciseContentRequirements: self::stringList($exerciseContent['requirements'] ?? []),
            suggestedClasses: self::stringList($definition['suggested_classes'] ?? []),
        );
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map('strval', $value));
    }
}
