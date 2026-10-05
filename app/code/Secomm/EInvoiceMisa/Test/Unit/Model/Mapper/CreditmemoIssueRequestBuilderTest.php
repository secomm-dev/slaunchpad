<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Mapper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceCore\Api\Data\IssueRequestInterface;
use Secomm\EInvoiceMisa\Model\Mapper\CreditmemoIssueRequestBuilder;
use Secomm\EInvoiceMisa\Model\Mapper\CreditmemoToIssueRequest;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplate;
use Secomm\EInvoiceMisa\Model\Template\InvoiceTemplateResolver;

/**
 * Unit tests for credit memo issue request builder.
 */
class CreditmemoIssueRequestBuilderTest extends TestCase
{
    /**
     * @return void
     */
    public function testBuildLoadsCreditmemoAndMapsWithOriginContext(): void
    {
        $creditmemo = $this->createMock(Creditmemo::class);
        $creditmemo->method('getStoreId')->willReturn('1');

        $repository = $this->createMock(CreditmemoRepositoryInterface::class);
        $repository->method('get')->with(7)->willReturn($creditmemo);

        $template = new InvoiceTemplate('template-id', '1C26THP');
        $resolver = $this->createMock(InvoiceTemplateResolver::class);
        $resolver->method('resolveForStore')->with(1)->willReturn($template);

        $request = $this->createMock(IssueRequestInterface::class);
        $mapper = $this->createMock(CreditmemoToIssueRequest::class);
        $mapper->expects(self::once())
            ->method('map')
            ->with(
                $creditmemo,
                $template,
                self::callback(static fn (array $origin): bool => $origin['transaction_id'] === 'TX-1')
            )
            ->willReturn($request);

        $builder = new CreditmemoIssueRequestBuilder($repository, $mapper, $resolver);
        $built = $builder->build(7, ['origin' => ['transaction_id' => 'TX-1']]);

        self::assertSame($request, $built);
    }

    /**
     * @return void
     */
    public function testBuildThrowsWhenCreditmemoMissing(): void
    {
        $repository = $this->createMock(CreditmemoRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));

        $builder = new CreditmemoIssueRequestBuilder(
            $repository,
            $this->createMock(CreditmemoToIssueRequest::class),
            $this->createMock(InvoiceTemplateResolver::class)
        );

        $this->expectException(LocalizedException::class);
        $builder->build(99);
    }
}
