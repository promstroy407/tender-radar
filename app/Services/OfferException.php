<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** КП не отправлено; сообщение показывается пользователю как есть. */
class OfferException extends RuntimeException {}
