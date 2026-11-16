<?php

declare(strict_types=1);

namespace App\Services\Normatives;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Открытое API портала ФГИС ЦС (fgiscs.minstroyrf.ru): реестр сметных нормативов
 * и сметные цены затрат труда. Авторизация не нужна.
 */
class FgisClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly Config $config,
    ) {}

    /**
     * Дочерние узлы реестра ФСНБ-2022 (сборник → отдел → раздел → таблица норм).
     *
     * @return list<array<string, mixed>>
     */
    public function children(string $guid): array
    {
        $data = $this->get('NormLegalDocPublished', [
            'orderBy' => 'name ASC',
            'normLegalDocPublishedType' => 10,
            'normLegalDocPublishedTypes' => '[10,41]',
            'state' => 20,
            'normLegalDocBaseKind' => 100,
            'parentGuid' => $guid,
        ]);

        $nodes = $data[$guid] ?? [];

        return is_array($nodes) ? array_values(array_filter($nodes, 'is_array')) : [];
    }

    /** Ценовая зона субъекта РФ (у наших пяти регионов она одна). */
    public function priceZone(int $subjectId): ?int
    {
        $zones = $this->get('EstimatedPrice/PriceZones', ['subjectId' => $subjectId]);
        $first = is_array($zones[0] ?? null) ? $zones[0] : null;

        return isset($first['id']) ? (int) $first['id'] : null;
    }

    /**
     * Последний опубликованный квартал для зоны.
     *
     * @return array{id: int, name: string}|null
     */
    public function latestPeriod(int $zoneId): ?array
    {
        $periods = $this->get('EstimatedPrice/Periods', ['priceZoneId' => $zoneId]);
        $first = is_array($periods[0] ?? null) ? $periods[0] : null;

        return isset($first['id'], $first['name']) ? ['id' => (int) $first['id'], 'name' => (string) $first['name']] : null;
    }

    /**
     * Сметные цены 1 чел.-ч по кодам («1-100-38», «2-100-04», «4-100-050»), рубли строкой.
     *
     * @return array<string, string>
     */
    public function laborRates(int $zoneId, int $periodId): array
    {
        $data = $this->get('EstimatedPrice/RimWorkerSalaryRegistry', [
            'priceZoneId' => $zoneId,
            'periodId' => $periodId,
            'page' => 1,
            'take' => 1000,
        ]);

        $rates = [];
        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            if (is_array($item) && isset($item['code'], $item['salary'])) {
                $rates[(string) $item['code']] = (string) $item['salary'];
            }
        }

        return $rates;
    }

    /**
     * @param  array<string, string|int>  $query
     * @return array<int|string, mixed>
     */
    private function get(string $path, array $query): array
    {
        $url = rtrim((string) $this->config->get('normatives.fgis_url'), '/').'/'.$path;

        try {
            $response = $this->http->timeout(60)->retry(3, 2000)->acceptJson()->get($url, $query);
        } catch (Throwable $e) {
            throw new NormativesException('ФГИС ЦС недоступна: '.$e->getMessage());
        }

        $data = $response->json();
        if (! $response->successful() || ! is_array($data)) {
            throw new NormativesException('ФГИС ЦС ответила HTTP '.$response->status().' на '.$path);
        }

        return $data;
    }
}
