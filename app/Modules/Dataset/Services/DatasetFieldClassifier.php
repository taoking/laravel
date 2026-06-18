<?php

namespace App\Modules\Dataset\Services;

class DatasetFieldClassifier
{
    /**
     * @return array{semantic_type: string, is_dimension: bool, is_metric: bool, default_aggregate: string}
     */
    public function classify(string $fieldName, string $normalizedType): array
    {
        $lowerName = strtolower($fieldName);

        if (str_contains($lowerName, 'time') || str_contains($lowerName, 'date') || str_ends_with($lowerName, '_at')) {
            return [
                'semantic_type' => 'time',
                'is_dimension' => true,
                'is_metric' => false,
                'default_aggregate' => 'none',
            ];
        }

        if (in_array($normalizedType, ['integer', 'decimal', 'number'], true)) {
            if ($lowerName === 'id' || str_ends_with($lowerName, '_id')) {
                return [
                    'semantic_type' => 'id',
                    'is_dimension' => true,
                    'is_metric' => false,
                    'default_aggregate' => 'none',
                ];
            }

            return [
                'semantic_type' => str_contains($lowerName, 'amount') ? 'amount' : 'normal',
                'is_dimension' => false,
                'is_metric' => true,
                'default_aggregate' => 'sum',
            ];
        }

        return [
            'semantic_type' => 'category',
            'is_dimension' => true,
            'is_metric' => false,
            'default_aggregate' => 'none',
        ];
    }
}
