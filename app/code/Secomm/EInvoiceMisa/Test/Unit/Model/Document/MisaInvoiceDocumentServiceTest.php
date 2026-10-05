<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Document;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;
use Secomm\EInvoiceMisa\Model\Client\MisaTokenProvider;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;
use Secomm\EInvoiceMisa\Model\Document\MisaDownloadResponseParser;
use Secomm\EInvoiceMisa\Model\Document\MisaInvoiceDocumentService;

/**
 * Unit tests for MeInvoice document service (cancel, send email).
 */
class MisaInvoiceDocumentServiceTest extends TestCase
{
    private MisaApiClient&MockObject $apiClient;
    private MisaTokenProvider&MockObject $tokenProvider;
    private MisaInvoiceDocumentService $service;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(MisaApiClient::class);
        $this->tokenProvider = $this->createMock(MisaTokenProvider::class);
        $this->tokenProvider->method('getToken')->willReturn('token');

        $this->service = new MisaInvoiceDocumentService(
            $this->apiClient,
            $this->tokenProvider,
            $this->createMock(MisaDownloadResponseParser::class),
            $this->createMock(MisaConfig::class),
            new Json()
        );
    }

    /**
     * @return void
     */
    public function testCancelRequiresInvSeries(): void
    {
        $this->expectException(LocalizedException::class);
        $this->service->cancel('TX-1', '', 'reason', 1);
    }

    /**
     * @return void
     */
    public function testSendEmailRequiresReceiverEmail(): void
    {
        $this->expectException(LocalizedException::class);
        $this->service->sendEmail('TX-1', 'Buyer', ' ', null, null, 1);
    }

    /**
     * @return void
     */
    public function testSendEmailReturnsFailureWhenProviderReportsErrorStatus(): void
    {
        $this->apiClient->method('sendInvoiceEmail')->willReturn([
            'success' => true,
            'data' => '[{"SendEmailStatus":2,"ErrorCode":"InvalidEmail"}]',
        ]);

        $result = $this->service->sendEmail('TX-1', 'Buyer', 'buyer@example.com', null, null, 1);

        self::assertFalse($result['success']);
        self::assertSame('InvalidEmail', $result['message']);
    }

    /**
     * @return void
     */
    public function testSendEmailSucceedsWhenProviderReturnsOkStatus(): void
    {
        $this->apiClient->method('sendInvoiceEmail')->willReturn([
            'success' => true,
            'data' => '[{"SendEmailStatus":1}]',
        ]);

        $result = $this->service->sendEmail('TX-1', 'Buyer', 'buyer@example.com', null, null, 1);

        self::assertTrue($result['success']);
    }
}
