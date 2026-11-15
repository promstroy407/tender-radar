<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContractFilter;
use App\Http\Requests\ContractIndexRequest;
use App\Models\Contract;
use App\Services\ContractService;
use App\Services\EisLinkBuilder;
use App\Services\Estimates\EstimateCalculator;
use App\Services\KpTemplateService;
use App\Services\MessengerLinkBuilder;
use App\Services\MoneyFormatter;
use App\Services\OfferSender;
use Illuminate\Contracts\View\View;

/**
 * Экраны «Тендеры» (лента новых победителей) и «Карточка тендера».
 */
class TenderController extends Controller
{
    public function __construct(
        private readonly ContractService $contracts,
        private readonly KpTemplateService $template,
        private readonly MessengerLinkBuilder $messengers,
        private readonly EisLinkBuilder $eisLinks,
        private readonly OfferSender $sender,
        private readonly MoneyFormatter $money,
        private readonly EstimateCalculator $calculator,
    ) {}

    public function index(ContractIndexRequest $request): View
    {
        $search = $request->toSearch(onlyNew: true);

        return view('tenders.index', [
            'contracts' => $this->contracts->search($search)->withQueryString(),
            'counts' => $this->contracts->counts($search),
            'filters' => ContractFilter::cases(),
            'active' => $search->filter,
            'money' => $this->money,
        ]);
    }

    public function show(Contract $contract): View
    {
        $contract->load(['company', 'region', 'offer', 'estimate']);
        $rendered = $this->template->render($contract);

        return view('tenders.show', [
            'contract' => $contract,
            'eisUrl' => $this->eisLinks->contractUrl($contract->law, $contract->registry_number),
            'message' => $rendered->body,
            'whatsappUrl' => $this->messengers->whatsapp($contract->company->phone, $rendered->body),
            'telegramUrl' => $this->messengers->telegram($contract->company->phone),
            'mailReady' => $this->sender->mailConfigured(),
            'money' => $this->money,
            'estimateSummary' => $contract->estimate === null ? null
                : $this->calculator->summarize($contract->estimate, $contract->estimate->lines()->with('priceItem')->get()),
        ]);
    }
}
