<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchSettingsRequest extends FormRequest
{
    /** Не больше триллиона рублей: столько не бывает, а копейки остаются в bigint. */
    private const MAX_RUBLES = 1_000_000_000_000;

    public function authorize(): bool
    {
        return $this->user()?->can('update', Setting::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        // «1 000 000» с пробелами — как на экране; в рубли без копеек.
        $this->merge([
            'min_amount_rubles' => preg_replace('/[\s\x{00A0}]+/u', '', (string) $this->input('min_amount_rubles')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'regions' => ['sometimes', 'array', 'max:100'],
            'regions.*' => ['string', 'size:2', 'distinct', Rule::exists('regions', 'code')],
            'min_amount_rubles' => ['required', 'regex:/^\d{1,13}$/', 'integer', 'min:0', 'max:'.self::MAX_RUBLES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['min_amount_rubles.regex' => 'Укажите сумму целым числом рублей, например 1 000 000.'];
    }

    /**
     * @return list<string>
     */
    public function regionCodes(): array
    {
        /** @var list<string> $codes */
        $codes = array_values((array) $this->validated('regions', []));

        return $codes;
    }

    public function minAmountKopecks(): int
    {
        return $this->integer('min_amount_rubles') * 100;
    }
}
