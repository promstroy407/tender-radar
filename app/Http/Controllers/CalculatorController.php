<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CalculationRequest;
use App\Http\Requests\EstimateUploadRequest;
use App\Models\Estimate;
use App\Models\Region;
use App\Models\User;
use App\Services\Estimates\EstimateCalculator;
use App\Services\Estimates\EstimatePageBuilder;
use App\Services\Estimates\EstimateService;
use App\Services\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «Калькулятор»: сторонняя смета, не из тендера. Загружаете файлы — считаются работы
 * по смете заказчика и отдельно по нашим расценкам, как в просчёте тендера.
 */
class CalculatorController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimates,
        private readonly EstimatePageBuilder $pages,
        private readonly EstimateCalculator $calculator,
        private readonly MoneyFormatter $money,
    ) {}

    public function index(): View
    {
        $calculations = Estimate::query()->whereNull('contract_id')->with(['region', 'files'])->latest()->get();

        $summaries = [];
        foreach ($calculations as $calculation) {
            $summaries[$calculation->id] = $this->calculator->summarize($calculation, $calculation->lines()->with('priceItem')->get());
        }

        return view('calculator.index', [
            'calculations' => $calculations,
            'summaries' => $summaries,
            'regions' => Region::query()->orderBy('name')->get(),
            'money' => $this->money,
        ]);
    }

    public function store(CalculationRequest $request): RedirectResponse
    {
        $estimate = $this->estimates->createCalculation($request->name(), $request->regionCode(), $this->user($request));

        return redirect()->route('calculator.show', $estimate)->with('status', 'Расчёт создан — загрузите смету.');
    }

    public function show(Request $request, Estimate $estimate): View
    {
        abort_unless($estimate->isCalculation(), 404);
        $estimate->load(['files', 'region']);
        $region = $estimate->region->name ?? (string) $estimate->region_code;

        return view('estimates.show', $this->pages->build($estimate, $estimate->regionCode(), $request->query('filter') === 'attention') + [
            'estimate' => $estimate,
            'money' => $this->money,
            'page' => [
                'title' => $estimate->title(),
                'subtitle' => 'Калькулятор · '.$region,
                'region' => $region,
                'back' => route('calculator'),
                'self' => route('calculator.show', $estimate),
                'attention' => route('calculator.show', ['estimate' => $estimate, 'filter' => 'attention']),
                'upload' => route('calculator.upload', $estimate),
                'export' => route('calculator.export', $estimate),
                'materials' => route('calculator.materials', $estimate),
            ],
        ]);
    }

    public function upload(EstimateUploadRequest $request, Estimate $estimate): RedirectResponse
    {
        abort_unless($estimate->isCalculation(), 404);
        $files = $this->estimates->addUploads($estimate, $request->uploads());

        return redirect()->route('calculator.show', $estimate)
            ->with('status', 'Загружено файлов: '.count($files).'. Читаем — обновите страницу через минуту.');
    }

    public function destroy(Estimate $estimate): RedirectResponse
    {
        abort_unless($estimate->isCalculation(), 404);
        $this->estimates->deleteCalculation($estimate);

        return redirect()->route('calculator')->with('status', 'Расчёт удалён.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
