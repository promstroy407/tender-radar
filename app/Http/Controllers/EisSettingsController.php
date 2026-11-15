<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SettingKey;
use App\Http\Requests\EisSettingsRequest;
use App\Jobs\RunEisProbe;
use App\Services\Eis\EisProbe;
use App\Services\SettingsService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * «Настройки → ЕИС»: токен получателя данных и проверка доступа с сервера.
 */
class EisSettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly EisProbe $probe,
        private readonly Dispatcher $bus,
    ) {}

    public function edit(): View
    {
        return view('settings.eis', [
            'tokenSource' => $this->settings->source(SettingKey::EisToken, 'services.eis.token'),
            'report' => $this->probe->lastReport(),
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
}
