<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OfferStatus;
use App\Http\Requests\OfferUpdateRequest;
use App\Http\Requests\WorkIndexRequest;
use App\Models\Offer;
use App\Services\MoneyFormatter;
use App\Services\OfferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Экран «В работе»: кому отправлены КП, кто ответил, что дальше.
 */
class WorkController extends Controller
{
    public function __construct(
        private readonly OfferService $offers,
        private readonly MoneyFormatter $money,
    ) {}

    public function index(WorkIndexRequest $request): View
    {
        $status = $request->status();

        return view('work.index', [
            'status' => $status,
            'statuses' => OfferStatus::board(),
            'counts' => $this->offers->counts(),
            'items' => $this->offers->board($status),
            'money' => $this->money,
        ]);
    }

    public function update(OfferUpdateRequest $request, Offer $offer): RedirectResponse
    {
        $offer = $this->offers->change($offer, $request->toChange());

        $back = $offer->status === OfferStatus::Dismissed ? OfferStatus::Sent : $offer->status;

        return redirect()->route('work', ['status' => $back->value])->with('status', 'Сохранено.');
    }
}
