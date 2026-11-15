<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{email: string, password: string}
     */
    public function credentials(): array
    {
        return [
            'email' => mb_strtolower($this->string('email')->trim()->toString()),
            'password' => $this->string('password')->toString(),
        ];
    }

    /** Ключ ограничения попыток: почта + IP. */
    public function throttleKey(): string
    {
        return 'login|'.mb_strtolower($this->string('email')->trim()->toString()).'|'.$this->ip();
    }
}
