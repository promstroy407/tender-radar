<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PriceSection;
use App\Http\Requests\PriceItemRequest;
use App\Models\PriceItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Прайс работ: цены и ключевые слова, по которым считается быстрая смета.
 */
class PriceController extends Controller
{
    public function index(): View
    {
        return view('prices.index', [
            'groups' => PriceItem::query()->orderBy('id')->get()
                ->groupBy(fn (PriceItem $item): string => $item->section->value),
            'sections' => PriceSection::cases(),
            'units' => PriceItemRequest::UNITS,
        ]);
    }

    public function store(PriceItemRequest $request): RedirectResponse
    {
        PriceItem::query()->create($request->toAttributes());

        return redirect()->route('prices')->with('status', 'Позиция добавлена.');
    }

    public function update(PriceItemRequest $request, PriceItem $item): RedirectResponse
    {
        $item->update($request->toAttributes());

        return redirect()->to(route('prices').'#item-'.$item->id)->with('status', 'Сохранено: '.$item->name.'.');
    }
}
