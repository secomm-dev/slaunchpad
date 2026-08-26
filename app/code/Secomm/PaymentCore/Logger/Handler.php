<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * FEAT-CSWYEJ — lifecycle log file handler (project convention, same pattern
 * as Vnpayment_VNPAY\Logger\Handler): var/log/secomm_paymentcore.log.
 * Runtime fix 2026-08-25: the virtualType with Magento\Framework\Logger\Handler\System
 * never worked (its constructor needs an ExceptionHandler argument that DI could
 * not resolve from the virtualType arguments) — logs silently went to system.log
 * (or nowhere). A dedicated handler class is the working core pattern.
 */
class Handler extends Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * @var string
     */
    protected $fileName = '/var/log/secomm_paymentcore.log';
}
