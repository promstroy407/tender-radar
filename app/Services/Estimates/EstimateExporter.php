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

        $contract = $estimate->contract;
        $sheet->setCellValue('A1', 'Быстрая смета: '.$contract->subject);
        $sheet->setCellValue('A2', 'Только работы — по нашему прайсу. Материалы считаются отдельно. Для себя, не для отправки.');

        $headers = ['№', 'Наименование (из сметы заказчика)', 'Ед.', 'Кол-во', 'Наша позиция прайса', 'Цена работы, ₽', 'Работа, ₽'];
        $sheet->fromArray($headers, null, 'A4');

        $row = 5;
        foreach ($lines as $index => $line) {
            $skip = $line->match === LineMatch::Skip;
            $price = $skip ? null : $line->priceItem?->work_price_kopecks;

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

        $last = $row - 1;
        $sheet->setCellValue('B'.$row, 'Итого работы');
        $sheet->setCellValue('G'.$row, $last >= 5 ? '=SUM(G5:G'.$last.')' : 0);
        $sheet->setCellValue('B'.($row + 1), 'Сумма контракта (с материалами)');
        $sheet->setCellValue('G'.($row + 1), $contract->amount_kopecks / 100);
        $sheet->setCellValue('B'.($row + 2), 'Наши работы от суммы контракта');
        $sheet->setCellValue('G'.($row + 2), '=IF(G'.($row + 1).'=0,0,G'.$row.'/G'.($row + 1).')');

        $sheet->getStyle('A4:G4')->getFont()->setBold(true);
        $sheet->getStyle('B'.$row.':G'.($row + 2))->getFont()->setBold(true);
        $sheet->getStyle('D5:D'.max($last, 5))->getNumberFormat()->setFormatCode(self::QTY);
        $sheet->getStyle('F5:G'.($row + 1))->getNumberFormat()->setFormatCode(self::MONEY);
        $sheet->getStyle('G'.($row + 2))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);
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
