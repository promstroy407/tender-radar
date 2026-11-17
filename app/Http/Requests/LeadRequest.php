<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\LeadData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Заявка с сайта es12.ru. Доступ проверяет AuthenticateLeadsToken (токен сайта).
 * Строки обрезаются по краям, пустые становятся null.
 */
class LeadRequest extends FormRequest
{
    public const FIELDS = [
        'external_id', 'received_at', 'name', 'phone', 'email', 'organization',
        'facility', 'service', 'origin', 'message', 'estimate', 'source_url',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (self::FIELDS as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $clean[$field] = $value === '' ? null : $value;
            }
        }

        $this->merge($clean);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:64', 'regex:/^es12-\d+$/'],
            // ISO 8601 с часовым поясом: 2026-11-17T14:05:00+03:00 или …Z.
            'received_at' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})$/', 'date'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+7\d{10}$/'],
            'email' => ['nullable', 'string', 'max:255', 'email:rfc'],
            'organization' => ['nullable', 'string', 'max:150'],
            'facility' => ['nullable', 'string', 'max:200'],
            'service' => ['nullable', 'string', 'max:200'],
            'origin' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
            'estimate' => ['nullable', 'string', 'max:500'],
            'source_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'external_id.regex' => 'external_id должен быть вида es12-<номер заявки>.',
            'received_at.regex' => 'received_at — дата ISO 8601 с часовым поясом, например 2026-11-17T14:05:00+03:00.',
            'phone.regex' => 'phone — в виде +7XXXXXXXXXX.',
        ];
    }

    public function toLead(): LeadData
    {
        return new LeadData(
            externalId: $this->text('external_id') ?? '',
            receivedAt: Carbon::parse($this->text('received_at') ?? 'now'),
            name: $this->text('name') ?? '',
            phone: $this->text('phone') ?? '',
            email: $this->text('email'),
            organization: $this->text('organization'),
            facility: $this->text('facility'),
            service: $this->text('service'),
            origin: $this->text('origin'),
            message: $this->text('message'),
            estimate: $this->text('estimate'),
            sourceUrl: $this->text('source_url'),
        );
    }

    private function text(string $field): ?string
    {
        $value = $this->validated($field);

        return is_string($value) ? $value : null;
    }
}
