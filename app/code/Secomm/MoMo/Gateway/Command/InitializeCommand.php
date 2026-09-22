<?php
/**
 * Gateway `initialize` command (MOMO-01).
 *
 * Runs when CartManagement::placeOrder creates the order inside the
 * OrderFinalizer transaction: the order is created in pending_payment and
 * the core confirmation email is suppressed at placement (SubmitObserver
 * respects canSendNewEmailFlag) — the finalizer sends the confirmation
 * itself, post-commit, under the email dispatch claim (single dispatch per
 * attempt, retry-safe).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

/**
 * Gateway initialize command: pending_payment order state + email
 * suppression at placement.
 */
class InitializeCommand implements CommandInterface
{
    /**
     * @inheritdoc
     */
    public function execute(array $commandSubject): void
    {
        $stateObject = SubjectReader::readStateObject($commandSubject);
        $paymentDO = SubjectReader::readPayment($commandSubject);

        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        ContextHelper::assertOrderPayment($payment);

        $payment->setAmountAuthorized($payment->getOrder()->getTotalDue());
        $payment->setBaseAmountAuthorized($payment->getOrder()->getBaseTotalDue());
        $payment->getOrder()->setCanSendNewEmailFlag(false);

        $stateObject->setData(OrderInterface::STATE, Order::STATE_PENDING_PAYMENT);
        $stateObject->setData(OrderInterface::STATUS, Order::STATE_PENDING_PAYMENT);
        $stateObject->setData('is_notified', false);
    }
}
