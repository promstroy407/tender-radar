<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DealSource;
use App\Http\Requests\FunnelRequest;
use App\Services\FunnelService;
use App\Services\MoneyFormatter;
use Illuminate\Contracts\View\View;

/**
 * Экран «Воронка»: этапы сделок, конверсия, деньги и источники.
 */
class FunnelController extends Controller
{
    public function __construct(
        private readonly FunnelService $funnel,
        private readonly MoneyFormatter $money,
    ) {}

    public function __invoke(FunnelRequest $request): View
    {
        $source = $request->source();

        return view('funnel.index', [
            'report' => $this->funnel->report($source),
            'source' => $source,
            'sources' => DealSource::cases(),
            'money' => $this->money,
        ]);
    }
}
