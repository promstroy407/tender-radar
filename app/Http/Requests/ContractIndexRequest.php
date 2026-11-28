<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\ContractSearch;
use App\Enums\ContractFilter;
use App\Models\Contract;
use Carbon\CarbonImmutable;
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
            'unseen' => ['sometimes', 'boolean'],
            'docs' => ['sometimes', 'boolean'],
            'region' => ['sometimes', 'nullable', 'string', 'size:2', Rule::exists('regions', 'code')],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'regions' => ['sometimes', 'array', 'max:100'],
            'regions.*' => ['string', 'size:2', 'distinct', Rule::exists('regions', 'code')],
            'min_amount_kopecks' => ['sometimes', 'integer', 'min:0', 'max:9000000000000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Фильтры и страница ленты, как в адресе, — только проверенные значения.
     *
     * @return array<string, string|int>
     */
    public function feedQuery(): array
    {
        $query = [];
        $filter = $this->validated('filter');
        if (is_string($filter) && $filter !== 'all') {
            $query['filter'] = $filter;
        }
        foreach (['docs', 'unseen'] as $toggle) {
            if ($this->boolean($toggle)) {
                $query[$toggle] = 1;
            }
        }
        foreach (['region', 'from', 'to'] as $field) {
            $value = $this->validated($field);
            if (is_string($value) && $value !== '') {
                $query[$field] = $value;
            }
        }
        $page = $this->integer('page', 1);
        if ($page > 1) {
            $query['page'] = $page;
        }

        return $query;
    }

    public function toSearch(bool $onlyNew = false): ContractSearch
    {
        /** @var list<string> $regions */
        $regions = array_values((array) $this->validated('regions', []));
        $region = $this->validated('region');
        if (is_string($region) && $region !== '') {
            $regions = [$region];
        }

        return new ContractSearch(
            filter: $this->enum('filter', ContractFilter::class) ?? ContractFilter::All,
            regionCodes: $regions,
            minAmountKopecks: $this->has('min_amount_kopecks') ? $this->integer('min_amount_kopecks') : null,
            perPage: $this->integer('per_page', 20),
            onlyNew: $onlyNew,
            viewerId: $this->user()?->id,
            onlyUnseen: $this->boolean('unseen'),
            onlyWithDocuments: $this->boolean('docs'),
            loadedFrom: $this->day('from'),
            loadedTo: $this->day('to'),
        );
    }

    private function day(string $field): ?CarbonImmutable
    {
        $value = $this->validated($field);

        return is_string($value) && $value !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null : null;
    }
}
