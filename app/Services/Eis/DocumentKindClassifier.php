<?php

declare(strict_types=1);

namespace App\Services\Eis;

use App\Enums\DocumentKind;

/**
 * Что за файл документации — по имени и описанию из извещения. Для просчёта нужны
 * смета, ведомость работ и проект; проект контракта, требования к заявке, реквизиты,
 * ответы на запросы и прочие бумаги закупки — мимо.
 */
class DocumentKindClassifier
{
    /** Бумаги закупки: проверяются первыми («проект контракта» — не проект). */
    private const OTHER = '/проект\w*\s+(?:государственн\w*\s+|муниципальн\w*\s+)?(?:контракт|договор)|требовани|реквизит|обеспечени|ответ\w*\s+на\s+запрос|разъяснени|протокол|изменени|заявк|акт\w*\s+при[её]м|описани\w*\s+объект|техническ\w*\s+задани|\bтз\b|инструкци|обоснован|\bнмцк\b|коммерческ|форм\w*\s+(?:заявк|предложени)|согласи|декларац/u';

    private const ESTIMATE = '/смет|\bлср\b|\bлсрр?\b|\bсср\b|\bор\b|сводн\w*\s+сметн|ресурсн\w*\s+расч|пусконалад|\bпнр\b|\bсм[\s\-_.]/u';

    private const WORKS_LIST = '/ведомост|\bвор\b|объ[её]м\w*\s+работ|перечень\s+(?:работ|дефект)|дефект/u';

    private const PROJECT = '/проект|рабоч\w*\s+документац|\bрд\b|\bпд\b|чертеж|чертёж|схем|спецификац|\b(?:эом|эм|эо|эс|эн|сс|скс|скуд|сот|свн|апс|спс|соуэ|ос|аупс|сб|сод|ло|асутп|кж|ар|иос\d*|том)\b/u';

    public function classify(string $name, ?string $description = null): DocumentKind
    {
        $text = mb_strtolower(str_replace(['_', '.'], ' ', $name.' '.($description ?? '')));

        return match (true) {
            preg_match(self::OTHER, $text) === 1 => DocumentKind::Other,
            preg_match(self::ESTIMATE, $text) === 1 => DocumentKind::Estimate,
            preg_match(self::WORKS_LIST, $text) === 1 => DocumentKind::WorksList,
            preg_match(self::PROJECT, $text) === 1 => DocumentKind::Project,
            default => DocumentKind::Other,
        };
    }
}
