<?php

declare(strict_types=1);

namespace App\Data;

/** Письмо с КП под конкретный контракт: поля шаблона уже подставлены. */
final readonly class RenderedOffer
{
    public function __construct(
        public string $subject,
        public string $body,
    ) {}
}
