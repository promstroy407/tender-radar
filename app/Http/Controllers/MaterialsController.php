<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Estimate;
use App\Services\Estimates\EstimateService;
use App\Services\Estimates\TrayReportBuilder;
use App\Services\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * «Лоток и комплектующие» по тендеру или расчёту калькулятора: выборка из смет заказчика с количеством и ценой.
 */
class MaterialsController extends Controller
{
    public function __construct(
        private readonly TrayReportBuilder $reports,
        private readonly EstimateService $estimates,
        private readonly MoneyFormatter $money,
    ) {}

    public function show(Contract $contract): View
    {
        $contract->load(['estimate.files', 'region', 'company']);

        return view('estimates.materials', [
            'report' => $contract->estimate === null ? null : $this->reports->build($contract->estimate),
            'money' => $this->money,
            'page' => [
                'title' => $contract->subject,
                'subtitle' => $contract->region->name.' · '.$contract->company->name,
                'back' => route('tenders.show', $contract),
                'reread' => route('estimates.materials.reread', $contract),
            ],
        ]);
    }

    public function reread(Contract $contract): RedirectResponse
    {
        $count = $contract->estimate === null ? 0 : $this->estimates->rereadCustomerEstimates($contract->estimate);

        return $this->afterReread(route('estimates.materials', $contract), $count);
    }

    public function showCalculation(Estimate $estimate): View
    {
        abort_unless($estimate->isCalculation(), 404);
        $estimate->load(['files', 'region']);

        return view('estimates.materials', [
            'report' => $this->reports->build($estimate),
            'money' => $this->money,
            'page' => [
                'title' => $estimate->title(),
                'subtitle' => 'Калькулятор · '.($estimate->region?->name ?? (string) $estimate->region_code),
                'back' => route('calculator.show', $estimate),
                'reread' => route('calculator.materials.reread', $estimate),
            ],
        ]);
    }

    public function rereadCalculation(Estimate $estimate): RedirectResponse
    {
        abort_unless($estimate->isCalculation(), 404);
        $count = $this->estimates->rereadCustomerEstimates($estimate);

        return $this->afterReread(route('calculator.materials', $estimate), $count);
    }

    private function afterReread(string $url, int $count): RedirectResponse
    {
        return redirect()->to($url)
            ->with('status', $count > 0 ? 'Сметы читаются заново — обновите страницу через минуту.' : 'Перечитывать нечего.');
    }
}
