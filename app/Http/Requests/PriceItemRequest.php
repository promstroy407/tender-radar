<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PriceSection;
use App\Models\PriceItem;
use App\Services\Estimates\NumberParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriceItemRequest extends FormRequest
{
    public const UNITS = ['м', 'шт', 'компл', 'точка', 'услуга'];

    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof PriceItem
            ? ($this->user()?->can('update', $item) ?? false)
            : ($this->user()?->can('create', PriceItem::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'price_rubles' => preg_replace('/[\s\x{00A0}]+/u', '', (string) $this->input('price_rubles')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->route('item');

        return [
            'section' => ['required', Rule::enum(PriceSection::class)],
            'name' => ['required', 'string', 'max:255', Rule::unique('price_items', 'name')
                ->where('unit', (string) $this->input('unit'))
                ->ignore($item instanceof PriceItem ? $item->id : null)],
            'unit' => ['required', Rule::in(self::UNITS)],
            // Пусто — цена не задана; иначе рубли, можно с копейками: «1300» или «1300,50».
            'price_rubles' => ['nullable', 'regex:/^\d{1,9}([.,]\d{1,2})?$/'],
            'keywords' => ['required', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_rubles.regex' => 'Цена — число рублей, например 1300 или 1300,50.',
            'name.unique' => 'Такая позиция с этой единицей уже есть.',
        ];
    }

    /**
     * @return array{section: PriceSection, name: string, unit: string, work_price_kopecks: int|null, keywords: string, is_active: bool}
     */
    public function toAttributes(): array
    {
        $price = (string) $this->input('price_rubles');

        return [
            'section' => $this->enum('section', PriceSection::class) ?? PriceSection::Electric,
            'name' => trim($this->string('name')->toString()),
            'unit' => $this->string('unit')->toString(),
            'work_price_kopecks' => $price === '' ? null : app(NumberParser::class)->toKopecks($price),
            'keywords' => mb_strtolower(trim($this->string('keywords')->toString())),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
