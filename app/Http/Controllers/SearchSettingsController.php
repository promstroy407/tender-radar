<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchSettingsRequest;
use App\Services\DemoDataService;
use App\Services\MoneyFormatter;
use App\Services\OfferSender;
use App\Services\SearchSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Экран «Настройки»: регионы, порог суммы, ссылки на Telegram и ЕИС, демо-данные.
 */
class SearchSettingsController extends Controller
{
    public function __construct(
        private readonly SearchSettingsService $search,
        private readonly DemoDataService $demo,
        private readonly OfferSender $sender,
        private readonly MoneyFormatter $money,
    ) {}

    public function edit(): View
    {
        $minKopecks = $this->search->minAmountKopecks();

        return view('settings.index', [
            'regions' => $this->search->regions(),
            'minAmountRubles' => number_format(intdiv($minKopecks, 100), 0, '', ' '),
            'minAmountFormatted' => $this->money->format($minKopecks),
            'demoLoaded' => $this->demo->exists(),
            'mailReady' => $this->sender->mailConfigured(),
        ]);
    }

    public function update(SearchSettingsRequest $request): RedirectResponse
    {
        $this->search->apply($request->regionCodes(), $request->minAmountKopecks());

        return redirect()->route('settings')->with('status', 'Настройки поиска сохранены.');
    }

    public function loadDemo(): RedirectResponse
    {
        $this->demo->load();

        return redirect()->route('home')->with('status', 'Демо-тендеры загружены. Они помечены «[Демо]».');
    }

    public function removeDemo(): RedirectResponse
    {
        $this->demo->remove();

        return redirect()->route('settings')->with('status', 'Демо-тендеры удалены.');
    }
}
