<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\WorkSection;
use App\Models\Contract;
use App\Services\EisLinkBuilder;
use App\Services\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $money = app(MoneyFormatter::class);
        $links = app(EisLinkBuilder::class);

        return [
            'id' => $this->id,
            'registry_number' => $this->registry_number,
            'law' => ['value' => $this->law->value, 'label' => $this->law->label()],
            'kind' => ['value' => $this->kind->value, 'label' => $this->kind->label()],
            'region' => [
                'code' => $this->region_code,
                'name' => $this->whenLoaded('region', fn () => $this->region->name),
            ],
            'subject' => $this->subject,
            'customer_name' => $this->customer_name,
            'amount_kopecks' => $this->amount_kopecks,
            'amount_formatted' => $money->format($this->amount_kopecks),
            'signed_at' => $this->signed_at->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'sections' => $this->sections
                ->map(fn (WorkSection $section): array => [
                    'value' => $section->value,
                    'label' => $section->label(),
                    'short' => $section->shortLabel(),
                ])
                ->values()
                ->all(),
            'requires_smp_subcontract' => $this->requires_smp_subcontract,
            'is_hot' => $this->isHot(),
            'eis_url' => $links->contractUrl($this->law, $this->registry_number),
            'company' => CompanyResource::make($this->whenLoaded('company')),
        ];
    }
}
