<?php

declare(strict_types=1);

namespace App\Services\Eis;

use App\Data\Eis\ContractClassification;
use App\Data\Eis\EisImportResult;
use App\Data\Eis\ParsedContract;
use App\Enums\ProcurementLaw;
use App\Jobs\FetchContractDocuments;
use App\Models\Company;
use App\Models\Contract;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

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
        private readonly EisArchiveReader $archives,
        private readonly ConnectionInterface $db,
        private readonly Dispatcher $bus,
    ) {}

    public function importDay(string $regionCode, Carbon $date): EisImportResult
    {
        $this->client->assertConfigured();

        $urls = $this->client->docsByOrgRegion($regionCode, self::SUBSYSTEM, self::DOCUMENT_TYPE, $date->toDateString());
        $files = $parsed = $matched = $saved = 0;

        foreach ($urls as $url) {
            foreach ($this->archives->xmlFiles($this->client->downloadWhenReady($url)) as $xml) {
                $files++;
                $contract = $this->parser->parse($xml);
                if ($contract === null) {
                    continue;
                }
                $parsed++;

                $classification = $this->classifier->classify($contract);
                if ($classification === null || $contract->supplier === null) {
                    $this->forget($contract->registryNumber);

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

        $saved = $this->db->transaction(function () use ($regionCode, $contract, $classification, $supplier): Contract {
            $company = Company::query()->firstOrNew(['inn' => $supplier->inn]);
            $company->fill(array_filter([
                'kpp' => $supplier->kpp,
                'name' => mb_substr($supplier->name, 0, 255),
                // Контакты из ЕИС не затирают уже найденные: пишем, только если пусто.
                'email' => $company->email ?? $supplier->email,
                'phone' => $company->phone ?? $supplier->phone,
                'city' => $company->city ?? $supplier->city,
            ], fn (?string $value): bool => $value !== null))->save();

            return Contract::query()->updateOrCreate(
                ['law' => ProcurementLaw::Fz44, 'registry_number' => $contract->registryNumber],
                [
                    'notification_number' => $contract->notificationNumber,
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

        // Документация закупки ищется один раз, в фоне — после записи контракта.
        if ($saved->documents_checked_at === null && $saved->notification_number !== null) {
            $this->bus->dispatch(new FetchContractDocuments($saved->id));
        }
    }

    /**
     * Контракт больше не проходит отбор (правила уточнили) — убираем из ленты,
     * если по нему ещё ничего не делали: ни сделки, ни сметы.
     */
    private function forget(string $registryNumber): void
    {
        Contract::query()
            ->where('law', ProcurementLaw::Fz44)
            ->where('registry_number', $registryNumber)
            ->whereDoesntHave('deal')
            ->whereDoesntHave('estimate')
            ->delete();
    }
}
