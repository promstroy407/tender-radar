<?php

declare(strict_types=1);

namespace App\Services\Normatives;

use RuntimeException;

/** Нормативы не загружены; сообщение показывается администратору. */
class NormativesException extends RuntimeException {}
