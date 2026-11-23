<?php

declare(strict_types=1);

namespace App\Services\Eis;

use ZipArchive;

/**
 * Чтение zip-архивов, которые отдаёт ЕИС: из них нужны только XML-документы.
 */
class EisArchiveReader
{
    /**
     * @return iterable<string> содержимое XML-файлов архива
     */
    public function xmlFiles(string $zipBytes): iterable
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'eis');
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
