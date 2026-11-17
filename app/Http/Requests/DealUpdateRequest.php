<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\DealChange;
use App\Models\Deal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/** Суммы, напоминание и заметка в карточке сделки. Суммы вводятся в рублях. */
class DealUpdateRequest extends FormRequest
{
    private const MONEY_FIELDS = ['offer_amount', 'contract_amount', 'paid'];

    public function authorize(): bool
    {
        $deal = $this->route('deal');

        return $deal instanceof Deal && ($this->user()?->can('update', $deal) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (self::MONEY_FIELDS as $field) {
            $value = $this->input($field);
            $clean[$field] = is_string($value) ? preg_replace('/[\s\x{00A0}]+/u', '', $value) : $value;
        }

        $note = $this->input('note');
        $clean['note'] = is_string($note) && trim($note) !== '' ? trim($note) : null;

        $this->merge($clean);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Рубли, можно с копейками: «1500000» или «1500000,50»; до 999 млрд.
        $money = ['nullable', 'string', 'regex:/^\d{1,12}([.,]\d{1,2})?$/'];

        return [
            'offer_amount' => $money,
            'contract_amount' => $money,
            'paid' => $money,
            'remind_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before:2100-01-01'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $text = 'Сумма в рублях, например 1500000 или 1500000,50.';

        return [
            'offer_amount.regex' => $text,
            'contract_amount.regex' => $text,
            'paid.regex' => $text,
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $contract = $this->kopecks('contract_amount');
                $paid = $this->kopecks('paid');

                if ($paid !== null && $contract !== null && $paid > $contract) {
                    $validator->errors()->add('paid', 'Оплачено больше суммы договора — проверьте суммы.');
                }
            },
        ];
    }

    public function toChange(): DealChange
    {
        $remindOn = $this->validated('remind_on');
        $note = $this->validated('note');

        return new DealChange(
            offerAmountKopecks: $this->kopecks('offer_amount'),
            contractAmountKopecks: $this->kopecks('contract_amount'),
            paidKopecks: $this->kopecks('paid'),
            remindOn: is_string($remindOn) ? Carbon::createFromFormat('Y-m-d', $remindOn)?->startOfDay() : null,
            note: is_string($note) ? $note : null,
        );
    }

    /** Рубли строкой → целые копейки, без float: «1500000,5» → 150000050. */
    private function kopecks(string $field): ?int
    {
        $value = $this->input($field);

        if (! is_string($value) || preg_match('/^(\d{1,12})(?:[.,](\d{1,2}))?$/', $value, $m) !== 1) {
            return null;
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }
}
