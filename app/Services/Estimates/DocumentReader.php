<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use Illuminate\Process\Factory as ProcessFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Читает файл документации в строки таблицы: листы Excel (xlsx, xls, ods, csv)
 * или текст PDF, разбитый на колонки по широким пробелам; скан PDF распознаётся (Tesseract).
 */
class DocumentReader
{
    public const TABLE_EXTENSIONS = ['xlsx', 'xls', 'xlsm', 'ods', 'csv'];

    public const SUPPORTED_EXTENSIONS = [...self::TABLE_EXTENSIONS, 'pdf'];

    /** Не читаем больше строк с одного листа: сметы крупнее — редкость, а память не бесконечна. */
    private const MAX_ROWS = 20000;

    /** Смете хватает трёх десятков колонок; оформление «на весь лист» (до XFD) не читаем. */
    private const MAX_COLUMNS = 40;

    /**
     * Страниц скана на распознавание: первая (шапка) и последние (итоги сметы).
     * Страница — 5–15 секунд работы сервера, а сканов в документации много.
     */
    private const OCR_MAX_PAGES = 8;

    /** Меньше стольких букв и цифр на страницу — это скан (бывает лишь штамп или номер листа). */
    private const SCAN_MAX_CHARS_PER_PAGE = 30;

    public function __construct(
        private readonly ProcessFactory $process,
    ) {}

    /**
     * Таблицы файла: по одной на лист Excel или одна на весь PDF.
     *
     * @return list<list<list<string|int|float|null>>>
     */
    public function read(string $path, string $extension): array
    {
        return match (true) {
            in_array($extension, self::TABLE_EXTENSIONS, true) => $this->readSpreadsheet($path),
            $extension === 'pdf' => [$this->readPdf($path)],
            default => throw new EstimateReadException($this->unsupportedMessage($extension)),
        };
    }

    public function unsupportedMessage(string $extension): string
    {
        return match ($extension) {
            'gsfx', 'gsf', 'xml' => 'Формат ГРАНД-Сметы пока не читается. Выгрузите смету в Excel (в ГРАНД-Смете: «Файл → Экспорт в Excel») и загрузите её.',
            'dwg', 'dxf', 'jpg', 'jpeg', 'png', 'tif', 'tiff' => 'Чертежи и сканы пока не считаются — это следующий этап (распознавание проекта).',
            'doc', 'docx' => 'Word пока не читается: сохраните таблицу в Excel или PDF.',
            'rar', '7z' => 'Архивы RAR и 7z не открываются — распакуйте и загрузите файлы или ZIP.',
            default => 'Файлы .'.$extension.' не читаются. Подходят Excel, CSV, PDF и ZIP с ними.',
        };
    }

    /**
     * @return list<list<list<string|int|float|null>>>
     */
    private function readSpreadsheet(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (Throwable $e) {
            throw new EstimateReadException('Таблица не открылась: '.$e->getMessage());
        }

        $tables = [];
        foreach ($book->getAllSheets() as $sheet) {
            $lastRow = min($sheet->getHighestDataRow(), self::MAX_ROWS);
            $lastColumn = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), self::MAX_COLUMNS);

