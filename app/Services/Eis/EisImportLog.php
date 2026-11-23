<?php

declare(strict_types=1);

namespace App\Services\Eis;

use App\Data\Eis\EisImportResult;
use App\Enums\SettingKey;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;

/**
 * Журнал сбора контрактов: последние запуски (регион, день, сколько нашли), чтобы
 * в «Настройки → ЕИС» было видно, что сбор идёт и где ошибки.
 */
class EisImportLog
{
    private const KEEP = 40;

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function recordSuccess(EisImportResult $result): void
    {
        $this->push([
            'region' => $result->regionCode,
            'date' => $result->date->toDateString(),
            'archives' => $result->archives,
            'files' => $result->files,
            'matched' => $result->matched,
            'saved' => $result->saved,
            'error' => null,
            'finished_at' => Carbon::now()->toDateTimeString(),
        ]);
    }

    public function recordError(string $regionCode, Carbon $date, string $error): void
    {
        $this->push([
            'region' => $regionCode,
            'date' => $date->toDateString(),
            'archives' => 0,
            'files' => 0,
            'matched' => 0,
            'saved' => 0,
            'error' => mb_substr($error, 0, 500),
            'finished_at' => Carbon::now()->toDateTimeString(),
        ]);
    }

    /**
     * Новые сверху.
     *
     * @return list<array{region: string, date: string, archives: int, files: int, matched: int, saved: int, error: string|null, finished_at: string}>
     */
    public function entries(): array
    {
        $raw = $this->settings->get(SettingKey::EisImportLog);
        $data = $raw !== null ? json_decode($raw, true) : null;

        if (! is_array($data)) {
            return [];
        }

        $entries = [];
        foreach ($data as $row) {
            if (is_array($row) && isset($row['region'], $row['date'], $row['finished_at'])) {
                $entries[] = [
                    'region' => (string) $row['region'],
                    'date' => (string) $row['date'],
                    'archives' => (int) ($row['archives'] ?? 0),
                    'files' => (int) ($row['files'] ?? 0),
                    'matched' => (int) ($row['matched'] ?? 0),
                    'saved' => (int) ($row['saved'] ?? 0),
                    'error' => isset($row['error']) ? (string) $row['error'] : null,
                    'finished_at' => (string) $row['finished_at'],
                ];
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function push(array $entry): void
    {
        $entries = array_slice([$entry, ...$this->entries()], 0, self::KEEP);
        $this->settings->put(SettingKey::EisImportLog, (string) json_encode($entries, JSON_UNESCAPED_UNICODE));
    }
}
