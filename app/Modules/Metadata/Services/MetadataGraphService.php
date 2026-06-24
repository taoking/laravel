<?php

namespace App\Modules\Metadata\Services;

class MetadataGraphService
{
    public function __construct(private readonly MetadataLineageService $lineageService) {}

    /**
     * @return array<string, mixed>
     */
    public function graph(string $assetType, int $assetId, int $depth = 3): array
    {
        return $this->lineageService->graph($assetType, $assetId, $depth);
    }
}
