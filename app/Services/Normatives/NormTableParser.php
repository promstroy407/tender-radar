<?php

declare(strict_types=1);

namespace App\Services\Normatives;

use App\Services\Estimates\NumberParser;

/**
 * Разбирает таблицу норм из реестра ФСНБ-2022: состав норм (шифр, название, единица)
 * и затраты труда рабочих. Труд бывает средним разрядом («1-100-38 Средний разряд
 * работы 3,8») или по разрядам («2-100-04 Рабочий 4 разряда») — берём всё, что
 * в разделе «1. Затраты труда рабочих» измеряется в чел.-ч.
 */
class NormTableParser
{
    private const LABOR = '/^[12]-100-\d+$/';

    public function __construct(
        private readonly NumberParser $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $node  узел-таблица из API
     * @return list<array{code: string, table_name: string, name: string, unit: string, labor: list<array{code: string, milli: int}>}>
     */
    public function parse(array $node): array
    {
        $norms = $this->decode($node['normTableJson'] ?? null);
        $parts = $this->decode($node['normTableValueTableJson'] ?? null);
        $tableName = trim((string) preg_replace('/^Таблица\s+ГЭСН\S*\s+\S+\s+/u', '', (string) ($node['name'] ?? '')));

        $labor = [];
        foreach ($parts as $part) {
            $cipher = (string) ($part['Cipher'] ?? '');
            if (preg_match(self::LABOR, $cipher) !== 1) {
                continue;
            }
            foreach (is_array($part['NormTablePartNormValueList'] ?? null) ? $part['NormTablePartNormValueList'] : [] as $value) {
                $number = (string) ($value['NormNumber'] ?? '');
                $milli = $this->numbers->toMilli(is_scalar($value['Value'] ?? null) ? (string) $value['Value'] : null);
                if ($number !== '' && $milli !== null) {
                    $labor[$number][] = ['code' => $cipher, 'milli' => $milli];
                }
            }
        }

        $result = [];
        foreach ($norms as $norm) {
            $code = (string) ($norm['Number'] ?? '');
            if ($code === '') {
                continue;
            }
            $result[] = [
                'code' => $code,
                'table_name' => mb_substr($tableName, 0, 255),
                'name' => (string) ($norm['Name'] ?? ''),
                'unit' => mb_substr((string) ($norm['MeterName'] ?? ''), 0, 32),
                'labor' => $labor[$code] ?? [],
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decode(mixed $json): array
    {
        if (! is_string($json) || $json === '') {
            return [];
        }
        $data = json_decode($json, true);

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }
}
