<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

declare(strict_types=1);

namespace Mageplaza\ExtraFee\Magewire\Checkout\Payment;

use Exception;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magewirephp\Magewire\Component;
use Mageplaza\ExtraFee\Helper\Data as HelperData;

if (!class_exists(Component::class)) {
    class_alias(\stdClass::class, 'Magewirephp\Magewire\Component');
}

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Magewire\Checkout\Payment
 */
class ExtraFee extends Component
{
    /**
     * @var bool
     */
    protected $loader = true;

    /**
     * @var SessionCheckout $sessionCheckout
     */
    protected SessionCheckout $sessionCheckout;

    /**
     * @var CartRepositoryInterface $quoteRepository
     */
    protected CartRepositoryInterface $quoteRepository;

    /**
     * @var HelperData $helperData
     */
    protected HelperData $helperData;

    /**
     * @param SessionCheckout $sessionCheckout
     * @param CartRepositoryInterface $quoteRepository
     * @param HelperData $helperData
     */
    public function __construct(
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository,
        HelperData $helperData
    ) {
        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
        $this->helperData      = $helperData;
    }

    /**
     * @return Quote
     */
    protected function getQuote()
    {
        return $this->sessionCheckout->getQuote();
    }

    /**
     * @param array $payload
     *
     * @return array
     * @throws CouldNotSaveException
     */
    public function collectTotal($payload)
    {
        $quote         = $this->quoteRepository->getActive($this->getQuote()->getId());
        $areaArray     = explode(',', $payload['area']);
        $formDataArray = explode(',', $payload['formData']);
        foreach ($areaArray as $key => $item) {
            $this->helperData->setMpExtraFee($quote, $formDataArray[$key], $item);
        }

        try {
            $quote->collectTotals();
            $this->quoteRepository->save($quote);
        } catch (Exception $e) {
            $this->loader = false;
            $this->emit('error', [
                'message' => __($e->getMessage()),
            ]);

            return [];
        }
        $this->emit('payment_method_selected');
    }
}
