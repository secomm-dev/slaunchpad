<?php
/**
 * Integration-test scaffolding — NOT production code.
 *
 * Minimal equivalent of the generated Secomm\ZaloPay\Model\PaymentAttemptFactory
 * (generated factories are not in git). create() is only reached by the
 * repository's lock/save paths, which this integration does not exercise;
 * the constructor type-hint still requires the class.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model;

use Secomm\ZaloPay\It\ItAttempt;

class PaymentAttemptFactory
{
    /**
     * @return ItAttempt
     */
    public function create(array $data = []): ItAttempt
    {
        return new ItAttempt();
    }
}
