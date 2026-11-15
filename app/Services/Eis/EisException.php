<?php

declare(strict_types=1);

namespace App\Services\Eis;

use RuntimeException;

/** Ошибка обращения к ЕИС. Токен в тексте заменён на ***. */
class EisException extends RuntimeException {}
