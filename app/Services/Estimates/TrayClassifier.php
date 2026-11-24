<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\MaterialGroup;

/**
 * Относит материал из сметы к разделу «Лоток и комплектующие»: сам лоток
 * (перфорированный, неперфорированный, прочий), доборные элементы и крепёж,
 * металлические стяжки. Пластиковый короб (кабель-канал) сюда не входит.
 */
class TrayClassifier
{
    private const TRAY = '/лот(ок|к)|кабельрост/u';

    private const PLASTIC_DUCT = '/^короб|кабель-канал|пвх|пластик|полиамид|нейлон/u';

    private const FITTINGS = '/крышк|поворот|угол|тройник|крестовин|переходник|соединител|пластин|кронштейн|консол|подвес|профил|шпильк|заглушк|перегородк|держател|скоб|опор[аы]|стойк|анкер|винт|болт|гайк|шайб/u';

    /** Крепёж, который ставят под лоток, хотя в названии лотка нет. */
    private const SUPPORTS = '/^(кронштейн|консол|подвес|профил|шпильк|траверс|стойк)/u';

    /**
     * @param  bool  $withTrays  в смете есть лоток: тогда кронштейны, консоли, шпильки без слова «лоток» — тоже его крепёж
     */
    public function isTray(string $name): bool
    {
        $group = $this->classify($name, false);

        return $group !== null && $group !== MaterialGroup::Ties && $group !== MaterialGroup::Fittings;
    }

    public function classify(string $name, bool $withTrays = true): ?MaterialGroup
    {
        $lower = str_replace('ё', 'е', mb_strtolower($name));

        if (preg_match('/стяжк/u', $lower) === 1) {
            return preg_match('/металл|стал|нерж|оцинк/u', $lower) === 1 ? MaterialGroup::Ties : null;
        }

        if (preg_match(self::PLASTIC_DUCT, $lower) === 1 && preg_match(self::TRAY, $lower) !== 1) {
            return null;
        }

        if (preg_match(self::TRAY, $lower) === 1) {
            // «Крышка для лотка», «Поворот лотка» — доборные элементы, не сам лоток.
            if (preg_match('/^(\S+\s){0,1}\S*('.trim(self::FITTINGS, '/u').')/u', $lower) === 1) {
                return MaterialGroup::Fittings;
            }
            if (preg_match('/неперфор|сплошн|глух/u', $lower) === 1) {
                return MaterialGroup::TraySolid;
            }
            if (preg_match('/перфор/u', $lower) === 1) {
                return MaterialGroup::TrayPerforated;
            }

            return MaterialGroup::TrayOther;
        }

        return $withTrays && preg_match(self::SUPPORTS, $lower) === 1 ? MaterialGroup::Fittings : null;
    }
}
