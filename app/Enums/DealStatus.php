<?php

declare(strict_types=1);

namespace App\Enums;

/** Этап сделки — общий для тендеров и заявок с сайта. */
enum DealStatus: string
{
    case New = 'new';
    case BriefReceived = 'brief_received';
    case OfferSent = 'offer_sent';
    case Negotiation = 'negotiation';
    case Contract = 'contract';
    case InProgress = 'in_progress';
    case DonePaid = 'done_paid';
    case Refused = 'refused';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новая',
            self::BriefReceived => 'Проект/ТЗ получен',
            self::OfferSent => 'КП отправлено',
            self::Negotiation => 'Переговоры',
            self::Contract => 'Договор',
            self::InProgress => 'В работе',
            self::DonePaid => 'Сдан и оплачен',
            self::Refused => 'Отказ',
            self::Dismissed => 'Не интересно',
        };
    }

    /**
     * Этапы воронки по порядку; «Отказ» и «Не интересно» — выходы из неё.
     *
     * @return list<self>
     */
    public static function chain(): array
    {
        return [self::New, self::BriefReceived, self::OfferSent, self::Negotiation, self::Contract, self::InProgress, self::DonePaid];
    }

    /** Место в воронке (0 — «Новая»); для выходов — null. */
    public function stage(): ?int
    {
        $index = array_search($this, self::chain(), true);

        return $index === false ? null : $index;
    }

    public function isClosed(): bool
    {
        return $this === self::DonePaid || $this === self::Refused || $this === self::Dismissed;
    }
}
