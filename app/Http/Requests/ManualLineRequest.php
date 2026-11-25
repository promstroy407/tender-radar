<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Estimate;
use App\Models\PriceItem;
use App\Support\Quantity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Работа, добавленная в расчёт вручную: название, единица, объём, при желании — позиция прайса. */
class ManualLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Estimate::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:500'],
            'unit' => ['required', Rule::in(PriceItemRequest::UNITS)],
            'quantity' => ['required', 'string', 'regex:'.Quantity::PATTERN],
            'price_item' => ['nullable', 'integer', 'exists:price_items,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Напишите, что за работа.',
            'quantity.required' => 'Укажите объём.',
            'quantity.regex' => 'Объём — число больше нуля, до трёх знаков после запятой.',
        ];
    }

    public function name(): string
    {
        return trim((string) $this->validated('name'));
    }

    public function unit(): string
    {
        return (string) $this->validated('unit');
    }

    public function quantityMilli(): int
    {
        return Quantity::toMilli((string) $this->validated('quantity'));
    }

    public function priceItem(): ?PriceItem
    {
        $id = $this->validated('price_item');

        return is_numeric($id) ? PriceItem::query()->find((int) $id) : null;
    }
}
