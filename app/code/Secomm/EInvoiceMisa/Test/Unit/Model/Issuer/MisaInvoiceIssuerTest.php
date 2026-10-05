<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Issuer;

use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceCore\Model\Data\IssueResult;
use Secomm\EInvoiceCore\Model\Data\IssueResultFactory;
use Secomm\EInvoiceMisa\Model\Client\MisaApiClient;
use Secomm\EInvoiceMisa\Model\Client\MisaTokenProvider;
use Secomm\EInvoiceMisa\Model\Config\MisaConfig;
use Secomm\EInvoiceMisa\Model\Issuer\InvoicePayloadTotalsValidator;
use Secomm\EInvoiceMisa\Model\Issuer\MisaInvoiceIssuer;
use Secomm\EInvoiceMisa\Model\Issuer\PublishResponseValidator;

/**
 * Unit tests for MeInvoice invoice issuer.
 */
class MisaInvoiceIssuerTest extends TestCase
{
    private function createIssuer(
        MisaApiClient $apiClient,
        MisaTokenProvider $tokenProvider,
        MisaConfig $misaConfig
    ): MisaInvoiceIssuer {
        $factory = $this->createMock(IssueResultFactory::class);
        $factory->method('create')->willReturn(new IssueResult());
        $logger = $this->createMock(LoggerInterface::class);

        $tokenProvider->method('executeWithToken')->willReturnCallback(
            static fn (?int $storeId, callable $callback): mixed => $callback('token')
        );

        return new MisaInvoiceIssuer(
            $apiClient,
            $tokenProvider,
            $misaConfig,
            new PublishResponseValidator(new Json()),
            new InvoicePayloadTotalsValidator(),
            $factory,
            new Json(),
            $logger
        );
    }

    public function testIssueSetsExternalIdFromTransactionIdOnSuccess(): void
    {
        $apiClient = $this->createMock(MisaApiClient::class);
        $tokenProvider = $this->createMock(MisaTokenProvider::class);
        $misaConfig = $this->createMock(MisaConfig::class);

        $misaConfig->method('usePreviewBeforePublish')->willReturn(false);
        $misaConfig->method('requiresStatusPollAfterPublish')->willReturn(false);
        $misaConfig->method('getSignType')->willReturn(2);
        $apiClient->method('publishInvoiceHsm')->willReturn([
            'success' => true,
            'publishInvoiceResult' => '[{"TransactionID":"TX-001","InvNo":"00000007"}]',
        ]);

        $issuer = $this->createIssuer($apiClient, $tokenProvider, $misaConfig);
        $request = $this->createMock(IssueRequestInterface::class);
        $request->method('getStoreId')->willReturn(1);
        $request->method('getOrderId')->willReturn(10);
        $request->method('getPayload')->willReturn([
            'RefID' => 'ref-1',
            'InvSeries' => '1C26THP',
            'InvoiceTemplateID' => 'tid-1',
            'TotalAmountOC' => 0.0,
            'TotalAmountWithoutVATOC' => 0.0,
            'TotalVATAmountOC' => 0.0,
            'InvoiceDetail' => [],
            'FeeInfo' => [],
        ]);

        $result = $issuer->issue($request);

        self::assertTrue($result->isSuccess());
        self::assertSame('TX-001', $result->getExternalId());
        $raw = $result->getRawResponse();
        self::assertSame('00000007', $raw['meta']['inv_no'] ?? null);
    }

    public function testIssueFailsOnNestedPublishErrorCode(): void
    {
        $apiClient = $this->createMock(MisaApiClient::class);
        $tokenProvider = $this->createMock(MisaTokenProvider::class);
        $misaConfig = $this->createMock(MisaConfig::class);

        $misaConfig->method('usePreviewBeforePublish')->willReturn(false);
        $apiClient->method('publishInvoiceHsm')->willReturn([
            'success' => true,
            'publishInvoiceResult' => '[{"TransactionID":"","ErrorCode":"InvalidInvoiceData"}]',
        ]);

        $issuer = $this->createIssuer($apiClient, $tokenProvider, $misaConfig);
        $request = $this->createMock(IssueRequestInterface::class);
        $request->method('getStoreId')->willReturn(1);
        $request->method('getOrderId')->willReturn(11);
        $request->method('getPayload')->willReturn([
            'RefID' => 'ref-2',
            'TotalAmountOC' => 0.0,
            'TotalAmountWithoutVATOC' => 0.0,
            'TotalVATAmountOC' => 0.0,
            'InvoiceDetail' => [],
            'FeeInfo' => [],
        ]);

        $result = $issuer->issue($request);

        self::assertFalse($result->isSuccess());
        self::assertStringContainsString('InvalidInvoiceData', $result->getMessage());
    }

    public function testIssueFailsWhenPayloadTotalsMismatch(): void
    {
        $apiClient = $this->createMock(MisaApiClient::class);
        $tokenProvider = $this->createMock(MisaTokenProvider::class);
        $misaConfig = $this->createMock(MisaConfig::class);

        $misaConfig->method('usePreviewBeforePublish')->willReturn(false);
        $apiClient->expects(self::never())->method('publishInvoiceHsm');

        $issuer = $this->createIssuer($apiClient, $tokenProvider, $misaConfig);
        $request = $this->createMock(IssueRequestInterface::class);
        $request->method('getStoreId')->willReturn(1);
        $request->method('getOrderId')->willReturn(13);
        $request->method('getPayload')->willReturn([
            'RefID' => 'ref-bad',
            'TotalAmountOC' => 29.0,
            'TotalAmountWithoutVATOC' => 29.0,
            'TotalVATAmountOC' => 0.0,
            'InvoiceDetail' => [
                ['ItemType' => 1, 'AmountWithoutVATOC' => 5.0, 'VATAmountOC' => 0.0],
            ],
            'FeeInfo' => [
                ['FeeName' => 'Điều chỉnh', 'FeeAmountOC' => 24.0],
            ],
        ]);

        $result = $issuer->issue($request);

        self::assertFalse($result->isSuccess());
        self::assertStringContainsString('FeeInfo', $result->getMessage());
    }

    public function testIssueFailsWhenTransactionIdMissingOnSuccessResponse(): void
    {
        $apiClient = $this->createMock(MisaApiClient::class);
        $tokenProvider = $this->createMock(MisaTokenProvider::class);
        $misaConfig = $this->createMock(MisaConfig::class);

        $misaConfig->method('usePreviewBeforePublish')->willReturn(false);
        $misaConfig->method('requiresStatusPollAfterPublish')->willReturn(false);
        $misaConfig->method('getSignType')->willReturn(2);
        $apiClient->method('publishInvoiceHsm')->willReturn([
            'success' => true,
            'publishInvoiceResult' => '[]',
        ]);

        $issuer = $this->createIssuer($apiClient, $tokenProvider, $misaConfig);
        $request = $this->createMock(IssueRequestInterface::class);
        $request->method('getStoreId')->willReturn(1);
        $request->method('getOrderId')->willReturn(12);
        $request->method('getPayload')->willReturn([
            'RefID' => 'ref-3',
            'TotalAmountOC' => 0.0,
            'TotalAmountWithoutVATOC' => 0.0,
            'TotalVATAmountOC' => 0.0,
            'InvoiceDetail' => [],
            'FeeInfo' => [],
        ]);

        $result = $issuer->issue($request);

        self::assertFalse($result->isSuccess());
        self::assertStringContainsString('TransactionID', $result->getMessage());
    }
}
