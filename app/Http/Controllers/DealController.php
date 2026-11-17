<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DealSource;
use App\Enums\DealStatus;
use App\Http\Requests\DealIndexRequest;
use App\Http\Requests\DealStatusRequest;
use App\Http\Requests\DealUpdateRequest;
use App\Models\Deal;
use App\Services\DealService;
use App\Services\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Экран «Сделки»: тендеры и заявки клиентов по статусам, карточка сделки.
 */
class DealController extends Controller
{
    public function __construct(
        private readonly DealService $deals,
        private readonly MoneyFormatter $money,
    ) {}

    public function index(DealIndexRequest $request): View
    {
        $status = $request->status();
        $source = $request->source();

        return view('deals.index', [
            'status' => $status,
            'source' => $source,
            'statuses' => DealStatus::cases(),
            'sources' => DealSource::cases(),
            'counts' => $this->deals->counts($source),
            'items' => $this->deals->list($status, $source),
            'money' => $this->money,
        ]);
    }

    public function show(Deal $deal): View
    {
        $deal->load(['contract.region', 'company', 'offer']);

        return view('deals.show', [
            'deal' => $deal,
            'statuses' => DealStatus::cases(),
            'money' => $this->money,
        ]);
    }

    public function updateStatus(DealStatusRequest $request, Deal $deal): RedirectResponse
    {
        $this->deals->changeStatus($deal, $request->status());

        return redirect()->route('deals.show', $deal)->with('status', 'Статус: «'.$deal->status->label().'».');
    }

    public function update(DealUpdateRequest $request, Deal $deal): RedirectResponse
    {
        $this->deals->update($deal, $request->toChange());

        return redirect()->route('deals.show', $deal)->with('status', 'Сохранено.');
    }
}
