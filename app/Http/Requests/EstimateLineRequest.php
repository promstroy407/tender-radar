<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Estimate;
use App\Support\Quantity;
use App\Models\PriceItem;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstimateLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Estimate::class) ?? false;
    }

    /**
     * «skip» — не считать позицию, число — позиция прайса.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price_item' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== 'skip' && ! (is_string($value) && ctype_digit($value) && PriceItem::query()->whereKey((int) $value)->exists())) {
                    $fail('Выберите позицию прайса или «не считать».');
                }
            }],
            'filter' => ['sometimes', Rule::in(['all', 'attention'])],
            'quantity' => ['sometimes', 'nullable', 'string', 'regex:'.Quantity::PATTERN],
        ];
    }

    /** Объём в тысячных; null — не меняли. */
    public function quantityMilli(): ?int
    {
        $value = $this->validated('quantity');

        return is_string($value) && $value !== '' ? Quantity::toMilli($value) : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['quantity.regex' => 'Объём — число больше нуля, до трёх знаков после запятой.'];
    }

    public function skip(): bool
    {
        return $this->input('price_item') === 'skip';
    }

    public function priceItem(): ?PriceItem
    {
        return $this->skip() ? null : PriceItem::query()->find((int) $this->input('price_item'));
    }
}
