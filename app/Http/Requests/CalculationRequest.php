<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Estimate;
use Illuminate\Foundation\Http\FormRequest;

/** Новый расчёт «Калькулятора»: название и регион, по ставкам которого считать работы. */
class CalculationRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'region_code' => ['required', 'string', 'size:2', 'exists:regions,code'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Назовите расчёт — например, объект или заказчика.',
            'region_code.required' => 'Выберите регион: по его ставкам считаются работы.',
        ];
    }

    public function name(): string
    {
        return trim((string) $this->validated('name'));
    }

    public function regionCode(): string
    {
        return (string) $this->validated('region_code');
    }
}
