<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Block\Info;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Secomm\VietQr\Model\VndAmount;

/**
 * Renders VietQR payment method information in Admin order view and emails/PDFs
 * (BUG-63CVS3 / SLP-149).
 */
class VietQr extends Info
{
    /**
     * @var string
     */
    protected $_template = 'Magento_Payment::info/default.phtml';

    /**
     * @param Context $context
     * @param VndAmount $vndAmount
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly VndAmount $vndAmount,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Prepare payment specific information.
     *
     * @param DataObject|array|null $transport
     * @return DataObject
     */
    protected function _prepareSpecificInformation($transport = null): DataObject
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }

        $transport = parent::_prepareSpecificInformation($transport);
        $payment = $this->getInfo();
        if (!$payment) {
            return $transport;
        }

        $data = [];

        if ($bankCode = $payment->getAdditionalInformation('vietqr_bank_code')) {
            $data[(string)__('Bank')] = (string)$bankCode;
        }
        if ($account = $payment->getAdditionalInformation('vietqr_bank_account')) {
            $data[(string)__('Account Number')] = (string)$account;
        }
        if ($accountName = $payment->getAdditionalInformation('vietqr_account_name')) {
            $data[(string)__('Account Holder')] = (string)$accountName;
        }
        if ($amount = $payment->getAdditionalInformation('vietqr_amount')) {
            // Always VND (BUG-4BX0CK) — bank transfers only accept VND
            $data[(string)__('Amount')] = $this->vndAmount->format((float)$amount);
        }
        if ($content = $payment->getAdditionalInformation('vietqr_content')) {
            $data[(string)__('Transfer Content')] = (string)$content;
        }

        $confirmed = $payment->getAdditionalInformation('vietqr_customer_confirmed');
        if ($confirmed !== null) {
            $data[(string)__('Customer Confirmed')] = (bool)$confirmed
                ? (string)__('Yes')
                : (string)__('No');
        }

        if ($confirmedAt = $payment->getAdditionalInformation('vietqr_customer_confirmed_at')) {
            $data[(string)__('Confirmed At')] = (string)$confirmedAt;
        }
        if ($txRef = $payment->getAdditionalInformation('vietqr_transaction_ref')) {
            $data[(string)__('Transaction Reference')] = (string)$txRef;
        }
        if ($notes = $payment->getAdditionalInformation('vietqr_customer_notes')) {
            $data[(string)__('Customer Notes')] = (string)$notes;
        }

        return $transport->setData(array_merge($data, $transport->getData()));
    }
}
