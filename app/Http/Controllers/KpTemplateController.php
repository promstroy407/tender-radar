<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\KpTemplateRequest;
use App\Services\ContractService;
use App\Services\KpTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Экран «Шаблон КП»: текст письма, вложение, дневной лимит и предпросмотр.
 */
class KpTemplateController extends Controller
{
    public function __construct(
        private readonly KpTemplateService $template,
        private readonly ContractService $contracts,
    ) {}

    public function edit(): View
    {
        // Предпросмотр на самом свежем контракте из ленты, если он есть.
        $sample = $this->contracts->latest();

        return view('template.edit', [
            'subject' => $this->template->subject(),
            'body' => $this->template->body(),
            'dailyLimit' => $this->template->dailyLimit(),
            'fields' => KpTemplateService::FIELDS,
            'hasAttachment' => $this->template->hasAttachment(),
            'attachmentName' => KpTemplateService::ATTACHMENT_NAME,
            'missingAttachment' => $this->template->promisesMissingAttachment(),
            'sample' => $sample,
            'preview' => $sample !== null ? $this->template->render($sample) : null,
        ]);
    }

    public function update(KpTemplateRequest $request): RedirectResponse
    {
        $this->template->save($request->subject(), $request->body(), $request->dailyLimit());

        $file = $request->attachment();
        if ($file !== null) {
            $this->template->storeAttachment($file);
        } elseif ($request->boolean('remove_attachment')) {
            $this->template->deleteAttachment();
        }

        return redirect()->route('template')->with('status', 'Шаблон сохранён.');
    }
}
