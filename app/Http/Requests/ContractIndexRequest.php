<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\ContractSearch;
use App\Enums\ContractFilter;
use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Contract::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', Rule::enum(ContractFilter::class)],
            'regions' => ['sometimes', 'array', 'max:100'],
            'regions.*' => ['string', 'size:2', 'distinct', Rule::exists('regions', 'code')],
            'min_amount_kopecks' => ['sometimes', 'integer', 'min:0', 'max:9000000000000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function toSearch(bool $onlyNew = false): ContractSearch
    {
        /** @var list<string> $regions */
        $regions = array_values((array) $this->validated('regions', []));

        return new ContractSearch(
            filter: $this->enum('filter', ContractFilter::class) ?? ContractFilter::All,
            regionCodes: $regions,
            minAmountKopecks: $this->has('min_amount_kopecks') ? $this->integer('min_amount_kopecks') : null,
            perPage: $this->integer('per_page', 20),
            onlyNew: $onlyNew,
        );
    }
}
