<?php

declare(strict_types=1);

namespace App\Enums;

/** Где находится наше КП по контракту. */
enum OfferStatus: string
{
    case Sent = 'sent';
    case Replied = 'replied';
    case Deal = 'deal';
    case Refused = 'refused';

    /** «Не интересно»: контракт убран из ленты без отправки. */
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Отправлено',
            self::Replied => 'Ответили',
            self::Deal => 'В работе',
            self::Refused => 'Отказ',
            self::Dismissed => 'Не интересно',
        };
    }

    /**
     * Вкладки экрана «В работе» в порядке воронки.
     *
     * @return list<self>
     */
    public static function board(): array
    {
        return [self::Sent, self::Replied, self::Deal, self::Refused];
    }
}
