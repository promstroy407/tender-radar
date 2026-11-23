<?php

declare(strict_types=1);

namespace App\Services\Eis;

use App\Data\Eis\NoticeDocument;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;

/**
 * Файлы документации из XML извещения ЕИС (attachmentsInfo/attachmentInfo):
 * имя, ссылка на файл, описание и размер. Пути — по local-name(), без привязки
 * к версии схемы.
 */
class NoticeDocumentsParser
{
    /**
     * @return list<NoticeDocument>
     */
    public function parse(string $xml): array
    {
        $doc = new DOMDocument;
        if ($xml === '' || ! @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            return [];
        }

        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//*[*[local-name()="url"] and *[local-name()="fileName"]]');

        $documents = [];
        foreach ($nodes instanceof DOMNodeList ? $nodes : [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $url = $this->child($xpath, $node, 'url');
            $name = $this->child($xpath, $node, 'fileName');
            if ($url === null || $name === null || preg_match('#^https?://#i', $url) !== 1) {
                continue;
            }

            $size = $this->child($xpath, $node, 'fileSize');
            $description = $this->child($xpath, $node, 'docDescription');
            $documents[$url] = new NoticeDocument(
                name: mb_substr($name, 0, 255),
                url: mb_substr($url, 0, 1000),
                description: $description !== null ? mb_substr($description, 0, 500) : null,
                sizeBytes: $size !== null && ctype_digit($size) ? (int) $size : null,
            );
        }

        return array_values($documents);
    }

    private function child(DOMXPath $xpath, DOMElement $node, string $name): ?string
    {
        $list = $xpath->query('./*[local-name()="'.$name.'"]', $node);
        $child = $list instanceof DOMNodeList ? $list->item(0) : null;
        $value = $child instanceof DOMElement ? trim($child->textContent) : '';

        return $value === '' ? null : $value;
    }
}