            $rows = [];
            for ($r = 1; $r <= $lastRow; $r++) {
                $cells = [];
                for ($c = 1; $c <= $lastColumn; $c++) {
                    // Пустые ячейки не создаём: getCell() на пустом месте раздувает память.
                    $value = null;
                    if ($sheet->cellExists([$c, $r])) {
                        $cell = $sheet->getCell([$c, $r]);
                        try {
                            $value = $cell->getCalculatedValue();
                        } catch (Throwable) {
                            $value = $cell->getValue();
                        }
                    }
                    $cells[] = is_string($value) || is_int($value) || is_float($value) ? $value : null;
                }
                $rows[] = $cells;
            }
            $tables[] = $rows;
        }
        $book->disconnectWorksheets();

        return $tables;
    }

    /**
     * @return list<list<string>>
     */
    private function readPdf(string $path): array
    {
        $result = $this->process->newPendingProcess()->timeout(120)->run(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);

        if (! $result->successful()) {
            throw new EstimateReadException('PDF не прочитан: '.trim($result->errorOutput()));
        }

        $text = $result->output();
        // Скан: текста нет совсем или есть только разрывы страниц и штамп — распознаём
        // (Tesseract). Строки — «подпись | число | число».
        $pages = max(1, substr_count($text, "\f"));
        if (intdiv((int) preg_match_all('/[\p{L}\p{N}]/u', $text), $pages) < self::SCAN_MAX_CHARS_PER_PAGE) {
            return $this->ocrRows($path);
        }

        $rows = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            // Колонки в тексте PDF разделены двумя и более пробелами.
            $rows[] = array_map('trim', preg_split('/\s{2,}/u', trim($line)) ?: []);
        }

        return $rows;
    }

    /**
     * Распознаёт скан PDF постранично. Длинные сметы — первая и последние страницы:
     * итоги («Всего по смете», материалы, оборудование) стоят в конце.
     *
     * @return list<list<string>>
     */
    private function ocrRows(string $path): array
    {
        $pages = $this->pageCount($path);
        if ($pages === 0) {
            throw new EstimateReadException('PDF не прочитан: не удалось узнать число страниц.');
        }

        $selected = $pages <= self::OCR_MAX_PAGES
            ? range(1, $pages)
            : [1, ...range($pages - self::OCR_MAX_PAGES + 2, $pages)];

        $dir = sys_get_temp_dir().'/ocr-'.bin2hex(random_bytes(6));
        @mkdir($dir);
        $rows = [];

        try {
            foreach ($selected as $page) {
                $image = $dir.'/page-'.$page;
                $this->process->newPendingProcess()->timeout(120)->run([
                    'pdftoppm', '-r', '200', '-gray', '-png', '-f', (string) $page, '-l', (string) $page, '-singlefile', $path, $image,
                ]);
                // --psm 1: сам определяет поворот страницы — сметы часто сканируют боком.
                $result = $this->process->newPendingProcess()->timeout(180)->run([
                    'tesseract', $image.'.png', 'stdout', '-l', 'rus+eng', '--psm', '1',
                ]);
                @unlink($image.'.png');

                if (! $result->successful()) {
                    throw new EstimateReadException('Скан не распознан: '.trim($result->errorOutput()));
                }

                foreach (preg_split('/\R/u', $result->output()) ?: [] as $line) {
                    $row = $this->ocrRow($line);
                    if ($row !== []) {
                        $rows[] = $row;
                    }
                }
            }
        } finally {
            @rmdir($dir);
        }

        if ($rows === []) {
            throw new EstimateReadException('Скан не распознан: на страницах нет текста — похоже, это чертёж.');
        }

        return $rows;
    }

    /**
     * Строка распознанного текста → [подпись, число, число…]: распознавание не
     * сохраняет колонки, а для итогов сметы нужны подпись и суммы после неё.
     *
     * @return list<string>
     */
    public function ocrRow(string $line): array
    {
        $line = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $line) ?? '');
        // Распознавание дорисовывает в конце строки скобки и черту от линий таблицы.
        $line = rtrim($line, ' )]}|');
        if ($line === '') {
            return [];
        }

        // Сумма с разрядами через пробел; распознавание часто склеивает первые два
        // разряда («7840 541,41» = 7 840 541,41), поэтому первый разряд — до 4 цифр.
        $number = '\d{1,4}(?: \d{3})+(?:[.,]\d{1,2})?|\d+(?:[.,]\d{1,2})?';
        if (preg_match('/^(.*?[^\d\s.,])?\s*((?:(?:'.$number.')\s*)+)$/u', $line, $m) !== 1 || trim($m[2]) === '') {
            return [$line];
        }

        preg_match_all('/'.$number.'/u', $m[2], $numbers);

        return [trim($m[1]), ...$numbers[0]];
    }

    private function pageCount(string $path): int
    {
        $result = $this->process->newPendingProcess()->timeout(30)->run(['pdfinfo', $path]);

        return preg_match('/^Pages:\s+(\d+)/m', $result->output(), $m) === 1 ? (int) $m[1] : 0;
    }
}
