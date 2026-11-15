<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Estimate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class EstimateUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Estimate::class) ?? false;
    }

    /**
     * Принимаем всё, что бывает в документации: нечитаемые форматы покажем с объяснением.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:65536', 'extensions:xlsx,xls,xlsm,ods,csv,pdf,zip,rar,7z,gsfx,gsf,xml,doc,docx,dwg,dxf,jpg,jpeg,png,tif,tiff'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Выберите файлы документации.',
            'files.*.max' => 'Файл больше 64 МБ.',
            'files.*.extensions' => 'Такой тип файла не подходит.',
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploads(): array
    {
        /** @var array<int, UploadedFile> $files */
        $files = (array) $this->file('files', []);

        return array_values($files);
    }
}
