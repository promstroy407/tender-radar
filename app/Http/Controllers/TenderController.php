<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContractFilter;
use App\Http\Requests\ContractIndexRequest;
use App\Models\Contract;
use App\Services\ContractService;
use App\Services\Eis\ContractDocumentsService;
use App\Services\EisLinkBuilder;
use App\Services\Estimates\EstimateCalculator;
use App\Services\KpTemplateService;
use App\Services\MessengerLinkBuilder;
use App\Services\MoneyFormatter;
use App\Services\OfferSender;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Экраны «Тендеры» (лента новых победителей) и «Карточка тендера».
 */
class TenderController extends Controller
{
    /** Ключ сессии: фильтры и страница ленты, с которых открыли карточку. */
    public const FEED_QUERY = 'feed.query';

    public function __construct(
        private readonly ContractService $contracts,
        private readonly KpTemplateService $template,
        private readonly MessengerLinkBuilder $messengers,
        private readonly EisLinkBuilder $eisLinks,
        private readonly OfferSender $sender,
        private readonly MoneyFormatter $money,
        private readonly EstimateCalculator $calculator,
        private readonly ContractDocumentsService $documents,
    ) {}

    public function index(ContractIndexRequest $request): View
    {
        $search = $request->toSearch(onlyNew: true);

        // Запоминаем фильтры и страницу ленты: «Назад» из карточки вернёт туда же.
        $request->session()->put(self::FEED_QUERY, $request->feedQuery());

        return view('tenders.index', [
            'contracts' => $this->contracts->search($search)->withQueryString(),
            'counts' => $this->contracts->counts($search),
            'filters' => ContractFilter::cases(),
            'active' => $search->filter,
            'unseen' => $search->onlyUnseen,
            'withDocs' => $search->onlyWithDocuments,
            'money' => $this->money,
        ]);
    }

    public function show(Contract $contract, Request $request): View
    {
        $user = $request->user();
        if ($user !== null) {
            $this->contracts->markViewed($contract, $user);
        }

        $contract->load(['company', 'region', 'offer', 'deal', 'estimate', 'documents']);
        $feedQuery = $request->session()->get(self::FEED_QUERY);
        $rendered = $this->template->render($contract);
        $documents = $this->documents->split($contract);
        $kindLabels = [];
        foreach ($documents['useful'] as $document) {
            $kindLabels[$document->id] = $this->documents->kind($document)->label();
        }

        return view('tenders.show', [
            'contract' => $contract,
            'eisUrl' => $this->eisLinks->contractUrl($contract->law, $contract->registry_number),
            'message' => $rendered->body,
            'whatsappUrl' => $this->messengers->whatsapp($contract->company->phone, $rendered->body),
            'telegramUrl' => $this->messengers->telegram($contract->company->phone),
            'mailReady' => $this->sender->mailConfigured(),
            'money' => $this->money,
            'documents' => $documents,
            'kindLabels' => $kindLabels,
            'backUrl' => route('home', is_array($feedQuery) ? $feedQuery : []),
            'estimateSummary' => $contract->estimate === null ? null
                : $this->calculator->summarize($contract->estimate, $contract->estimate->lines()->with('priceItem')->get()),
        ]);
    }
}
