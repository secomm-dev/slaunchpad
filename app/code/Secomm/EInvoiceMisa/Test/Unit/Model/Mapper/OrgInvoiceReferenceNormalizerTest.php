<?php

declare(strict_types=1);

namespace Secomm\EInvoiceMisa\Test\Unit\Model\Mapper;

use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceMisa\Model\Mapper\OrgInvoiceReferenceNormalizer;

class OrgInvoiceReferenceNormalizerTest extends TestCase
{
    public function testSplitInvSeriesForNd123Symbol(): void
    {
        $split = OrgInvoiceReferenceNormalizer::splitInvSeries('2C26TYV');

        self::assertSame('2', $split['template_no']);
        self::assertSame('C26TYV', $split['series']);
    }

    public function testApplyOrgFieldsUsesSplitSeries(): void
    {
        $payload = [];
        OrgInvoiceReferenceNormalizer::applyOrgFields($payload, [
            'transaction_id' => 'TX-1',
            'ref_id' => 'ref-1',
            'inv_series' => '1C24MAA',
            'inv_date' => '2026-06-08',
            'inv_no' => '00000008',
            'template_no' => 'uuid-template',
        ]);

        self::assertSame('TX-1', $payload['OrgInvoiceTransactionID']);
        self::assertSame('C24MAA', $payload['OrgInvSeries']);
        self::assertSame('1', $payload['OrgInvTemplateNo']);
        self::assertSame('00000008', $payload['OrgInvNo']);
    }
}
