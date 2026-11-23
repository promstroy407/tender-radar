<?php

declare(strict_types=1);

namespace App\Data\Eis;

/** Итог загрузки документации в смету: сколько файлов добавлено и что пропущено. */
final readonly class DocumentsToEstimateResult
{
    /**
     * @param  list<string>  $skipped  «имя — причина»
     */
    public function __construct(
        public int $loaded,
        public array $skipped,
    ) {}
}
