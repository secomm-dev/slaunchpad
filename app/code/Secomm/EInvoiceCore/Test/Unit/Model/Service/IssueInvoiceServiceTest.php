<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Test\Unit\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceCore\Api\Data\IssueResultInterface;
use Secomm\EInvoiceCore\Api\InvoiceIssuerInterface;
use Secomm\EInvoiceCore\Api\IssueRequestBuilderInterface;
use Secomm\EInvoiceCore\Api\OrderStoreIdProviderInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\EInvoiceCore\Model\AdjustmentRequestBuilderPool;
use Secomm\EInvoiceCore\Model\Config;
use Secomm\EInvoiceCore\Model\InvoiceDocumentServicePool;
use Secomm\EInvoiceCore\Model\InvoiceIssuerPool;
use Secomm\EInvoiceCore\Model\IssueRequestBuilderPool;
use Secomm\EInvoiceCore\Model\Service\IssueInvoiceService;
use Secomm\EInvoiceLog\Api\IssueLogRepositoryInterface;
use Secomm\EInvoiceLog\Model\IssueLog;
use Secomm\EInvoiceLog\Model\IssueLogFactory;

/**
 * Unit tests for synchronous invoice issuance orchestration.
 */
class IssueInvoiceServiceTest extends TestCase
{
    /**
     * Config mock.
     *
     * @var Config&MockObject
     */
    private $config;

    /**
     * Order store ID provider mock.
     *
     * @var OrderStoreIdProviderInterface&MockObject
     */
    private $orderStoreIdProvider;

    /**
     * Request builder pool mock.
     *
     * @var IssueRequestBuilderPool&MockObject
     */
    private $requestBuilderPool;

    /**
     * Invoice issuer pool mock.
     *
     * @var InvoiceIssuerPool&MockObject
     */
    private $invoiceIssuerPool;

    /**
     * Issue log factory mock.
     *
     * @var IssueLogFactory&MockObject
     */
    private $issueLogFactory;

    /**
     * Issue log repository mock.
     *
     * @var IssueLogRepositoryInterface&MockObject
     */
    private $issueLogRepository;

    /**
     * JSON serializer mock.
     *
     * @var Json&MockObject
     */
    private $json;

    /**
     * Date/time helper mock.
     *
     * @var DateTime&MockObject
     */
    private $dateTime;

