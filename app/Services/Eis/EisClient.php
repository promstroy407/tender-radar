<?php

declare(strict_types=1);

namespace App\Services\Eis;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Str;
use Throwable;

/**
 * Клиент сервиса отдачи информации ЕИС getDocsIP (SOAP).
 * Токен физлица передаётся в заголовке SOAP (individualPerson_token),
 * а при скачивании архива — в HTTP-заголовке с тем же именем.
 */
class EisClient
{
    private const NS = 'http://zakupki.gov.ru/fz44/get-docs-ip/ws';

    private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

    private const TOKEN_HEADER = 'individualPerson_token';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly EisConfig $config,
    ) {}

    /**
     * Документы региона за дату. Возвращает ссылки на архивы (их может быть несколько).
     *
     * @return list<string>
     */
    public function docsByOrgRegion(string $orgRegion, string $subsystemType, string $documentType, string $exactDate): array
    {
        $xml = $this->envelope('getDocsByOrgRegionRequest', [
            'orgRegion' => $orgRegion,
            'subsystemType' => $subsystemType,
            'documentType44' => $documentType,
        ], $exactDate);

        return $this->archiveUrls($this->post($xml));
    }

    public function downloadArchive(string $url): string
    {
        try {
            $response = $this->http
                ->timeout(120)
                ->withHeaders([self::TOKEN_HEADER => $this->config->requireToken()])
                ->get($url);
        } catch (EisException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new EisException('ЕИС: не удалось скачать архив: '.$this->mask($e->getMessage()));
        }

        if (! $response->successful()) {
            throw new EisException('ЕИС: архив не скачан, HTTP '.$response->status());
        }

        return $response->body();
    }

    /** XSD сервиса — в нём перечислены допустимые подсистемы и типы документов. */
    public function schema(string $name = 'getDocsIP-ws-api.xsd'): string
    {
        try {
            $response = $this->http->timeout(30)->get($this->config->url44, ['xsd' => $name]);
        } catch (Throwable $e) {
            throw new EisException('ЕИС недоступен: '.$this->mask($e->getMessage()));
        }

        if (! $response->successful()) {
            throw new EisException('ЕИС: схема '.$name.' не получена, HTTP '.$response->status());
        }

        return $response->body();
    }

    /**
     * @param  array<string, string>  $selection  порядок полей важен: так требует схема
     */
    public function envelope(string $method, array $selection, string $exactDate): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $envelope = $doc->createElementNS(self::SOAP_NS, 'soapenv:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ws', self::NS);
        $doc->appendChild($envelope);

        $header = $doc->createElementNS(self::SOAP_NS, 'soapenv:Header');
        $header->appendChild($doc->createElement(self::TOKEN_HEADER, $this->config->requireToken()));
        $envelope->appendChild($header);

        $body = $doc->createElementNS(self::SOAP_NS, 'soapenv:Body');
        $request = $doc->createElementNS(self::NS, 'ws:'.$method);
        $body->appendChild($request);
        $envelope->appendChild($body);

        $index = $doc->createElement('index');
        $index->appendChild($doc->createElement('id', (string) Str::uuid()));
        $index->appendChild($doc->createElement('createDateTime', now()->format('Y-m-d\TH:i:s')));
        $index->appendChild($doc->createElement('mode', 'PROD'));
        $request->appendChild($index);

        $params = $doc->createElement('selectionParams');
        foreach ($selection as $name => $value) {
            $element = $doc->createElement($name);
            $element->appendChild($doc->createTextNode($value));
            $params->appendChild($element);
        }
        $period = $doc->createElement('periodInfo');
        $period->appendChild($doc->createElement('exactDate', $exactDate));
        $params->appendChild($period);
        $request->appendChild($params);

        return (string) $doc->saveXML();
    }

    private function post(string $xml): string
    {
        try {
            $response = $this->http
                ->timeout(60)
                ->withBody($xml, 'text/xml; charset=utf-8')
                ->post($this->config->url44);
        } catch (Throwable $e) {
            throw new EisException('ЕИС недоступен: '.$this->mask($e->getMessage()));
        }

        $body = $response->body();
        $fault = $this->firstText($body, 'faultstring') ?? $this->firstText($body, 'errorInfo');

        if ($fault !== null) {
            throw new EisException('ЕИС ответил ошибкой: '.$this->mask($fault));
        }

        if (! $response->successful()) {
            throw new EisException('ЕИС ответил HTTP '.$response->status());
        }

        return $body;
    }

    /**
     * @return list<string>
     */
    private function archiveUrls(string $xml): array
    {
        $xpath = $this->xpath($xml);

        $urls = [];
        foreach ($xpath?->query('//*[local-name()="archiveUrl"]') ?: [] as $node) {
            $url = trim($node->textContent);
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function firstText(string $xml, string $localName): ?string
    {
        $node = $this->xpath($xml)?->query('//*[local-name()="'.$localName.'"]')?->item(0);

        if ($node === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');

        return $text === '' ? null : $text;
    }

    private function xpath(string $xml): ?DOMXPath
    {
        if (trim($xml) === '') {
            return null;
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? new DOMXPath($doc) : null;
    }

    public function mask(string $text): string
    {
        $token = $this->config->token;

        return $token !== null && $token !== '' ? str_replace($token, '***', $text) : $text;
    }
}
