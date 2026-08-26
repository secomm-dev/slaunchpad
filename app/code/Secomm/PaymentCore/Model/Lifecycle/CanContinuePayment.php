<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model\Lifecycle;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime as DateLib;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Secomm\PaymentCore\Api\Data\PaymentInterface;
use Secomm\PaymentCore\Api\PaymentRepositoryInterface;
use Secomm\PaymentCore\Model\Config;

/**
 * FEAT-CSWYEJ — single guard for the Continue Payment surface (spec §4.1/§4.8).
 *
 * Both the button (should it render?) and the controller (may it redirect?)
 * MUST call this service — the denial is enforced server-side, hiding the
 * button is never the only protection (AC-006).
 */
class CanContinuePayment
{
    public const REASON_OK = '';
    public const REASON_CORE_DISABLED = 'core_disabled';
    public const REASON_METHOD_NOT_MANAGED = 'method_not_managed';
    public const REASON_CONTINUE_DISABLED = 'continue_disabled';
    public const REASON_NO_RECORD = 'no_record';
    public const REASON_NOT_ACTIVE = 'not_active';
    public const REASON_EXPIRED = 'expired';
    public const REASON_STATE = 'state';
    public const REASON_NOT_PAYABLE = 'not_payable';

    public function __construct(
        private readonly Config $config,
        private readonly PaymentRepositoryInterface $repository,
        private readonly DateLib $dateLib
    ) {
    }

    /**
     * Full guard chain for the order. Deny reasons are exposed for logging
     * (continue_denied{reason}) and for the customer-facing message.
     */
    public function canContinue(OrderInterface $order): bool
    {
        return $this->denyReason($order) === self::REASON_OK;
    }

    /**
     * Expiry refresh on Continue Payment (DEC-FEATCSWYEJ-003): each retry
     * generates a fresh provider session (VNPAY token TTL 15'), so the record
     * window must restart with it — otherwise the customer pays on a dead
     * token or the cron cancels mid-payment.
     *
     * @param PaymentInterface $record
     * @param string $methodCode
     * @return void
     */
    public function refreshExpiry(PaymentInterface $record, string $methodCode): void
    {
        $record->setExpiresAt($this->dateLib->gmtDate(
            'Y-m-d H:i:s',
            $this->dateLib->gmtTimestamp() + $this->config->resolveExpiryMinutes($methodCode) * 60
        ));
        $this->repository->save($record);
    }

    /**
     * @return self::REASON_*
     */
    public function denyReason(OrderInterface $order): string
    {
        $websiteId = $order->getStore()?->getWebsiteId();
        if (!$this->config->isEnabled($websiteId)) {
            return self::REASON_CORE_DISABLED;
        }
        $methodCode = (string)($order->getPayment()?->getMethod() ?? '');
        if (!$this->config->isManagedMethod($methodCode, $websiteId)) {
            return self::REASON_METHOD_NOT_MANAGED;
        }
        if ($this->config->isContinueDisabled($methodCode, $websiteId)) {
            return self::REASON_CONTINUE_DISABLED;
        }
        try {
            $record = $this->repository->getByOrderId((int)$order->getEntityId());
        } catch (NoSuchEntityException) {
            return self::REASON_NO_RECORD;
        }
        if ($record->getStatus() !== PaymentInterface::STATUS_ACTIVE) {
            return self::REASON_NOT_ACTIVE;
        }
        if ($record->getExpiresAt() <= $this->dateLib->gmtDate('Y-m-d H:i:s')) {
            return self::REASON_EXPIRED;
        }
        $state = (string)$order->getState();
        if ($state !== Order::STATE_NEW) {
            return self::REASON_STATE;
        }
        if (!$order->canCancel() || (float)$order->getTotalDue() <= 0) {
            return self::REASON_NOT_PAYABLE;
        }
        return self::REASON_OK;
    }
}