    /**
     * Logger mock.
     *
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * System under test.
     *
     * @var IssueInvoiceService
     */
    private $service;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->orderStoreIdProvider = $this->createMock(OrderStoreIdProviderInterface::class);
        $this->requestBuilderPool = $this->createMock(IssueRequestBuilderPool::class);
        $this->invoiceIssuerPool = $this->createMock(InvoiceIssuerPool::class);
        $this->issueLogFactory = $this->createMock(IssueLogFactory::class);
        $this->issueLogRepository = $this->createMock(IssueLogRepositoryInterface::class);
        $this->json = $this->createMock(Json::class);
        $this->dateTime = $this->createMock(DateTime::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new IssueInvoiceService(
            $this->config,
            $this->orderStoreIdProvider,
            $this->requestBuilderPool,
            $this->invoiceIssuerPool,
            $this->createMock(InvoiceDocumentServicePool::class),
            $this->createMock(AdjustmentRequestBuilderPool::class),
            $this->createMock(CreditmemoRepositoryInterface::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->issueLogFactory,
            $this->issueLogRepository,
            $this->json,
            $this->dateTime,
            $this->logger
        );
    }

    /**
     * @return void
     */
    public function testScheduleReturnsExistingSuccessfulLogWithoutCallingIssuer(): void
    {
        $request = $this->createRequestMock();
        $successful = $this->createIssueLog(10, IssueLog::STATUS_SUCCESS);

        $this->orderStoreIdProvider->method('getStoreIdByOrderId')->with(10)->willReturn(1);
        $this->setupRequestBuilder(1, 10, $request);
        $this->config->method('isEnabled')->with(1)->willReturn(true);
        $this->issueLogRepository->method('findSuccessfulByOrderId')->with(10)->willReturn($successful);

        self::assertSame($successful, $this->service->schedule(10));
    }

    /**
     * @return void
     */
    public function testScheduleThrowsWhenDisabled(): void
    {
        $request = $this->createRequestMock();

        $this->orderStoreIdProvider->method('getStoreIdByOrderId')->with(10)->willReturn(1);
        $this->setupRequestBuilder(1, 10, $request);
        $this->config->method('isEnabled')->with(1)->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->service->schedule(10);
    }

    /**
     * @return void
     */
    public function testProcessLogMarksSuccessAndPersists(): void
    {
        $request = $this->createRequestMock();
        $log = $this->createIssueLog(10, IssueLog::STATUS_PENDING);
        $result = $this->createMock(IssueResultInterface::class);
        $issuer = $this->createMock(InvoiceIssuerInterface::class);

        $this->config->method('isEnabled')->with(1)->willReturn(true);
        $this->setupRequestBuilder(1, 10, $request);
        $this->invoiceIssuerPool->method('get')->with(1)->willReturn($issuer);
        $this->json->method('serialize')->willReturn('{"payload":true}');
        $issuer->method('issue')->with($request)->willReturn($result);
        $result->method('isSuccess')->willReturn(true);
        $result->method('getExternalId')->willReturn('MISA-123');
        $result->method('getRawResponse')->willReturn(['success' => true]);
        $this->dateTime->method('gmtDate')->willReturn('2026-05-25 10:00:00');
        $this->issueLogRepository->expects(self::exactly(2))->method('save')->with($log)->willReturn($log);

        $processed = $this->service->processLog($log);

        self::assertSame(IssueLog::STATUS_SUCCESS, $processed->getStatus());
        self::assertSame('MISA-123', $processed->getExternalId());
    }

    /**
     * @return void
     */
    public function testProcessLogMarksFailedWhenIssuerReturnsFailure(): void
    {
        $request = $this->createRequestMock();
        $log = $this->createIssueLog(10, IssueLog::STATUS_PENDING);
        $result = $this->createMock(IssueResultInterface::class);
        $issuer = $this->createMock(InvoiceIssuerInterface::class);

        $this->config->method('isEnabled')->with(1)->willReturn(true);
        $this->setupRequestBuilder(1, 10, $request);
        $this->invoiceIssuerPool->method('get')->with(1)->willReturn($issuer);
        $this->json->method('serialize')->willReturn('{}');
        $issuer->method('issue')->willReturn($result);
        $result->method('isSuccess')->willReturn(false);
        $result->method('getMessage')->willReturn('Provider error');
        $result->method('getRawResponse')->willReturn(['success' => false]);
        $this->logger->expects(self::once())->method('error');
        $this->issueLogRepository->expects(self::exactly(2))->method('save')->willReturn($log);

        $processed = $this->service->processLog($log);

        self::assertSame(IssueLog::STATUS_FAILED, $processed->getStatus());
        self::assertSame('Provider error', $processed->getErrorMessage());
    }

    /**
     * @return void
     */
    public function testIssueNowBypassesSuccessfulLogLookup(): void
    {
        $request = $this->createRequestMock();
        $log = $this->createIssueLog(10, IssueLog::STATUS_PENDING);
        $result = $this->createMock(IssueResultInterface::class);
        $issuer = $this->createMock(InvoiceIssuerInterface::class);

        $this->orderStoreIdProvider->method('getStoreIdByOrderId')->with(10)->willReturn(1);
        $this->setupRequestBuilder(1, 10, $request);
        $this->config->method('isEnabled')->with(1)->willReturn(true);
        $this->issueLogRepository->expects(self::never())->method('findSuccessfulByOrderId');
        $this->issueLogRepository->method('findOpenByOrderId')->with(10)->willReturn(null);
        $this->issueLogFactory->method('create')->willReturn($log);
        $this->json->method('serialize')->willReturn('{}');
        $this->invoiceIssuerPool->method('get')->willReturn($issuer);
        $issuer->method('issue')->willReturn($result);
        $result->method('isSuccess')->willReturn(true);
        $result->method('getExternalId')->willReturn('MISA-999');
        $result->method('getRawResponse')->willReturn([]);
        $this->dateTime->method('gmtDate')->willReturn('2026-05-25 10:00:00');
        $this->issueLogRepository->method('save')->willReturn($log);

        $processed = $this->service->issueNow(10);

        self::assertSame(IssueLog::STATUS_SUCCESS, $processed->getStatus());
    }

    /**
     * Wire request builder pool mock for a store and order.
     *
     * @param int $storeId
     * @param int $orderId
     * @param IssueRequestInterface $request
     * @return void
     */
    private function setupRequestBuilder(int $storeId, int $orderId, IssueRequestInterface $request): void
    {
        $builder = $this->createMock(IssueRequestBuilderInterface::class);
        $this->requestBuilderPool->method('get')->with($storeId)->willReturn($builder);
        $builder->method('build')->with($orderId)->willReturn($request);
    }

    /**
     * Create request DTO mock with default order identifiers.
     *
     * @return IssueRequestInterface&MockObject
     */
    private function createRequestMock(): IssueRequestInterface&MockObject
    {
        $request = $this->createMock(IssueRequestInterface::class);
        $request->method('getOrderId')->willReturn(10);
        $request->method('getOrderIncrementId')->willReturn('100000010');
        $request->method('getStoreId')->willReturn(1);
        $request->method('getPayload')->willReturn(['sale_order_no' => '100000010']);

        return $request;
    }

    /**
     * Create mutable issue log mock backed by in-memory state.
     *
     * @param int $orderId
     * @param string $status
     * @return IssueLog&MockObject
     */
    private function createIssueLog(int $orderId, string $status): IssueLog
    {
        /** @var array<string, mixed> $state */
        $state = [
            'entity_id' => 1,
            'order_id' => $orderId,
            'order_increment_id' => '100000010',
            'store_id' => 1,
            'status' => $status,
        ];

        $log = $this->createMock(IssueLog::class);
        $log->method('getId')->willReturnCallback(function () use (&$state) {
            return $state['entity_id'];
        });
        $log->method('getStatus')->willReturnCallback(function () use (&$state): string {
            return (string) $state['status'];
        });
        $log->method('setStatus')->willReturnCallback(function (string $value) use (&$state, $log) {
            $state['status'] = $value;
            return $log;
        });
        $log->method('getData')->willReturnCallback(function (?string $key = null) use (&$state) {
            if ($key === null) {
                return $state;
            }

            return $state[$key] ?? null;
        });
        $log->method('setData')->willReturnCallback(
            function (array|string $key, mixed $value = null) use (&$state, $log) {
                if (is_array($key)) {
                    $state = array_merge($state, $key);
                } else {
                    $state[$key] = $value;
                }

                return $log;
            }
        );
        $log->method('setErrorMessage')->willReturnCallback(function (?string $message) use (&$state, $log) {
            $state['error_message'] = $message;
            return $log;
        });
        $log->method('getErrorMessage')->willReturnCallback(function () use (&$state): ?string {
            $message = $state['error_message'] ?? null;

            return $message !== null && $message !== '' ? (string) $message : null;
        });
        $log->method('getExternalId')->willReturnCallback(function () use (&$state): ?string {
            $externalId = $state['external_id'] ?? null;

            return $externalId !== null && $externalId !== '' ? (string) $externalId : null;
        });
        $log->method('setLastAction')->willReturnCallback(
            function (string $action, ?string $at = null) use (&$state, $log) {
                $state['last_action'] = $action;
                if ($at !== null) {
                    $state['last_action_at'] = $at;
                }

                return $log;
            }
        );
        $log->method('setActionError')->willReturnCallback(
            function (string $action, ?string $message) use (&$state, $log) {
                if ($message === null || $message === '') {
                    unset($state['action_errors'][$action]);
                } else {
                    $state['action_errors'][$action] = $message;
                    $state['error_message'] = $message;
                    $state['last_action'] = $action;
                }

                return $log;
            }
        );

        return $log;
    }
}
