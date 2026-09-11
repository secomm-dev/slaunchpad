<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Integration\Controller\Payment;

use Magento\TestFramework\TestCase\AbstractController;

/**
 * TASK-EDS9T5 (payment-first only): the IPN is the canonical
 * server-to-server finalization path. A callback that references no known
 * payment attempt must be answered 404 (not retryable, nothing mutated,
 * no order created) — this hermetic case covers the empty/unknown payload
 * entry point; MAC/amount/finalization semantics are covered by the unit
 * suite (IpnProcessorTest).
 */
class IpnTest extends AbstractController
{
    /**
     * @return void
     */
    public function testExecuteWithoutReferenceAnswers404(): void
    {
        $this->getRequest()
            ->setMethod('POST')
            ->setContent('{}');

        $this->dispatch('zalopay/payment/ipn');

        $result = json_decode((string)$this->getResponse()->getBody(), true);
        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
        $this->assertTrue((bool)($result['errors'] ?? false));
    }

    /**
     * @return void
     */
    public function testExecuteWithUnknownAppTransIdAnswers404(): void
    {
        $payload = json_encode([
            'data' => json_encode(['app_trans_id' => '240129_000000000']),
            'mac' => 'deadbeef',
        ]);
        $this->getRequest()
            ->setMethod('POST')
            ->setContent((string)$payload);

        $this->dispatch('zalopay/payment/ipn');

        $result = json_decode((string)$this->getResponse()->getBody(), true);
        $this->assertSame(404, $this->getResponse()->getHttpResponseCode());
        $this->assertTrue((bool)($result['errors'] ?? false));
    }
}
