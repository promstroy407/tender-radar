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
 * решаем по предмету контракта, а коды ОКПД2 и позиции только дополняют его:
 * в сметах ремонта почти всегда есть «замена розеток», это ещё не электромонтаж.
 */
class ContractClassifier
{
    /** ОКПД2 профильных работ: электромонтаж (43.21), линии электропередачи и связи (42.22). */
    private const PROFILE_OKPD2 = ['43.21', '42.22'];

    /** ОКПД2 строительства зданий: генподряд, если в предмете есть работы. */
    private const BUILDING_OKPD2 = ['41.2'];

    /** Признаки подряда в предмете. */
    private const WORKS = '/монтаж|ремонт|устройств|замен|модерниз|прокладк|реконструк|строительств|оснащени|установк|переустройств|переклад|переоборудован/u';

    private const GENERAL_SUBJECT = '/капитальн\w*\s+ремонт|кап\.?\s+ремонт|реконструкци|строительств\w*\s+(?!материал)|строительно-монтажн|ремонт\w*\s+(?:здани|помещени|корпус)|переоборудован\w*\s+помещени|монтаж\w*\s+(?:модульн|служебн|быстровозводим)/u';

    /** Не подряд на монтаж: поставка, услуги, изготовление, проектирование, ИТ. */
    private const NOT_WORKS = [
        '/^поставк(?!.*(?:монтаж|установк))/u',
        '/^оказани\w*\s+услуг(?!.*монтаж)/u',
        '/^(?:выполнени\w*\s+работ\w*\s+по\s+)?изготовлени(?!.*монтаж)/u',
        '/информационн\w*\s+систем|программн\w*\s+обеспечени|аттестаци/u',
        '/^(?!.*(?:монтаж|ремонт|замен|строительств|реконструк|устройств|прокладк)).*(?:обслуживани|проектн|проектировани|\bпсд\b|\bпд\b|\bрд\b|изыскани|экспертиз|разработк)/u',
        '/(?:разработк|подготовк|актуализац)\w*\s+(?:\S+\s+){0,3}(?:проектн|\bпсд\b|\bпд\b|\bрд\b|рабоч\w*\s+документ|сметн|норматив)/u',
        '/^(?:аренд|возмещени|вывоз|уборк|содержани)/u',
    ];

    /** Генподряд не про наши разделы: окна, кровля, фасад, сети тепла и воды, дороги. */
    private const GENERAL_NOT_OURS = '/окон|оконн|витраж|кровл|фасад|крыльц|санитарн|санузл|асфальт|дорог|тротуар|ограждени|благоустройств|теплов\w*\s+сет|водопровод|водоснабж|канализац|отоплени|лифт|покраск|окраск|покрыти|площадк|парк|сквер|озеленен|снег/u';

    /** @var array<string, string> раздел => признак в тексте */
    private const SECTIONS = [
        'eom' => '/электро(?!нн|кардио|мобил)|освещени|электроснабж|кабельн\w*\s+лини|\bкл\s?-?\s?0[,.]4|\bвл\s?-?\s?\d|щит\w*\s+(?:учёт|учет|распред)|\bвру\b|\bтп\b|трансформаторн/u',
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
        $subject = mb_strtolower(trim($contract->subject));

        foreach (self::NOT_WORKS as $pattern) {
            if (preg_match($pattern, $subject) === 1) {
                return null;
            }
        }

        $texts = [$subject];
        foreach ($contract->products as $product) {
            $texts[] = mb_strtolower($product->name);
        }
        $allSections = $this->sections(implode("\n", $texts));
        $subjectSections = $this->sections($subject);

        if ($this->hasOkpd2($contract, self::PROFILE_OKPD2)
            || (preg_match(self::WORKS, $subject) === 1 && $subjectSections !== [])) {
            return new ContractClassification(ContractKind::Profile, $allSections !== [] ? $allSections : [WorkSection::Eom]);
        }

        $general = preg_match(self::GENERAL_SUBJECT, $subject) === 1
            || ($this->hasOkpd2($contract, self::BUILDING_OKPD2) && preg_match(self::WORKS, $subject) === 1);

        if ($general && preg_match(self::GENERAL_NOT_OURS, $subject) !== 1) {
            return new ContractClassification(ContractKind::General, $allSections !== [] ? $allSections : self::GENERAL_DEFAULT);
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
