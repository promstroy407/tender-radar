<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PriceItem;
use Illuminate\Foundation\Http\FormRequest;

/** Открыть форму новой позиции прайса, заполненную по строке сметы. */
class PriceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PriceItem::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:500'],
            'unit' => ['nullable', 'string', 'max:20'],
            'back' => ['nullable', 'integer', 'exists:estimates,id'],
        ];
    }
}
