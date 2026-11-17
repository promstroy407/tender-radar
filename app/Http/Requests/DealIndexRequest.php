<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DealSource;
use App\Enums\DealStatus;
use App\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealIndexRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(DealStatus::class)],
            'source' => ['sometimes', 'nullable', Rule::enum(DealSource::class)],
        ];
    }

    public function status(): DealStatus
    {
        return $this->enum('status', DealStatus::class) ?? DealStatus::New;
    }

    public function source(): ?DealSource
    {
        return $this->enum('source', DealSource::class);
    }
}
