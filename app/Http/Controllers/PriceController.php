<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PriceSection;
use App\Http\Requests\PriceDraftRequest;
use App\Http\Requests\PriceItemRequest;
use App\Models\Estimate;
use App\Models\PriceItem;
use App\Models\Region;
use App\Services\Estimates\EstimateService;
use App\Services\Estimates\EstimateUrls;
use App\Services\Estimates\PriceDraftBuilder;
use App\Services\Estimates\PriceResolver;
use App\Services\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Прайс работ: цены и ключевые слова, по которым считается быстрая смета.
 */
class PriceController extends Controller
{
    public function __construct(
        private readonly PriceResolver $prices,
        private readonly MoneyFormatter $money,
        private readonly PriceDraftBuilder $drafts,
        private readonly EstimateService $estimates,
        private readonly EstimateUrls $urls,
    ) {}

    /** Новая позиция по непосчитанной строке сметы: форма заполнена заранее. */
    public function create(PriceDraftRequest $request): View
    {
        $back = $request->validated('back');
        $estimate = is_numeric($back) ? Estimate::query()->find((int) $back) : null;

        return view('prices.create', [
            'draft' => $this->drafts->build((string) $request->validated('name'), $request->validated('unit')),
            'back' => $estimate?->id,
            'backUrl' => $estimate === null ? route('prices') : $this->urls->show($estimate).'#unpriced',
            'sections' => PriceSection::cases(),
            'units' => PriceItemRequest::UNITS,
        ]);
    }

    public function index(): View
    {
        $items = PriceItem::query()->orderBy('id')->get();
        $regions = Region::query()->orderBy('name')->get();

        // Нормативная цена каждой позиции по каждому региону (ГЭСН × ставка труда + НР + СП).
        $normative = [];
        foreach ($items as $item) {
            foreach ($regions as $region) {
                $normative[$item->id][$region->code] = $this->prices->normative($item, $region->code);
            }
        }

        return view('prices.index', [
            'groups' => $items->groupBy(fn (PriceItem $item): string => $item->section->value),
            'regions' => $regions,
            'normative' => $normative,
            'money' => $this->money,
            'sections' => PriceSection::cases(),
            'units' => PriceItemRequest::UNITS,
        ]);
    }

    public function store(PriceItemRequest $request): RedirectResponse
    {
        $item = PriceItem::query()->create($request->toAttributes());

        // Добавили из сметы — пересопоставляем её и возвращаемся к списку «Не посчитано».
        $estimate = $request->backEstimate();
        if ($estimate !== null) {
            $matched = $this->estimates->rematchUnmatched($estimate);

            return redirect()->to($this->urls->show($estimate).'#unpriced')
                ->with('status', 'Добавлено в прайс: «'.$item->name.'». Посчитано позиций: '.$matched.'.');
        }

        return redirect()->route('prices')->with('status', 'Позиция добавлена.');
    }

    public function update(PriceItemRequest $request, PriceItem $item): RedirectResponse
    {
        $item->update($request->toAttributes());

        return redirect()->to(route('prices').'#item-'.$item->id)->with('status', 'Сохранено: '.$item->name.'.');
    }
}
