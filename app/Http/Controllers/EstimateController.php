<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EstimateLineRequest;
use App\Http\Requests\EstimateUploadRequest;
use App\Http\Requests\ManualLineRequest;
use App\Models\Contract;
use App\Models\Estimate;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use App\Models\User;
use App\Services\Estimates\EstimateExporter;
use App\Services\Estimates\EstimatePageBuilder;
use App\Services\Estimates\EstimateService;
use App\Services\Estimates\EstimateUrls;
use App\Services\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Раздел «Смета» в карточке тендера: файлы документации, позиции, наша цена.
 * Действия с файлами и позициями общие со сметами «Калькулятора».
 */
class EstimateController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimates,
        private readonly EstimatePageBuilder $pages,
        private readonly EstimateExporter $exporter,
        private readonly EstimateUrls $urls,
        private readonly MoneyFormatter $money,
    ) {}

    public function show(Request $request, Contract $contract): View
    {
        $contract->load(['estimate.files', 'region', 'company']);
        $estimate = $contract->estimate;

        return view('estimates.show', $this->pages->build($estimate, $contract->region_code, $request->query('filter') === 'attention') + [
            'estimate' => $estimate,
            'money' => $this->money,
            'page' => [
                'title' => $contract->subject,
                'subtitle' => $contract->region->name.' · '.$contract->company->name,
                'region' => $contract->region->name,
                'back' => route('tenders.show', $contract),
                'self' => route('estimates.show', $contract),
                'attention' => route('estimates.show', ['contract' => $contract, 'filter' => 'attention']),
                'upload' => route('estimates.upload', $contract),
                'export' => $estimate === null ? null : route('estimates.export', $contract),
                'materials' => route('estimates.materials', $contract),
            ],
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

        return redirect()->to($this->urls->show($file->estimate))->with('status', 'Файл читается заново.');
    }

    /** Текст PDF так, как его видит разбор: чтобы понять, почему смета не прочиталась. */
    public function text(EstimateFile $file): Response
    {
        return new Response($this->estimates->text($file), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function destroyFile(EstimateFile $file): RedirectResponse
    {
        $estimate = $file->estimate;
        $this->estimates->deleteFile($file);

        return redirect()->to($this->urls->show($estimate))->with('status', 'Файл и его позиции удалены.');
    }

    public function updateLine(EstimateLineRequest $request, EstimateLine $line): RedirectResponse
    {
        $this->estimates->setLineMatch($line, $request->priceItem(), $request->skip(), $request->quantityMilli());

        return redirect()->to($this->urls->show($line->estimate, [
            'filter' => (string) $request->input('filter', 'all'),
            'page' => $request->integer('page', 1),
        ]).'#line-'.$line->id);
    }

    public function storeLine(ManualLineRequest $request, Estimate $estimate): RedirectResponse
    {
        $line = $this->estimates->addManualLine($estimate, $request->name(), $request->unit(), $request->quantityMilli(), $request->priceItem());

        return redirect()->to($this->urls->show($estimate).'#line-'.$line->id)->with('status', 'Работа добавлена.');
    }

    public function export(Contract $contract): BinaryFileResponse
    {
        $estimate = $contract->estimate;
        abort_if($estimate === null, 404);

        return $this->download($estimate, 'Смета_тендер_'.$contract->id.'.xlsx');
    }

    public function exportCalculation(Estimate $estimate): BinaryFileResponse
    {
        abort_unless($estimate->isCalculation(), 404);

        return $this->download($estimate, 'Расчёт_'.$estimate->id.'.xlsx');
    }

    /** Excel с позициями и формулами — для сметы тендера и расчёта калькулятора. */
    private function download(Estimate $estimate, string $fileName): BinaryFileResponse
    {
        $lines = $estimate->lines()->with('priceItem')->orderBy('estimate_file_id')->orderBy('position')->get();
        $path = (string) tempnam(sys_get_temp_dir(), 'est');
        $this->exporter->toXlsx($estimate, $lines, $path);

        return response()->download($path, $fileName)->deleteFileAfterSend();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
