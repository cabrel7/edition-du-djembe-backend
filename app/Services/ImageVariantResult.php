<?php

declare(strict_types=1);

namespace App\Services;

final class ImageVariantResult
{
    public function __construct(
        public readonly ImageVariantStatus $status,
        public readonly string $source,
        public readonly int $bytesBefore = 0,
        public readonly int $bytesAfter = 0,
        public readonly ?string $reason = null,
    ) {
    }

    public function isCreated(): bool
    {
        return $this->status === ImageVariantStatus::Created;
    }
}
