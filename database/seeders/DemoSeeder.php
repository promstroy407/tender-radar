<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContractKind;
use App\Enums\ProcurementLaw;
use App\Enums\WorkSection;
use App\Models\Company;
use App\Models\Contract;
use Illuminate\Database\Seeder;

/**
 * Демо-данные для показа экранов до подключения ЕИС.
 * Все компании и номера вымышленные и помечены «[Демо]».
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RegionSeeder::class);

        $rows = [
            ['77', ProcurementLaw::Fz44, ContractKind::Profile, 'Монтаж системы видеонаблюдения и СКУД в поликлинике', 325_000_000, [WorkSection::Video, WorkSection::Skud], false],
            ['16', ProcurementLaw::Fz44, ContractKind::General, 'Капитальный ремонт здания школы', 1_840_000_000, [WorkSection::Eom, WorkSection::Ss, WorkSection::Aps], true],
            ['50', ProcurementLaw::Fz223, ContractKind::General, 'Реконструкция административного здания', 4_190_000_000, [WorkSection::Eom, WorkSection::Sks, WorkSection::Soue], true],
            ['21', ProcurementLaw::Fz44, ContractKind::Profile, 'Замена электропроводки и освещения в детском саду', 187_000_000, [WorkSection::Eom], false],
            ['12', ProcurementLaw::Fz44, ContractKind::General, 'Капитальный ремонт здания администрации', 510_000_000, [WorkSection::Eom, WorkSection::Aps], false],
        ];

        foreach ($rows as $i => [$region, $law, $kind, $subject, $amountKopecks, $sections, $smp]) {
            $number = $i + 1;

            $company = Company::query()->firstOrCreate(
                ['inn' => '000000000'.$number],
                ['name' => 'ООО «[Демо] Победитель '.$number.'»', 'email' => 'demo'.$number.'@example.invalid', 'city' => 'Демо-город'],
            );

            Contract::query()->firstOrCreate(
                ['law' => $law, 'registry_number' => 'DEMO-'.$number],
                [
                    'kind' => $kind,
                    'region_code' => $region,
                    'subject' => '[Демо] '.$subject,
                    'customer_name' => '[Демо] Заказчик '.$number,
                    'amount_kopecks' => $amountKopecks,
                    'signed_at' => now()->subDays($number)->toDateString(),
                    'ends_at' => now()->addMonths(6)->toDateString(),
                    'sections' => $sections,
                    'requires_smp_subcontract' => $smp,
                    'company_id' => $company->id,
                ],
            );
        }
    }
}
