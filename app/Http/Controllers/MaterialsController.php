<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\Estimates\EstimateService;
use App\Services\Estimates\TrayReportBuilder;
use App\Services\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * «Лоток и комплектующие» по тендеру: выборка из смет заказчика с количеством и ценой.
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
            'contract' => $contract,
            'report' => $contract->estimate === null ? null : $this->reports->build($contract->estimate),
            'money' => $this->money,
        ]);
    }

    public function reread(Contract $contract): RedirectResponse
    {
        $count = $contract->estimate === null ? 0 : $this->estimates->rereadForMaterials($contract->estimate);

        return redirect()->route('estimates.materials', $contract)
            ->with('status', $count > 0 ? 'Сметы читаются заново — обновите страницу через минуту.' : 'Перечитывать нечего.');
    }
}
