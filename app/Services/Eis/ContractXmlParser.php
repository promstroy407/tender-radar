<?php

declare(strict_types=1);

namespace App\Services\Eis;

use App\Data\Eis\ParsedContract;
use App\Data\Eis\ParsedProduct;
use App\Data\Eis\ParsedSupplier;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Illuminate\Support\Carbon;

/**
 * Разбор XML контракта из реестра контрактов ЕИС (export/contract, схема 16.x).
 * Пространства имён у ЕИС меняются от версии к версии, поэтому пути — по local-name().
 */
class ContractXmlParser
{
    /** null — это не контракт или в нём нет обязательных полей (номер, цена, дата). */
    public function parse(string $xml): ?ParsedContract
    {
        $doc = new DOMDocument;
        if ($xml === '' || ! @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            return null;
        }

        $xpath = new DOMXPath($doc);
        $contract = $this->node($xpath, '//*[local-name()="contract"][*[local-name()="regNum"]]', $doc);

        if (! $contract instanceof DOMElement) {
            return null;
        }

        $registryNumber = $this->text($xpath, 'regNum', $contract);
        $price = $this->text($xpath, 'priceInfo/price', $contract);
        $signDate = $this->text($xpath, 'signDate', $contract);
        $kopecks = $price !== null ? $this->kopecks($price) : null;

        if ($registryNumber === null || $kopecks === null || $signDate === null) {
            return null;
        }

        $endDate = $this->text($xpath, 'executionPeriod/endDate', $contract);

        return new ParsedContract(
            registryNumber: $registryNumber,
            // Номер извещения о закупке: по нему ищется документация (проект, смета, ТЗ).
            notificationNumber: $this->first($xpath, './*[local-name()="foundation"]//*[local-name()="notificationNumber"]', $contract),
            subject: $this->text($xpath, 'contractSubject', $contract) ?? '',
            customerName: $this->text($xpath, 'customer/fullName', $contract)
                ?? $this->text($xpath, 'customer/shortName', $contract) ?? '',
            customerInn: $this->text($xpath, 'customer/inn', $contract),
            amountKopecks: $kopecks,
            signedAt: Carbon::parse(substr($signDate, 0, 10))->startOfDay(),
            endsAt: $endDate !== null ? Carbon::parse(substr($endDate, 0, 10))->startOfDay() : null,
            supplier: $this->supplier($xpath, $contract),
            products: $this->products($xpath, $contract),
        );
    }

    /**
     * Рубли из ЕИС («43267.00», «8.6534») → целые копейки, без float; дробь округляется
     * до копейки половиной вверх.
     */
    public function kopecks(string $value): ?int
    {
        if (preg_match('/^(\d{1,15})(?:\.(\d+))?$/', trim($value), $m) !== 1) {
            return null;
        }

        $fraction = str_pad($m[2] ?? '', 3, '0');
        $kopecks = (int) $m[1] * 100 + (int) substr($fraction, 0, 2);

        return (int) $fraction[2] >= 5 ? $kopecks + 1 : $kopecks;
    }

    private function supplier(DOMXPath $xpath, DOMElement $contract): ?ParsedSupplier
    {
        $info = $this->node($xpath, './*[local-name()="suppliersInfo"]/*[local-name()="supplierInfo"]', $contract)
            ?? $this->node($xpath, './*[local-name()="suppliers"]/*[local-name()="supplier"]', $contract);

        if (! $info instanceof DOMElement) {
            return null;
        }

        // Реквизиты банка лежат рядом и тоже содержат ИНН/КПП — их не трогаем.
        $own = 'not(ancestor::*[local-name()="supplierAccountsDetails"])';
        $inn = $this->first($xpath, './/*[(local-name()="INN" or local-name()="inn") and '.$own.']', $info);

        if ($inn === null || preg_match('/^\d{10}(\d{2})?$/', $inn) !== 1) {
            return null;
        }

        $name = $this->first($xpath, './/*[local-name()="shortName" and '.$own.']', $info)
            ?? $this->first($xpath, './/*[local-name()="fullName" and '.$own.']', $info)
            ?? $this->personName($xpath, $info)
            ?? $inn;

        $address = $this->first($xpath, './/*[local-name()="address" and '.$own.']', $info)
            ?? $this->first($xpath, './/*[local-name()="mailingAdress"]', $info);

        return new ParsedSupplier(
            inn: $inn,
            kpp: $this->first($xpath, './/*[(local-name()="KPP" or local-name()="kpp") and '.$own.']', $info),
            name: $name,
            email: $this->email($this->first($xpath, './/*[local-name()="contactEMail"]', $info)),
            phone: $this->phone($this->first($xpath, './/*[local-name()="contactPhone"]', $info)),
            city: $address !== null ? $this->city($address) : null,
        );
    }

    private function personName(DOMXPath $xpath, DOMElement $info): ?string
    {
        $parts = [];
        foreach (['lastName', 'firstName', 'middleName'] as $part) {
            $value = $this->first($xpath, './/*[local-name()="'.$part.'"]', $info);
            if ($value !== null) {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : 'ИП '.implode(' ', $parts);
    }

    /**
     * @return list<ParsedProduct>
     */
    private function products(DOMXPath $xpath, DOMElement $contract): array
    {
        $products = [];
        foreach ($xpath->query('./*[local-name()="products"]/*[local-name()="product"]', $contract) ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $products[] = new ParsedProduct(
                okpd2: $this->first($xpath, './*[local-name()="OKPD2"]/*[local-name()="code"]', $node)
                    ?? $this->first($xpath, './*[local-name()="KTRU"]/*[local-name()="code"]', $node),
                name: $this->first($xpath, './*[local-name()="name"]', $node) ?? '',
            );
        }

        return $products;
    }

    /** «7 9278735164», «8 (927) 873-51-64» → «+79278735164»; иначе как есть. */
    public function phone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            return '+7'.substr($digits, 1);
        }
        if (strlen($digits) === 10 && $digits[0] === '9') {
            return '+7'.$digits;
        }

        return mb_substr($value, 0, 32);
    }

    private function email(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = mb_strtolower(trim($value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null;
    }

    /** «424008, РЕСПУБЛИКА МАРИЙ ЭЛ, Г. ЙОШКАР-ОЛА, УЛ. ПЕТРОВА» → «Йошкар-Ола». */
    public function city(string $address): ?string
    {
        if (preg_match('/(?:^|[,\s])г\.?\s*([а-яё][а-яё\- ]*?)\s*(?:,|$)/iu', $address, $m) !== 1) {
            return null;
        }

        return mb_convert_case(trim($m[1]), MB_CASE_TITLE);
    }

    private function text(DOMXPath $xpath, string $path, DOMElement $context): ?string
    {
        $query = './'.implode('/', array_map(
            fn (string $name): string => '*[local-name()="'.$name.'"]',
            explode('/', $path),
        ));

        return $this->first($xpath, $query, $context);
    }

    private function node(DOMXPath $xpath, string $query, DOMNode $context): ?DOMNode
    {
        $list = $xpath->query($query, $context);

        $node = $list instanceof DOMNodeList ? $list->item(0) : null;

        return $node instanceof DOMNode ? $node : null;
    }

    private function first(DOMXPath $xpath, string $query, DOMNode $context): ?string
    {
        $node = $this->node($xpath, $query, $context);
        $value = $node !== null ? trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '') : '';

        return $value === '' ? null : $value;
    }
}
