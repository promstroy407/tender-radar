<?php

declare(strict_types=1);

namespace App\Services\Estimates;

use App\Enums\EstimateFileStatus;
use App\Enums\LineMatch;
use App\Jobs\ParseEstimateFile;
use App\Models\Contract;
use App\Models\Estimate;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Быстрая смета тендера: файлы документации, их разбор и ручные поправки.
 */
class EstimateService
{
    /** Из ZIP берём не больше файлов и не больше 64 МБ каждый. */
    private const ZIP_MAX_FILES = 50;

    private const ZIP_MAX_BYTES = 64 * 1024 * 1024;

    public function __construct(
        private readonly FilesystemFactory $storage,
        private readonly Dispatcher $bus,
        private readonly DocumentReader $reader,
    ) {}

    public function forContract(Contract $contract, User $user): Estimate
    {
        return Estimate::query()->firstOrCreate(['contract_id' => $contract->id], ['user_id' => $user->id]);
    }

    /**
     * Загружает файлы; ZIP раскрывается в отдельные файлы. Разбор идёт в фоне.
     *
     * @param  list<UploadedFile>  $uploads
     * @return list<EstimateFile>
     */
    public function addUploads(Estimate $estimate, array $uploads): array
    {
        $files = [];
        foreach ($uploads as $upload) {
            $name = $upload->getClientOriginalName();
            $extension = strtolower($upload->getClientOriginalExtension());

            if ($extension === 'zip') {
                array_push($files, ...$this->addZip($estimate, (string) $upload->getRealPath(), $name));

                continue;
            }

            $path = $this->storage->disk('local')->putFileAs($this->folder($estimate), $upload, Str::uuid()->toString().'.'.$extension);
            $files[] = $this->register($estimate, $name, (string) $path, (int) $upload->getSize());
        }

        return $files;
    }

    public function reparse(EstimateFile $file): void
    {
        $file->forceFill(['status' => EstimateFileStatus::Pending, 'error' => null])->save();
        $this->bus->dispatch(new ParseEstimateFile($file));
    }

    public function deleteFile(EstimateFile $file): void
    {
        $this->storage->disk('local')->delete($file->path);
        $file->delete();
    }

    /** Ручное сопоставление: позиция прайса, «не считать» или вернуть автоматическое. */
    public function setLineMatch(EstimateLine $line, ?PriceItem $item, bool $skip): EstimateLine
    {
        $line->forceFill([
            'price_item_id' => $skip ? null : $item?->id,
            'match' => $skip ? LineMatch::Skip : LineMatch::Manual,
        ])->save();

        return $line;
    }

    /**
     * @return list<EstimateFile>
     */
    private function addZip(Estimate $estimate, string $zipPath, string $zipName): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return [$this->failed($estimate, $zipName, 'ZIP-архив не открылся.')];
        }

        $files = [];
        for ($i = 0; $i < $zip->numFiles && count($files) < self::ZIP_MAX_FILES; $i++) {
            $stat = $zip->statIndex($i, ZipArchive::FL_ENC_RAW);
            if ($stat === false || str_ends_with($stat['name'], '/')) {
                continue;
            }

            $name = $this->zipEntryName($stat['name']);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $label = $zipName.' → '.$name;

            if (! in_array($extension, DocumentReader::SUPPORTED_EXTENSIONS, true)) {
                $files[] = $this->failed($estimate, $label, $this->reader->unsupportedMessage($extension));

                continue;
            }

            if ($stat['size'] > self::ZIP_MAX_BYTES) {
                $files[] = $this->failed($estimate, $label, 'Файл в архиве больше 64 МБ.');

                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            $path = $this->folder($estimate).'/'.Str::uuid()->toString().'.'.$extension;
            $this->storage->disk('local')->put($path, $content);
            $files[] = $this->register($estimate, $label, $path, strlen($content));
        }
        $zip->close();

        return $files;
    }

    private function register(Estimate $estimate, string $name, string $path, int $size): EstimateFile
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, DocumentReader::SUPPORTED_EXTENSIONS, true)) {
            $this->storage->disk('local')->delete($path);

            return $this->failed($estimate, $name, $this->reader->unsupportedMessage($extension));
        }

        $file = EstimateFile::query()->create([
            'estimate_id' => $estimate->id,
            'original_name' => mb_substr($name, 0, 255),
            'path' => $path,
            'size_bytes' => $size,
            'status' => EstimateFileStatus::Pending,
        ]);

        $this->bus->dispatch(new ParseEstimateFile($file));

        return $file;
    }

    /** Файл, который не читаем, всё равно показываем — с объяснением почему. */
    private function failed(Estimate $estimate, string $name, string $message): EstimateFile
    {
        return EstimateFile::query()->create([
            'estimate_id' => $estimate->id,
            'original_name' => mb_substr($name, 0, 255),
            'path' => '',
            'size_bytes' => 0,
            'status' => EstimateFileStatus::Failed,
            'error' => $message,
        ]);
    }

    /** Имена в ZIP из Windows часто в кодировке CP866. */
    private function zipEntryName(string $raw): string
    {
        $name = mb_check_encoding($raw, 'UTF-8') ? $raw : (string) mb_convert_encoding($raw, 'UTF-8', 'CP866');

        return basename(str_replace('\\', '/', $name));
    }

    private function folder(Estimate $estimate): string
    {
        return 'estimates/'.$estimate->id;
    }
}
