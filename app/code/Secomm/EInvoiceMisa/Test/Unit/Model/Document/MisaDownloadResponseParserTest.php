<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Document;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceCore\Api\InvoiceDocumentServiceInterface;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;
use Secomm\EInvoiceMisa\Model\Document\MisaDownloadResponseParser;

/**
 * Unit tests for MeInvoice download response parser.
 */
class MisaDownloadResponseParserTest extends TestCase
{
    private MisaDownloadResponseParser $parser;
    private MisaApiClient&MockObject $apiClient;
    private Json $json;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(MisaApiClient::class);
        $this->json = new Json();
        $logger = $this->createMock(LoggerInterface::class);
        $this->parser = new MisaDownloadResponseParser($this->apiClient, $this->json, $logger);
    }

    /**
     * @return void
     */
    public function testParseFetchesPdfFromDownloadUrl(): void
    {
        $url = 'https://test.meinvoice.vn/download/tra-cuu/downloadhandler.ashx?type=pdf&code=abc';
        $pdfBytes = '%PDF-1.4 fake content';

        $this->apiClient->expects(self::once())
            ->method('fetchBinary')
            ->with($url, 'token-1', 1)
            ->willReturn($pdfBytes);

        $result = $this->parser->parse(
            [
                'success' => true,
                'data' => $url,
            ],
            'TX001',
            InvoiceDocumentServiceInterface::DOWNLOAD_PDF,
            'token-1',
            1
        );

        self::assertSame('invoice-TX001.pdf', $result['filename']);
        self::assertSame('application/pdf', $result['mime']);
        self::assertSame($pdfBytes, $result['contents']);
    }

    /**
     * @return void
     */
    public function testParseExtractsXmlFromJsonListInData(): void
    {
        $xml = '<HDon><DLHDon Id="N6FGS190W39"></DLHDon></HDon>';
        $data = $this->json->serialize([
            [
                'TransactionID' => 'N6FGS190W39',
                'Data' => $xml,
                'ErrorCode' => '',
            ],
        ]);

        $this->apiClient->expects(self::never())->method('fetchBinary');

        $result = $this->parser->parse(
            [
                'success' => true,
                'data' => $data,
            ],
            'N6FGS190W39',
            InvoiceDocumentServiceInterface::DOWNLOAD_XML
        );

        self::assertSame('invoice-N6FGS190W39.xml', $result['filename']);
        self::assertSame('application/xml', $result['mime']);
        self::assertSame($xml, $result['contents']);
    }

    /**
     * @return void
     */
    public function testParseDecodesBase64PdfInJsonList(): void
    {
        $pdfBytes = '%PDF-1.4 test';
        $data = $this->json->serialize([
            [
                'TransactionID' => 'TX002',
                'Data' => base64_encode($pdfBytes),
                'ErrorCode' => '',
            ],
        ]);

        $result = $this->parser->parse(
            [
                'success' => true,
                'data' => $data,
            ],
            'TX002',
            InvoiceDocumentServiceInterface::DOWNLOAD_PDF
        );

        self::assertSame($pdfBytes, $result['contents']);
    }

    /**
     * @return void
     */
    public function testParseThrowsWhenApiReturnsFailure(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid invoice');

        $this->parser->parse(
            [
                'success' => false,
                'descriptionErrorCode' => 'Invalid invoice',
            ],
            'TX003',
            InvoiceDocumentServiceInterface::DOWNLOAD_PDF
        );
    }
}
