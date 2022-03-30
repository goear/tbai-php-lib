<?php

namespace Barnetik\Tbai\Invoice;

use Barnetik\Tbai\Header\RectifyingInvoice;
use Barnetik\Tbai\Interfaces\TbaiXml;
use Barnetik\Tbai\ValueObject\Date;
use Barnetik\Tbai\ValueObject\Time;
use DOMDocument;
use DOMNode;

class Header implements TbaiXml
{
    private ?string $series;
    private string $invoiceNumber;
    private Date $expeditionDate;
    private Time $expeditionTime;
    private bool $isSimplified;
    private ?bool $isSimplifiedSubstitute;
    private ?RectifyingInvoice $rectifyingInvoice;

    private function __construct(string $invoiceNumber, Date $expeditionDate, Time $expeditionTime, ?string $series = null)
    {
        $this->series = $series;
        $this->invoiceNumber = $invoiceNumber;
        $this->expeditionDate = $expeditionDate;
        $this->expeditionTime = $expeditionTime;
    }

    public static function create(string $invoiceNumber, Date $expeditionDate, Time $expeditionTime, ?string $series = null): self
    {
        $header = new self($invoiceNumber, $expeditionDate, $expeditionTime, $series);
        $header->isSimplified = false;
        return $header;
    }

    public static function createSimplified(string $invoiceNumber, Date $expeditionDate, Time $expeditionTime, ?string $series = null): self
    {
        $header = new self($invoiceNumber, $expeditionDate, $expeditionTime, $series);
        $header->isSimplified = true;
        return $header;
    }

    public static function createSimplifiedSubstitute(string $invoiceNumber, Date $expeditionDate, Time $expeditionTime, ?string $series = null): self
    {
        $header = self::create($invoiceNumber, $expeditionDate, $expeditionTime, $series);
        $header->isSimplifiedSubstitute = true;
        return $header;
    }

    public static function createRectifyingInvoice(string $invoiceNumber, Date $expeditionDate, Time $expeditionTime, RectifyingInvoice $rectifyingInvoice, ?string $series = null): self
    {
        $header = self::create($invoiceNumber, $expeditionDate, $expeditionTime, $series);
        $header->rectifyingInvoice = $rectifyingInvoice;
        return $header;
    }

    public function series(): string
    {
        return $this->series;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function expeditionDate(): Date
    {
        return $this->expeditionDate;
    }

    public function expeditionTime(): Time
    {
        return $this->expeditionTime;
    }

    public function xml(DOMDocument $domDocument): DOMNode
    {
        $header = $domDocument->createElement('CabeceraFactura');
        if ($this->series()) {
            $header->appendChild($domDocument->createElement('SerieFactura', $this->series()));
        }

        $header->appendChild($domDocument->createElement('NumFactura', $this->invoiceNumber()));
        $header->appendChild($domDocument->createElement('FechaExpedicionFactura', $this->expeditionDate()));
        $header->appendChild($domDocument->createElement('HoraExpedicionFactura', $this->expeditionTime()));
        $header->appendChild($domDocument->createElement('FacturaSimplificada', $this->isSimplified ? 'S' : 'N'));

        if (isset($this->isSimplifiedSubstitute)) {
            $header->appendChild($domDocument->createElement('FacturaEmitidaSustitucionSimplificada', $this->isSimplifiedSubstitute ? 'S' : 'N'));
        }

        if (isset($this->rectifyingInvoice)) {
            $header->appendChild($this->rectifyingInvoice->xml($domDocument));
        }

        return $header;
    }

    public static function createFromJson(array $jsonData): self
    {
        $isSimplified = $jsonData['simplifiedInvoice'] ?? false;
        $invoiceNumber = $jsonData['invoiceNumber'];
        $expeditionDate = new Date($jsonData['expeditionDate']);
        $expeditionTime = new Time($jsonData['expeditionTime']);
        $series = $jsonData['series'] ?? null;

        if ($isSimplified) {
            return self::createSimplified($invoiceNumber, $expeditionDate, $expeditionTime, $series);
        }

        if (isset($jsonData['isSimplifiedSubstitute']) && $jsonData['isSimplifiedSubstitute']) {
            return self::createSimplifiedSubstitute($invoiceNumber, $expeditionDate, $expeditionTime, $series);
        }

        if (isset($jsonData['rectifyingInvoice']) && $jsonData['rectifyingInvoice']) {
            $rectifyingInvoice = RectifyingInvoice::createFromJson($jsonData['rectifyingInvoice']);
            return self::createRectifyingInvoice($invoiceNumber, $expeditionDate, $expeditionTime, $rectifyingInvoice, $series);
        }

        return self::create($invoiceNumber, $expeditionDate, $expeditionTime, $series);
    }

    public static function docJson(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'series' => [
                    'type' => 'string',
                    'maxLength' => 20,
                    'description' => 'Fakturaren seriea - Serie factura'
                ],
                'invoiceNumber' => [
                    'type' => 'string',
                    'maxLength' => 20,
                    'description' => 'Fakturaren zenbakia - Número factura'
                ],
                'expeditionDate' => [
                    'type' => 'string',
                    'minLength' => 10,
                    'maxLength' => 10,
                    'pattern' => '^\d{2,2}-\d{2,2}-\d{4,4}$',
                    'description' => 'Faktura bidali den data (adib: 21-12-2020) - Fecha de expedición de factura (ej: 21-12-2020)'
                ],
                'expeditionTime' => [
                    'type' => 'string',
                    'minLength' => 10,
                    'maxLength' => 10,
                    'pattern' => '^\d{2,2}:\d{2,2}:\d{2,2}$',
                    'description' => 'Faktura bidali den ordua (adib: 21:00:00) - Hora de expedición de factura (ej: 21:00:00)'
                ],
                'simplifiedInvoice' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Faktura erraztua - Factura simplificada'
                ],
                'rectifyingInvoice' => RectifyingInvoice::docJson()
            ]
        ];
    }

    public function toArray(): array
    {
        return [
            'series' => $this->series ?? null,
            'invoiceNumber' => $this->invoiceNumber,
            'expeditionDate' => (string)$this->expeditionDate,
            'expeditionTime' => (string)$this->expeditionTime,
            'simplifiedInvoice' => $this->isSimplified,
            'isSimplifiedSustitute' => $this->isSimplifiedSubstitute ?? null,
            'rectifyingInvoice' => isset($this->rectifyingInvoice) ? $this->rectifyingInvoice->toArray() : null,
        ];
    }
}
