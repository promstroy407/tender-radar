<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstimateFileStatus;
use App\Enums\LineMatch;
use App\Enums\PriceSection;
use App\Http\Requests\EstimateLineRequest;
use App\Http\Requests\EstimateUploadRequest;
use App\Models\Contract;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use App\Models\User;
use App\Services\Estimates\EstimateCalculator;
use App\Services\Estimates\EstimateExporter;
use App\Services\Estimates\EstimateService;
use App\Services\Estimates\PriceResolver;
use App\Services\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Раздел «Смета» в карточке тендера: файлы документации, позиции, наша цена.
 */
class EstimateController extends Controller
{
    private const LINES_PER_PAGE = 100;

    public function __construct(
        private readonly EstimateService $estimates,
        private readonly EstimateCalculator $calculator,
        private readonly EstimateExporter $exporter,
        private readonly MoneyFormatter $money,
        private readonly PriceResolver $prices,
    ) {}

    public function show(Request $request, Contract $contract): View
    {
        $contract->load(['estimate.files', 'region', 'company']);
        $estimate = $contract->estimate;
        $attention = $request->query('filter') === 'attention';

        $summary = null;
        $lines = null;
        if ($estimate !== null) {
            $summary = $this->calculator->summarize($estimate, $estimate->lines()->with('priceItem')->get());

            $lines = $estimate->lines()->with(['priceItem', 'file'])
                ->when($attention, fn (Builder $q) => $q->where('match', '!=', LineMatch::Skip->value)
                    ->where(fn (Builder $q) => $q->whereNull('price_item_id')
                        ->orWhereHas('priceItem', fn (Builder $p) => $p->whereNull('work_price_kopecks'))))
                ->orderBy('estimate_file_id')
                ->orderBy('position')
                ->paginate(self::LINES_PER_PAGE)
                ->withQueryString();
        }

        // Цена за единицу каждой позиции прайса в регионе тендера: по ГЭСН или вручную.
        $items = PriceItem::query()->where('is_active', true)->orderBy('name')->get();
        $unitPrices = [];
        foreach ($items as $item) {
            $unitPrices[$item->id] = $this->prices->unitPrice($item, $contract->region_code);
        }

        return view('estimates.show', [
            'contract' => $contract,
            'estimate' => $estimate,
            'summary' => $summary,
            'lines' => $lines,
            'attention' => $attention,
            'pending' => $estimate?->files->contains(fn (EstimateFile $f): bool => $f->status === EstimateFileStatus::Pending) ?? false,
            'priceGroups' => $items->groupBy(fn (PriceItem $item): string => $item->section->value),
            'unitPrices' => $unitPrices,
            'sections' => PriceSection::cases(),
            'money' => $this->money,
        ]);
    }

    public function upload(EstimateUploadRequest $request, Contract $contract): RedirectResponse
    {
        $estimate = $this->estimates->forContract($contract, $this->user($request));
        $files = $this->estimates->addUploads($estimate, $request->uploads());

        return redirect()->route('estimates.show', $contract)
            ->with('status', 'Загружено файлов: '.count($files).'. Читаем — обновите страницу через минуту.');
    }

    public function reparse(EstimateFile $file): RedirectResponse
    {
        $this->estimates->reparse($file);

        return redirect()->route('estimates.show', $file->estimate->contract_id)->with('status', 'Файл читается заново.');
    }

    /** Текст PDF так, как его видит разбор: чтобы понять, почему смета не прочиталась. */
    public function text(EstimateFile $file): Response
    {
        return new Response($this->estimates->text($file), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function destroyFile(EstimateFile $file): RedirectResponse
    {
        $contractId = $file->estimate->contract_id;
        $this->estimates->deleteFile($file);

        return redirect()->route('estimates.show', $contractId)->with('status', 'Файл и его позиции удалены.');
    }

    public function updateLine(EstimateLineRequest $request, EstimateLine $line): RedirectResponse
    {
        $this->estimates->setLineMatch($line, $request->priceItem(), $request->skip());

        return redirect()->to(route('estimates.show', [
            'contract' => $line->file->estimate->contract_id,
            'filter' => $request->input('filter', 'all'),
            'page' => $request->integer('page', 1),
        ]).'#line-'.$line->id);
    }

    public function export(Contract $contract): BinaryFileResponse
    {
        $estimate = $contract->estimate;
        abort_if($estimate === null, 404);

        $lines = $estimate->lines()->with('priceItem')->orderBy('estimate_file_id')->orderBy('position')->get();
        $path = (string) tempnam(sys_get_temp_dir(), 'est');
        $this->exporter->toXlsx($estimate, $lines, $path);

        return response()->download($path, 'Смета_тендер_'.$contract->id.'.xlsx')->deleteFileAfterSend();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
