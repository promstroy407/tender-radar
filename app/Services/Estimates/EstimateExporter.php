<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\LineMatch;
use App\Models\Estimate;
use App\Models\EstimateLine;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Выгрузка быстрой сметы (только работы) в Excel. Суммы строк и итоги — формулами,
 * чтобы правка количества или цены в Excel сразу пересчитывала итог.
 */
class EstimateExporter
{
    public function __construct(
        private readonly PriceResolver $prices,
    ) {}

    private const MONEY = '#,##0.00';

    private const QTY = '#,##0.###';

    /**
     * @param  Collection<int, EstimateLine>  $lines  с загруженным priceItem
     */
    public function toXlsx(Estimate $estimate, Collection $lines, string $path): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Смета');

        $regionCode = $estimate->regionCode();
        $sheet->setCellValue('A1', 'Быстрая смета: '.$estimate->title());
        $sheet->setCellValue('A2', 'Только работы: по нормативам ГЭСН и ставкам труда региона (ФГИС ЦС) с НР и СП, без машин; где нормы нет — по цене прайса. Материалы отдельно. Для себя, не для отправки.');

        $headers = ['№', 'Наименование (из сметы заказчика)', 'Ед.', 'Кол-во', 'Наша позиция прайса', 'Цена работы, ₽ (ГЭСН по региону или вручную)', 'Работа, ₽'];
        $sheet->fromArray($headers, null, 'A4');

        $row = 5;
        foreach ($lines as $index => $line) {
            $skip = $line->match === LineMatch::Skip;
            $price = $skip || $line->priceItem === null ? null : $this->prices->unitPrice($line->priceItem, $regionCode);

            $sheet->setCellValue('A'.$row, $index + 1);
            $sheet->setCellValue('B'.$row, $line->name);
            $sheet->setCellValue('C'.$row, $line->unit ?? '');
            // Количество хранится в тысячных — для Excel показываем как обычное число.
            $sheet->setCellValue('D'.$row, $line->quantity_milli / 1000);
            $sheet->setCellValue('E'.$row, $skip ? 'не считать' : ($line->priceItem === null ? 'не сопоставлено' : $line->priceItem->name));
            $sheet->setCellValue('F'.$row, $price === null ? null : $price / 100);
            $sheet->setCellValue('G'.$row, '=IF(F'.$row.'="",0,ROUND(D'.$row.'*F'.$row.',2))');
            $row++;
        }

        // Работы из нормативных смет заказчика: итог − материалы − оборудование, по файлам.
        $normative = 0;
        foreach ($estimate->files as $file) {
            $normative += $file->normativeTotals()?->worksKopecks() ?? 0;
        }

        $last = $row - 1;
        $sheet->setCellValue('B'.$row, 'Работы по позициям (прайс)');
        $sheet->setCellValue('G'.$row, $last >= 5 ? '=SUM(G5:G'.$last.')' : 0);
        $sheet->setCellValue('B'.($row + 1), 'Работы по сметам заказчика (нормативы, без материалов и НДС)');
        $sheet->setCellValue('G'.($row + 1), $normative / 100);
        // Если есть смета заказчика, позиции — её же работы по нашему прайсу: не складываем, берём смету.
        $sheet->setCellValue('B'.($row + 2), 'Итого работы (по смете заказчика, если она есть)');
        $sheet->setCellValue('G'.($row + 2), '=IF(G'.($row + 1).'>0,G'.($row + 1).',G'.$row.')');
        $sheet->setCellValue('B'.($row + 3), 'Сумма контракта (с материалами)');
        $sheet->setCellValue('G'.($row + 3), ($estimate->contract->amount_kopecks ?? 0) / 100);
        $sheet->setCellValue('B'.($row + 4), 'Работы от суммы контракта');
        $sheet->setCellValue('G'.($row + 4), '=IF(G'.($row + 3).'=0,0,G'.($row + 2).'/G'.($row + 3).')');

        $sheet->getStyle('A4:G4')->getFont()->setBold(true);
        $sheet->getStyle('B'.($row + 2).':G'.($row + 4))->getFont()->setBold(true);
        $sheet->getStyle('D5:D'.max($last, 5))->getNumberFormat()->setFormatCode(self::QTY);
        $sheet->getStyle('F5:G'.($row + 3))->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle('G'.($row + 4))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);
        $sheet->getColumnDimension('B')->setWidth(60);
        $sheet->getColumnDimension('E')->setWidth(40);
        foreach (['A', 'C', 'D', 'F', 'G'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A5');

        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }
}
