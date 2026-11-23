<?php

declare(strict_types=1);

namespace App\Services\Eis;

use App\Data\Eis\ContractClassification;
use App\Data\Eis\ParsedContract;
use App\Enums\ContractKind;
use App\Enums\WorkSection;

/**
 * Наш ли контракт: профильный (электромонтаж, слаботочка, пожарка) или генподряд
 * (стройка, реконструкция, капремонт). Документации закупки в реестре контрактов нет —
 * решаем по предмету контракта и кодам ОКПД2 позиций.
 */
class ContractClassifier
{
    /** ОКПД2 профильных работ: электромонтаж (43.21), линии электропередачи и связи (42.22). */
    private const PROFILE_OKPD2 = ['43.21', '42.22'];

    /** ОКПД2 генподряда: здания (41.2), отделка (43.3), прочие строительные (43.99, 42.99). */
    private const GENERAL_OKPD2 = ['41.2', '43.3', '43.99', '42.99'];

    /** Без работ это поставка или услуга, а не подряд. */
    private const WORKS = '/монтаж|ремонт|устройств|замен|модерниз|прокладк|реконструк|строительств|оснащени|установк|переустройств|переклад/u';

    private const GENERAL_SUBJECT = '/капитальн\w*\s+ремонт|реконструкци|строительств\w*\s+(?!материал)|ремонт\w*\s+(?:здани|помещени|корпус|кровл|фасад)/u';

    /** Только обслуживание или проектирование, без работ — субподряд на монтаж не нужен. */
    private const EXCLUDE = '/^(?!.*(?:монтаж|ремонт|замен|строительств|реконструк|устройств|прокладк)).*(?:обслуживани|проектн|проектировани|изыскани|экспертиз)/us';

    /** @var array<string, string> раздел => признак в тексте */
    private const SECTIONS = [
        'eom' => '/электро(?!нн)|освещени|электроснабж|кабельн\w*\s+лини|\bкл\s?-?\s?0[,.]4|\bвл\s?-?\s?\d|щит\w*\s+(?:учёт|учет|распред)|вру\b|\bтп\b|трансформаторн/u',
        'ss' => '/сет\w*\s+связ|телефони|радиофикац|телевиден|\bсс\b/u',
        'aps' => '/пожарн\w*\s+сигнал|\bапс\b|\bаупс\b|пожарн\w*\s+автоматик|систем\w*\s+пожар/u',
        'soue' => '/оповещени|\bсоуэ\b/u',
        'skud' => '/\bскуд\b|контрол\w*\s+(?:и\s+управлени\w*\s+)?доступ/u',
        'video' => '/видеонаблюд|видеокамер|\bсот\b/u',
        'sks' => '/\bскс\b|структурированн|кабельн\w*\s+систем|локальн\w*\s+(?:вычислительн\w*\s+)?сет|\bлвс\b/u',
    ];

    /** Для генподряда без признаков разделов — то, что мы предлагаем всегда. */
    private const GENERAL_DEFAULT = [WorkSection::Eom, WorkSection::Ss, WorkSection::Aps];

    public function classify(ParsedContract $contract): ?ContractClassification
    {
        $texts = [$contract->subject];
        foreach ($contract->products as $product) {
            $texts[] = $product->name;
        }
        $text = mb_strtolower(implode("\n", $texts));

        if (preg_match(self::EXCLUDE, mb_strtolower($contract->subject)) === 1) {
            return null;
        }

        $sections = $this->sections($text);
        $hasWorks = preg_match(self::WORKS, $text) === 1;

        if ($this->hasOkpd2($contract, self::PROFILE_OKPD2) || ($hasWorks && $sections !== [])) {
            if ($sections === []) {
                $sections = [WorkSection::Eom];
            }

            return new ContractClassification(ContractKind::Profile, $sections);
        }

        if ($this->hasOkpd2($contract, self::GENERAL_OKPD2) || preg_match(self::GENERAL_SUBJECT, $text) === 1) {
            return new ContractClassification(ContractKind::General, $sections !== [] ? $sections : self::GENERAL_DEFAULT);
        }

        return null;
    }

    /**
     * @return list<WorkSection>
     */
    private function sections(string $text): array
    {
        $found = [];
        foreach (self::SECTIONS as $section => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $found[] = WorkSection::from($section);
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function hasOkpd2(ParsedContract $contract, array $prefixes): bool
    {
        foreach ($contract->products as $product) {
            foreach ($prefixes as $prefix) {
                if ($product->okpd2 !== null && str_starts_with($product->okpd2, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
