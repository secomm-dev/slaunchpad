<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Helper;

use Magento\Directory\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\ResourceModel\Currency;
use Magento\Directory\Model\PriceCurrency;
use Psr\Log\LoggerInterface;

/**
 * Class Rate
 *
 * @package Secomm\GiaoHangNhanh\Helper
 */
class Rate
{
    /**
     * Vietnam dong currency
     */
    const CURRENCY_CODE = 'VND';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Data
     */
    private $helperData;

    /**
     * OrderDetailsDataBuilder constructor.
     *
     * @param Data $helperData
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Data $helperData,
        StoreManagerInterface $storeManager,
        protected Currency $currency,
        protected PriceCurrency $priceCurrency,
        protected LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->helperData = $helperData;
    }

    /**
     * @param float $amount
     * @return false|float
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAmountByStoreCurrency($amount)
    {
        if ($this->getDefaultCurrencyCode() == self::CURRENCY_CODE) {
            return $amount;
        } else {
            try {
                return round($this->helperData->currencyConvert(
                    $amount,
                    self::CURRENCY_CODE,
                    $this->getDefaultCurrencyCode()
                ), 2);
            } catch (\Exception $e) {
                throw new LocalizedException(
                    __('We can\'t convert VND to store default currency. Please setup currency rates.')
                );
            }
        }
    }

    /**
     * Convert shipping fee from VND to Store Base Currency
     *
     * @param float|int $shippingFee
     * @return float
     */
    public function convertPriceToDefaultCurrency($shippingFee): float
    {
        $baseCurrencyCode = $this->getBaseCurrencyCode();
        if ($baseCurrencyCode === self::CURRENCY_CODE || empty($shippingFee)) {
            return (float)$shippingFee;
        }

        try {
            $rate = (float)$this->currency->getRate($baseCurrencyCode, self::CURRENCY_CODE);
            if ($rate > 0) {
                return (float)$this->priceCurrency->roundPrice($shippingFee / $rate);
            }
        } catch (\Exception $exception) {
            // The price falls back to the original price to avoid crashing checkout, but the logs are still available for diagnostics.
            $this->logger->error('GHN convert currency error: ' . $exception->getMessage());
        }

        return (float)$shippingFee;
    }

    /**
     * @param Order $order
     * @param $amount
     * @return false|float
     * @throws LocalizedException
     */
    public function getVndOrderAmount(Order $order, $amount)
    {
        if ($this->isVietnamDong($order)) {
            return $amount;
        } else {
            try {
                return round($this->helperData->currencyConvert(
                    $amount,
                    $order->getOrderCurrencyCode(),
                    self::CURRENCY_CODE
                ));
            } catch (\Exception $e) {
                throw new LocalizedException(
                    __('We can\'t convert base currency to %1. Please setup currency rates.', self::CURRENCY_CODE)
                );
            }
        }
    }

    /**
     * @param Order $order
     * @return boolean
     */
    private function isVietnamDong($order)
    {
        return $order->getOrderCurrencyCode() === self::CURRENCY_CODE;
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    private function getDefaultCurrencyCode()
    {
        return $this->storeManager->getStore()->getDefaultCurrencyCode();
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    private function getBaseCurrencyCode()
    {
        return $this->storeManager->getStore()->getBaseCurrencyCode();
    }

    /**
     * @param int|float $amount
     * @return false|float
     * @throws LocalizedException
     */
    public function getVndAmountByStoreCurrency($amount)
    {
        try {
            return round($this->helperData->currencyConvert(
                $amount,
                $this->getDefaultCurrencyCode(),
                self::CURRENCY_CODE
            ));
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('We can\'t convert base currency to %1. Please setup currency rates.', self::CURRENCY_CODE)
            );
        }
    }
}