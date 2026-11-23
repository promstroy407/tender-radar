<?php

declare(strict_types=1);

namespace App\Data\Eis;

/** Файл документации из извещения о закупке. */
final readonly class NoticeDocument
{
    public function __construct(
        public string $name,
        public string $url,
        public ?string $description,
        public ?int $sizeBytes,
    ) {}
}
