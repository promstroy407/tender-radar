<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Setting;
use App\Services\Telegram\TelegramConfig;
use App\Services\Telegram\TelegramConfigException;
use App\Services\Telegram\TelegramUpdatesMode;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TelegramSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Setting::class) ?? false;
    }

    /**
     * Пустое поле — оставить прежнее значение; флажок «удалить» — стереть.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bot_token' => ['nullable', 'string', 'max:100', 'regex:/^\d{5,15}:[A-Za-z0-9_-]{30,60}$/'],
            'api_url' => ['nullable', 'string', 'max:255', $this->validApiUrl()],
            'clear_bot_token' => ['sometimes', 'boolean'],
            'clear_api_url' => ['sometimes', 'boolean'],
            'updates_mode' => ['sometimes', Rule::enum(TelegramUpdatesMode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bot_token.regex' => 'Токен выглядит как «123456789:AAH…» — цифры, двоеточие, затем 30–60 букв, цифр, «_» или «-».',
        ];
    }

    public function botToken(): ?string
    {
        $value = trim($this->string('bot_token')->toString());

        return $value === '' ? null : $value;
    }

    public function apiUrl(): ?string
    {
        $value = trim($this->string('api_url')->toString());

        return $value === '' ? null : rtrim($value, '/');
    }

    public function updatesMode(): ?TelegramUpdatesMode
    {
        return $this->enum('updates_mode', TelegramUpdatesMode::class);
    }

    private function validApiUrl(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            try {
                TelegramConfig::fromArray(['api_url' => is_string($value) ? trim($value) : null]);
            } catch (TelegramConfigException $e) {
                $fail($e->getMessage());
            }
        };
    }
}
