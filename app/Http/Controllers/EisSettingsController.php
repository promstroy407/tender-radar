<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SettingKey;
use App\Http\Requests\EisSettingsRequest;
use App\Jobs\RunEisProbe;
use App\Models\Region;
use App\Services\Eis\EisImportLog;
use App\Services\Eis\EisImportPlanner;
use App\Services\Eis\EisProbe;
use App\Services\SettingsService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * «Настройки → ЕИС»: токен получателя данных, проверка доступа и сбор контрактов.
 */
class EisSettingsController extends Controller
{
    /** Первичная загрузка: контракты за последний месяц. */
    private const BACKFILL_DAYS = 30;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly EisProbe $probe,
        private readonly Dispatcher $bus,
        private readonly EisImportPlanner $planner,
        private readonly EisImportLog $log,
    ) {}

    public function edit(): View
    {
        return view('settings.eis', [
            'tokenSource' => $this->settings->source(SettingKey::EisToken, 'services.eis.token'),
            'report' => $this->probe->lastReport(),
            'importLog' => $this->log->entries(),
            'regionNames' => Region::query()->pluck('name', 'code')->all(),
            'backfillDays' => self::BACKFILL_DAYS,
        ]);
    }

    public function update(EisSettingsRequest $request): RedirectResponse
    {
        $this->settings->applyInput(SettingKey::EisToken, $request->token(), $request->boolean('clear_token'));

        return redirect()->route('settings.eis')->with('status', 'Настройки сохранены.');
    }

    public function probe(): RedirectResponse
    {
        $this->bus->dispatch(new RunEisProbe);

        return redirect()->route('settings.eis')
            ->with('status', 'Проверка запущена. Обновите страницу через 1–2 минуты.');
    }

    /** Загрузить контракты за последние 30 дней по всем включённым регионам. */
    public function import(): RedirectResponse
    {
        $count = $this->planner->plan(Carbon::yesterday(), self::BACKFILL_DAYS);

        return redirect()->route('settings.eis')
            ->with('status', 'Сбор запущен: заданий — '.$count.'. Контракты будут появляться в «Тендерах» в течение часа.');
    }
}
