<?php

declare(strict_types=1);

namespace App\Services\Normatives;

use App\Enums\SettingKey;
use App\Models\GesnNorm;
use App\Models\LaborRate;
use App\Services\Estimates\NumberParser;
use App\Services\SettingsService;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Загрузка нормативов с портала ФГИС ЦС: нормы ГЭСНм нужных сборников и сметные
 * цены труда по нашим регионам за последний квартал. Итог — в «Настройки → Нормативы».
 */
class NormativesSync
{
    private const CHUNK = 500;

    public function __construct(
        private readonly FgisClient $fgis,
        private readonly NormTableParser $parser,
        private readonly NumberParser $numbers,
        private readonly Config $config,
        private readonly ConnectionInterface $db,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed> отчёт, он же сохраняется как статус
     */
    public function run(): array
    {
        $status = ['started_at' => Carbon::now()->toDateTimeString()];

        try {
            $status['norms'] = $this->syncNorms();
            $status['periods'] = $this->syncRates();
            $status['ok'] = true;
        } catch (Throwable $e) {
            $status['ok'] = false;
            $status['error'] = mb_substr($e->getMessage(), 0, 500);
        }

        $status['finished_at'] = Carbon::now()->toDateTimeString();
        $this->settings->put(SettingKey::NormativesStatus, (string) json_encode($status, JSON_UNESCAPED_UNICODE));

        return $status;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastStatus(): ?array
    {
        $raw = $this->settings->get(SettingKey::NormativesStatus);
        $data = $raw === null ? null : json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /** @return int сколько норм загружено */
    public function syncNorms(): int
    {
        /** @var array<string, string> $collections */
        $collections = (array) $this->config->get('normatives.collections', []);
        $roots = $this->fgis->children((string) $this->config->get('normatives.gesnm_root'));

        $total = 0;
        foreach ($collections as $title => $kind) {
            $collection = $this->findByName($roots, $title);
            if ($collection === null) {
                throw new NormativesException('В реестре ФСНБ-2022 не найден «'.$title.'».');
            }

            $buffer = [];
            $this->crawl((string) $collection['guid'], $kind, $buffer, $total);
            $this->store($buffer);
        }

        if ($total === 0) {
            throw new NormativesException('Из реестра не пришло ни одной нормы.');
        }

        return $total;
    }

    /**
     * Ставки труда по регионам; старые ставки региона заменяются целиком.
     *
     * @return array<string, string> регион => квартал
     */
    public function syncRates(): array
    {
        /** @var array<string, int> $subjects */
        $subjects = (array) $this->config->get('normatives.subjects', []);
        $periods = [];

        foreach ($subjects as $region => $subjectId) {
            $zone = $this->fgis->priceZone((int) $subjectId);
            $period = $zone === null ? null : $this->fgis->latestPeriod($zone);
            if ($zone === null || $period === null) {
                throw new NormativesException('Нет сметных цен труда для региона '.$region.'.');
            }

            $rows = [];
            $now = Carbon::now();
            foreach ($this->fgis->laborRates($zone, $period['id']) as $code => $rubles) {
                $kopecks = $this->numbers->toKopecks($rubles);
                if ($kopecks !== null) {
                    $rows[] = ['region_code' => (string) $region, 'code' => $code, 'rate_kopecks' => $kopecks,
                        'period' => mb_substr($period['name'], 0, 32), 'created_at' => $now, 'updated_at' => $now];
                }
            }

            if ($rows === []) {
                throw new NormativesException('ФГИС ЦС вернула пустой перечень ставок для региона '.$region.'.');
            }

            $this->db->transaction(function () use ($region, $rows): void {
                LaborRate::query()->where('region_code', (string) $region)->delete();
                LaborRate::query()->insert($rows);
            });
            $periods[(string) $region] = $period['name'];
        }

        return $periods;
    }

    /**
     * @param  list<array{code: string, kind?: string, table_name: string, name: string, unit: string, labor: list<array{code: string, milli: int}>}>  $buffer
     */
    private function crawl(string $guid, string $kind, array &$buffer, int &$total): void
    {
        $this->pause();

        foreach ($this->fgis->children($guid) as $node) {
            if (is_string($node['normTableJson'] ?? null) && $node['normTableJson'] !== '') {
                foreach ($this->parser->parse($node) as $norm) {
                    $buffer[] = $norm + ['kind' => $kind];
                    $total++;
                }
                if (count($buffer) >= self::CHUNK) {
                    $this->store($buffer);
                    $buffer = [];
                }
            } elseif (($node['isLeaf'] ?? true) === false && isset($node['guid'])) {
                $this->crawl((string) $node['guid'], $kind, $buffer, $total);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $norms
     */
    private function store(array $norms): void
    {
        if ($norms === []) {
            return;
        }

        $now = Carbon::now();
        $rows = array_map(fn (array $n): array => [
            'code' => $n['code'],
            'kind' => $n['kind'],
            'table_name' => $n['table_name'],
            'name' => $n['name'],
            'unit' => $n['unit'],
            'labor' => json_encode($n['labor']),
            'created_at' => $now,
            'updated_at' => $now,
        ], $norms);

        GesnNorm::query()->upsert($rows, ['code'], ['kind', 'table_name', 'name', 'unit', 'labor', 'updated_at']);
    }

    /**
     * Сборник ищем по названию: идентификаторы в реестре меняются от редакции к редакции.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function findByName(array $nodes, string $title): ?array
    {
        foreach ($nodes as $node) {
            if (trim((string) ($node['name'] ?? '')) === $title && isset($node['guid'])) {
                return $node;
            }
        }

        return null;
    }

    private function pause(): void
    {
        $ms = (int) $this->config->get('normatives.request_pause_ms', 0);
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
