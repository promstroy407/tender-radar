<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DealStatus;
use App\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Смена статуса кнопкой в карточке сделки. */
class DealStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deal = $this->route('deal');

        return $deal instanceof Deal && ($this->user()?->can('update', $deal) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(DealStatus::class)],
        ];
    }

    public function status(): DealStatus
    {
        return $this->enum('status', DealStatus::class) ?? DealStatus::New;
    }
}
