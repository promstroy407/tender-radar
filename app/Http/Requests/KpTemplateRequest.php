<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class KpTemplateRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:200'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'remove_attachment' => ['sometimes', 'boolean'],
        ];
    }

    public function subject(): string
    {
        return trim($this->string('subject')->toString());
    }

    public function body(): string
    {
        return trim($this->string('body')->toString());
    }

    public function dailyLimit(): int
    {
        return $this->integer('daily_limit');
    }

    public function attachment(): ?UploadedFile
    {
        $file = $this->file('attachment');

        return $file instanceof UploadedFile ? $file : null;
    }
}
