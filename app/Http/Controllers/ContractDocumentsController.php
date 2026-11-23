<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\FetchContractDocuments;
use App\Jobs\LoadDocumentsIntoEstimate;
use App\Models\Contract;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Документация закупки в карточке тендера: найти в ЕИС заново и загрузить в смету.
 */
class ContractDocumentsController extends Controller
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function fetch(Contract $contract): RedirectResponse
    {
        $this->bus->dispatch(new FetchContractDocuments($contract->id));

        return redirect()->route('tenders.show', $contract)
            ->with('status', 'Ищу документацию в ЕИС. Обновите страницу через минуту.');
    }

    public function toEstimate(Request $request, Contract $contract): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $this->bus->dispatch(new LoadDocumentsIntoEstimate($contract->id, $user->id));

        return redirect()->route('estimates.show', $contract)
            ->with('status', 'Документация скачивается в смету. Файлы появятся здесь в течение нескольких минут — обновите страницу.');
    }
}
