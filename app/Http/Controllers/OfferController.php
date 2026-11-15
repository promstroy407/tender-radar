<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OfferChannel;
use App\Http\Requests\MessengerOfferRequest;
use App\Models\Contract;
use App\Models\User;
use App\Services\KpTemplateService;
use App\Services\MessengerLinkBuilder;
use App\Services\OfferException;
use App\Services\OfferSender;
use App\Services\OfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Действия с карточки тендера: отправить КП, открыть мессенджер, «Не интересно».
 */
class OfferController extends Controller
{
    public function __construct(
        private readonly OfferSender $sender,
        private readonly OfferService $offers,
        private readonly KpTemplateService $template,
        private readonly MessengerLinkBuilder $messengers,
    ) {}

    public function email(Request $request, Contract $contract): RedirectResponse
    {
        try {
            $this->sender->sendEmail($contract, $this->user($request));
        } catch (OfferException $e) {
            return redirect()->route('tenders.show', $contract)->with('error', $e->getMessage());
        }

        return redirect()->route('tenders.show', $contract)
            ->with('status', 'КП отправлено на '.$contract->company->email.'. Напоминание — через '.OfferService::REMIND_AFTER_DAYS.' дня.');
    }

    /** Отмечает КП отправленным и открывает чат: сообщение пользователь отправляет сам. */
    public function messenger(MessengerOfferRequest $request, Contract $contract): RedirectResponse
    {
        $contract->load(['company', 'region']);
        $channel = $request->channel();
        $phone = $contract->company->phone;

        $url = $channel === OfferChannel::WhatsApp
            ? $this->messengers->whatsapp($phone, $this->template->render($contract)->body)
            : $this->messengers->telegram($phone);

        if ($url === null) {
            return redirect()->route('tenders.show', $contract)->with('error', 'У победителя нет мобильного номера.');
        }

        $this->offers->recordSent($contract, $channel, $this->user($request));

        return redirect()->away($url);
    }

    public function dismiss(Request $request, Contract $contract): RedirectResponse
    {
        $this->offers->dismiss($contract, $this->user($request));

        return redirect()->route('home')->with('status', 'Тендер убран из ленты.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
