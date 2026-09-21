<?php

namespace Secomm\VNPAY\Model;

/**
 * Class Vnpay
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 */
class Vnpay extends \Magento\Payment\Model\Method\AbstractMethod
{
    public const PAYMENT_METHOD_VNPAY_CODE = 'vnpay';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_VNPAY_CODE;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * Online refund via VNPAY Refund API
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Partial refund via VNPAY Refund API (vnp_TransactionType 03)
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Secomm\VNPAY\Model\VnpayRefundService $refundService,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        ?\Magento\Directory\Helper\Data $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
        $this->refundService = $refundService;
    }

    /**
     * @var VnpayRefundService
     */
    protected $refundService;

    /**
     * VNPAY online refund — calls the Refund API against the original
     * transaction (vnp_TransactionType 02 full / 03 partial).
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount base currency amount to refund
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->refundService->refund($payment, (float)$amount);

        return $this;
    }
}
