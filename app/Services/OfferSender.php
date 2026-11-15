<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OfferChannel;
use App\Mail\KpMail;
use App\Models\Contract;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Throwable;

/**
 * Отправка КП победителю по почте. Письмо уходит сразу (не через очередь),
 * чтобы ошибка почтового сервера была видна на экране, а статус не врал.
 */
class OfferSender
{
    /** Почтовые драйверы, которые никуда не отправляют. */
    private const FAKE_MAILERS = ['log', 'array'];

    public function __construct(
        private readonly MailFactory $mail,
        private readonly Config $config,
        private readonly KpTemplateService $template,
        private readonly OfferService $offers,
    ) {}

    public function sendEmail(Contract $contract, User $user): Offer
    {
        $contract->loadMissing(['company', 'region']);

        $this->assertMailConfigured();

        $email = $contract->company->email;
        if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new OfferException('У победителя нет почты. Напишите в мессенджер или найдите почту на его сайте.');
        }

        $limit = $this->template->dailyLimit();
        if ($this->offers->emailsSentToday() >= $limit) {
            throw new OfferException('Сегодня уже отправлено '.$limit.' писем — это дневной лимит. Остальные — завтра, иначе почту сочтут рассылкой.');
        }

        $mail = new KpMail(
            $this->template->render($contract),
            $this->template->hasAttachment() ? $this->template->attachmentPath() : null,
            KpTemplateService::ATTACHMENT_NAME,
        );

        try {
            $this->mail->mailer()->to($email)->send($mail);
        } catch (Throwable $e) {
            throw new OfferException('Почтовый сервер не принял письмо: '.$e->getMessage());
        }

        return $this->offers->recordSent($contract, OfferChannel::Email, $user);
    }

    public function mailConfigured(): bool
    {
        $mailer = (string) $this->config->get('mail.default');

        return ! in_array($mailer, self::FAKE_MAILERS, true)
            && filled($this->config->get('mail.from.address'))
            && $this->config->get('mail.from.address') !== 'hello@example.com';
    }

    private function assertMailConfigured(): void
    {
        if (! $this->mailConfigured()) {
            throw new OfferException('Почта для КП ещё не подключена (ящик kp@es12.ru). Пока отправляйте через WhatsApp или Telegram.');
        }
    }
}
