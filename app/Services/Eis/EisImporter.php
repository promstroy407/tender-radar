<?php

declare(strict_types=1);

namespace App\Services\Eis;

use App\Data\Eis\ContractClassification;
use App\Data\Eis\EisImportResult;
use App\Data\Eis\ParsedContract;
use App\Enums\ProcurementLaw;
use App\Models\Company;
use App\Models\Contract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use ZipArchive;

/**
 * Сбор контрактов 44-ФЗ из реестра контрактов ЕИС: регион заказчика и день публикации →
 * архивы → XML → отбор по профилю → контракты и победители в базе. Повторный сбор
 * того же дня ничего не дублирует: контракт обновляется по реестровому номеру.
 */
class EisImporter
{
    private const SUBSYSTEM = 'RGK';

    private const DOCUMENT_TYPE = 'contract';

    public function __construct(
        private readonly EisClient $client,
        private readonly ContractXmlParser $parser,
        private readonly ContractClassifier $classifier,
        private readonly ConnectionInterface $db,
    ) {}

    public function importDay(string $regionCode, Carbon $date): EisImportResult
    {
        $this->client->assertConfigured();

        $urls = $this->client->docsByOrgRegion($regionCode, self::SUBSYSTEM, self::DOCUMENT_TYPE, $date->toDateString());
        $files = $parsed = $matched = $saved = 0;

        foreach ($urls as $url) {
            foreach ($this->xmlFiles($this->client->downloadWhenReady($url)) as $xml) {
                $files++;
                $contract = $this->parser->parse($xml);
                if ($contract === null) {
                    continue;
                }
                $parsed++;

                $classification = $this->classifier->classify($contract);
                if ($classification === null || $contract->supplier === null) {
                    continue;
                }
                $matched++;

                $this->save($regionCode, $contract, $classification);
                $saved++;
            }
        }

        return new EisImportResult($regionCode, $date, count($urls), $files, $parsed, $matched, $saved);
    }

    private function save(string $regionCode, ParsedContract $contract, ContractClassification $classification): void
    {
        $supplier = $contract->supplier;
        if ($supplier === null) {
            return;
        }

        $this->db->transaction(function () use ($regionCode, $contract, $classification, $supplier): void {
            $company = Company::query()->firstOrNew(['inn' => $supplier->inn]);
            $company->fill(array_filter([
                'kpp' => $supplier->kpp,
                'name' => mb_substr($supplier->name, 0, 255),
                // Контакты из ЕИС не затирают уже найденные: пишем, только если пусто.
                'email' => $company->email ?? $supplier->email,
                'phone' => $company->phone ?? $supplier->phone,
                'city' => $company->city ?? $supplier->city,
            ], fn (?string $value): bool => $value !== null))->save();

            Contract::query()->updateOrCreate(
                ['law' => ProcurementLaw::Fz44, 'registry_number' => $contract->registryNumber],
                [
                    'kind' => $classification->kind,
                    'region_code' => $regionCode,
                    'subject' => $contract->subject,
                    'customer_name' => mb_substr($contract->customerName, 0, 255),
                    'customer_inn' => $contract->customerInn,
                    'amount_kopecks' => $contract->amountKopecks,
                    'signed_at' => $contract->signedAt,
                    'ends_at' => $contract->endsAt,
                    'sections' => $classification->sections,
                    'company_id' => $company->id,
                ],
            );
        });
    }

    /**
     * XML-файлы из zip-архива ЕИС.
     *
     * @return iterable<string>
     */
    private function xmlFiles(string $zipBytes): iterable
    {
        $path = tempnam(sys_get_temp_dir(), 'eis');
        file_put_contents($path, $zipBytes);

        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new EisException('ЕИС: архив повреждён или это не zip.');
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_ends_with(strtolower($name), '.xml')) {
                    yield (string) $zip->getFromIndex($i);
                }
            }

            $zip->close();
        } finally {
            @unlink($path);
        }
    }
}
