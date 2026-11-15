<?php

declare(strict_types=1);

namespace App\Services\Eis;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Carbon;
use Throwable;
use ZipArchive;

/**
 * Проверка доступа к ЕИС с нашего сервера: читает схему сервиса (какие подсистемы
 * и типы документов разрешены), делает пробный запрос реестра контрактов и
 * сохраняет отчёт с образцом XML. Нужна, чтобы писать разбор по настоящим данным.
 */
class EisProbe
{
    public const REPORT_PATH = 'eis/probe.json';

    private const SAMPLE_CHARS = 6000;

    public function __construct(
        private readonly EisClient $client,
        private readonly FilesystemFactory $storage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(string $region = '12', ?string $date = null, string $subsystem = 'RGK', ?string $documentType = null): array
    {
        $report = [
            'started_at' => Carbon::now()->toDateTimeString(),
            'region' => $region,
            'date' => $date ??= Carbon::yesterday()->toDateString(),
            'subsystem' => $subsystem,
        ];

        try {
            $enums = $this->schemaEnumerations();
            $report['schema'] = $enums;
            $documentType ??= $this->guessContractType($enums['documentType44'] ?? []);
            $report['document_type'] = $documentType;

            if ($documentType === null) {
                throw new EisException('В схеме не найден тип документа для контрактов.');
            }

            $urls = $this->client->docsByOrgRegion($region, $subsystem, $documentType, $date);
            $report['archives'] = count($urls);

            if ($urls !== []) {
                $report['sample'] = $this->sample($this->downloadWhenReady($urls[0]));
            }

            $report['ok'] = true;
        } catch (EisException $e) {
            $report['ok'] = false;
            $report['error'] = $e->getMessage();
        } catch (Throwable $e) {
            $report['ok'] = false;
            $report['error'] = $this->client->mask(get_class($e).': '.$e->getMessage());
        }

        $report['finished_at'] = Carbon::now()->toDateTimeString();
        $this->storage->disk('local')->put(
            self::REPORT_PATH,
            (string) json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );

        return $report;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastReport(): ?array
    {
        $disk = $this->storage->disk('local');

        if (! $disk->exists(self::REPORT_PATH)) {
            return null;
        }

        $data = json_decode((string) $disk->get(self::REPORT_PATH), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Перечисления из XSD (с подключаемыми схемами): subsystemType, documentType44 и т.п.
     *
     * @return array<string, list<string>>
     */
    public function schemaEnumerations(): array
    {
        $result = [];
        $queue = ['getDocsIP-ws-api.xsd'];
        $seen = [];

        while ($queue !== [] && count($seen) < 10) {
            $name = array_shift($queue);
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            $xpath = $this->xpath($this->client->schema($name));

            foreach ($xpath->query('//*[local-name()="include" or local-name()="import"]/@schemaLocation') ?: [] as $attr) {
                $location = $attr->nodeValue ?? '';
                $queue[] = preg_match('/xsd=([^&]+)/', $location, $m) === 1 ? $m[1] : basename($location);
            }

            foreach ($xpath->query('//*[local-name()="simpleType"][@name]') ?: [] as $type) {
                if (! $type instanceof DOMElement) {
                    continue;
                }
                $values = [];
                foreach ($xpath->query('.//*[local-name()="enumeration"]/@value', $type) ?: [] as $value) {
                    $values[] = (string) $value->nodeValue;
                }
                if ($values !== []) {
                    $result[$type->getAttribute('name')] = $values;
                }
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $types
     */
    private function guessContractType(array $types): ?string
    {
        foreach (['contract', 'contractProcedure'] as $preferred) {
            if (in_array($preferred, $types, true)) {
                return $preferred;
            }
        }

        foreach ($types as $type) {
            if (str_starts_with(strtolower($type), 'contract')) {
                return $type;
            }
        }

        return null;
    }

    /** Архив формируется не сразу: пробуем скачать до минуты. */
    private function downloadWhenReady(string $url): string
    {
        $last = null;
        for ($attempt = 0; $attempt < 6; $attempt++) {
            try {
                return $this->client->downloadArchive($url);
            } catch (EisException $e) {
                $last = $e;
                sleep(10);
            }
        }

        throw $last ?? new EisException('ЕИС: архив не скачан.');
    }

    /**
     * @return array{files: list<string>, first_file: string|null, xml: string|null}
     */
    private function sample(string $zipBytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'eis');
        file_put_contents($path, $zipBytes);

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            @unlink($path);

            return ['files' => [], 'first_file' => null, 'xml' => mb_substr($zipBytes, 0, 500)];
        }

        $files = [];
        for ($i = 0; $i < min($zip->numFiles, 50); $i++) {
            $files[] = (string) $zip->getNameIndex($i);
        }

        $first = null;
        foreach ($files as $file) {
            if (str_ends_with(strtolower($file), '.xml')) {
                $first = $file;
                break;
            }
        }

        $xml = $first !== null ? mb_substr((string) $zip->getFromName($first), 0, self::SAMPLE_CHARS) : null;
        $zip->close();
        @unlink($path);

        return ['files' => $files, 'first_file' => $first, 'xml' => $xml];
    }

    private function xpath(string $xml): DOMXPath
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new EisException('ЕИС: схема пришла не в формате XML.');
        }

        return new DOMXPath($doc);
    }
}
