<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Plugin\Model\Checkout\Session;

use Magento\Checkout\Model\Session;
use Magento\Checkout\Model\Session\SuccessValidator;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Plugin to handle ZaloPay success page grace period
 */
class SuccessValidatorPlugin
{
    /**
     * Allowed time difference in minutes for ZaloPay
     */
    private const ALLOWED_TIME_DIFFERENCE = 15;

    /**
     * ZaloPay method code
     */
    private const ZALO_PAY_METHOD_CODE = "zalopay";

    /**
     * Constructor
     *
     * @param TimezoneInterface $timezone
     */
    public function __construct(private readonly TimezoneInterface $timezone)
    {
    }

    /**
     * Around plugin to extend success validation for ZaloPay
     *
     * @param SuccessValidator $subject
     * @param callable $proceed
     * @return bool
     */
    public function aroundIsValid(SuccessValidator $subject, callable $proceed): bool
    {
        // Call original isValid
        $result = $proceed();
        if ($result) {
            return true;
        }

        // Additional grace period for ZaloPay
        try {
            $checkoutSession = $this->getCheckoutSession($subject);
            $order = $checkoutSession->getLastRealOrder();

            if (!$order->hasData()) {
                return false;
            }

            $paymentMethod = $order->getPayment()->getMethod();

            // Only apply grace period for ZaloPay
            if ($paymentMethod !== self::ZALO_PAY_METHOD_CODE) {
                return false;
            }

            // Check if within grace period
            $updatedAt = $order->getUpdatedAt();
            $allowedTime = $this->timezone->date($updatedAt)->modify('+' . self::ALLOWED_TIME_DIFFERENCE . 'minutes');
            $currentTime = $this->timezone->date();

            if ($currentTime < $allowedTime) {
                if (!$checkoutSession->getLastQuoteId() || !$checkoutSession->getLastOrderId()) {
                    return false;
                }
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get checkout session from subject (workaround for protected property)
     *
     * @param SuccessValidator $subject
     * @return Session
     */
    private function getCheckoutSession(SuccessValidator $subject): Session
    {
        // Use reflection to access protected property
        $reflection = new \ReflectionClass($subject);
        $property = $reflection->getProperty('checkoutSession');
        $property->setAccessible(true);
        return $property->getValue($subject);
    }
}
