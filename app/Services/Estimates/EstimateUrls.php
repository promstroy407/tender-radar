<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Models\Estimate;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * Адреса страниц сметы: у сметы тендера они под тендером, у расчёта
 * из «Калькулятора» — под калькулятором. Одна страница — два входа.
 */
class EstimateUrls
{
    public function __construct(
        private readonly UrlGenerator $url,
    ) {}

    /**
     * @param  array<string, string|int>  $query
     */
    public function show(Estimate $estimate, array $query = []): string
    {
        return $estimate->contract_id === null
            ? $this->url->route('calculator.show', ['estimate' => $estimate->id] + $query)
            : $this->url->route('estimates.show', ['contract' => $estimate->contract_id] + $query);
    }

    public function upload(Estimate $estimate): string
    {
        return $estimate->contract_id === null
            ? $this->url->route('calculator.upload', $estimate->id)
            : $this->url->route('estimates.upload', $estimate->contract_id);
    }

    public function export(Estimate $estimate): string
    {
        return $estimate->contract_id === null
            ? $this->url->route('calculator.export', $estimate->id)
            : $this->url->route('estimates.export', $estimate->contract_id);
    }

    public function materials(Estimate $estimate): string
    {
        return $estimate->contract_id === null
            ? $this->url->route('calculator.materials', $estimate->id)
            : $this->url->route('estimates.materials', $estimate->contract_id);
    }

    public function rereadMaterials(Estimate $estimate): string
    {
        return $estimate->contract_id === null
            ? $this->url->route('calculator.materials.reread', $estimate->id)
            : $this->url->route('estimates.materials.reread', $estimate->contract_id);
    }

    /** Куда ведёт «Назад»: к тендеру или к списку расчётов. */
    public function back(Estimate $estimate): string
    {
        return $estimate->contract_id === null
            ? $this->url->route('calculator')
            : $this->url->route('tenders.show', $estimate->contract_id);
    }
}
