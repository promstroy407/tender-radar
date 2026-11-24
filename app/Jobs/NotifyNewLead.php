<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Deal;
use App\Services\Telegram\LeadNotifier;
use App\Services\Telegram\OwnerChatService;
use App\Services\Telegram\TelegramConfigException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Psr\Log\LoggerInterface;

/**
 * Уведомление владельцу о новой заявке — в фоне, чтобы сайт получил ответ сразу,
 * даже если Telegram или пересыльщик медлят. Три попытки с паузой.
 */
class NotifyNewLead implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /** Своя очередь: воркер берёт её первой, заявка не ждёт распознавания смет. */
    public const QUEUE = 'notify';

    public function __construct(
        public readonly int $dealId,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(OwnerChatService $owner, Container $container, LoggerInterface $log): void
    {
        $deal = Deal::query()->find($this->dealId);

        // Чат владельца не привязан — к Telegram не обращаемся.
        if ($deal === null || $owner->chatId() === null) {
            return;
        }

        try {
            // Клиент бота создаётся здесь: ошибка настроек бота станет записью в журнале.
            $container->make(LeadNotifier::class)->notify($deal);
        } catch (TelegramConfigException $e) {
            // Бот не настроен — повтор не поможет; заявка всё равно лежит в «Сделках».
            $log->warning('Уведомление о заявке не отправлено: '.$e->getMessage(), ['deal_id' => $deal->id]);
        }
    }
}
