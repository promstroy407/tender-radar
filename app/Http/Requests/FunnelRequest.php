<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DealSource;
use App\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FunnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Deal::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['sometimes', 'nullable', Rule::enum(DealSource::class)],
        ];
    }

    public function source(): ?DealSource
    {
        return $this->enum('source', DealSource::class);
    }
}
