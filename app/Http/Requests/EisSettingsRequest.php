<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class EisSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Setting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string', 'min:10', 'max:255', 'regex:/^\S+$/'],
            'clear_token' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.regex' => 'Токен вставляется одной строкой, без пробелов и переносов.',
        ];
    }

    public function token(): ?string
    {
        $value = trim($this->string('token')->toString());

        return $value === '' ? null : $value;
    }
}
