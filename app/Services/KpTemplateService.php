<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\RenderedOffer;
use App\Enums\SettingKey;
use App\Enums\WorkSection;
use App\Models\Contract;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;

/**
 * Шаблон письма с КП: текст хранится в «Настройках», поля в фигурных скобках
 * подставляются из карточки контракта. Вложение — PDF с подробным КП.
 */
class KpTemplateService
{
    public const ATTACHMENT_PATH = 'kp/kp.pdf';

    public const ATTACHMENT_NAME = 'КП_электромонтаж.pdf';

    public const DEFAULT_DAILY_LIMIT = 30;

    public const UNSUBSCRIBE = 'Если предложение неактуально, ответьте «нет», и мы больше не напишем.';

    /** Поля, которые можно вставить в шаблон. */
    public const FIELDS = ['{объект}', '{регион}', '{сумма}', '{разделы}', '{победитель}', '{заказчик}'];

    private const DEFAULT_SUBJECT = 'Субподряд: электромонтаж и слаботочка на объекте {объект}';

    private const DEFAULT_BODY = <<<'TEXT'
        Здравствуйте!

        Поздравляем с победой в закупке «{объект}» ({регион}, контракт {сумма}).

        Мы — электромонтажная компания из Йошкар-Олы: более 15 лет в электромонтаже, своя бригада, выезжаем на объекты по всей России. Готовы взять на субподряд разделы {разделы}.

        Делаем электромонтаж (щиты, освещение, работы до 10 кВ, заземление и молниезащита), пожарную сигнализацию и СОУЭ, видеонаблюдение, СКУД, СКС и сети.

        Цену фиксируем до начала работ: по вашему проекту готовим смету с расшифровкой каждой позиции. После монтажа передаём исполнительную документацию для сдачи объекта.

        Подробное предложение — во вложении.

        С уважением,
        А. Н. Шатунов
        +7 937 112-48-97 · es12.ru
        TEXT;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly MoneyFormatter $money,
        private readonly FilesystemFactory $storage,
    ) {}

    public function subject(): string
    {
        return $this->settings->get(SettingKey::KpSubject) ?? self::DEFAULT_SUBJECT;
    }

    public function body(): string
    {
        return $this->settings->get(SettingKey::KpBody) ?? self::DEFAULT_BODY;
    }

    public function dailyLimit(): int
    {
        $value = $this->settings->get(SettingKey::KpDailyLimit);

        return $value !== null ? (int) $value : self::DEFAULT_DAILY_LIMIT;
    }

    public function save(string $subject, string $body, int $dailyLimit): void
    {
        $this->settings->put(SettingKey::KpSubject, $subject);
        $this->settings->put(SettingKey::KpBody, $this->normalizeNewlines($body));
        $this->settings->put(SettingKey::KpDailyLimit, (string) $dailyLimit);
    }

    public function render(Contract $contract): RenderedOffer
    {
        $values = [
            '{объект}' => $contract->subject,
            '{регион}' => $contract->region->name,
            '{сумма}' => $this->money->format($contract->amount_kopecks),
            '{разделы}' => $contract->sections
                ->map(fn (WorkSection $section): string => $section->phrase())
                ->implode(', '),
            '{победитель}' => $contract->company->name,
            '{заказчик}' => $contract->customer_name,
        ];

        return new RenderedOffer(
            subject: strtr($this->subject(), $values),
            body: strtr($this->body(), $values)."\n\n".self::UNSUBSCRIBE,
        );
    }

    public function hasAttachment(): bool
    {
        return $this->storage->disk('local')->exists(self::ATTACHMENT_PATH);
    }

    public function attachmentPath(): string
    {
        return $this->storage->disk('local')->path(self::ATTACHMENT_PATH);
    }

    public function storeAttachment(UploadedFile $file): void
    {
        $this->storage->disk('local')->putFileAs('kp', $file, basename(self::ATTACHMENT_PATH));
    }

    public function deleteAttachment(): void
    {
        $this->storage->disk('local')->delete(self::ATTACHMENT_PATH);
    }

    /** Текст обещает вложение, а файла нет — письмо окажется неправдой. */
    public function promisesMissingAttachment(): bool
    {
        return ! $this->hasAttachment() && mb_stripos($this->body(), 'вложени') !== false;
    }

    private function normalizeNewlines(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }
}
