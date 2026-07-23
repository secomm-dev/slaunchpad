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
 * @package     Mageplaza_DeliveryTime
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\DeliveryTime\Magewire\Checkout;

use Exception;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Mageplaza\DeliveryTime\Helper\Data;
use Magewirephp\Magewire\Component;

if (!class_exists(Component::class)) {
    class_alias(BaseComponent::class, 'Magewirephp\Magewire\Component');
}

/**
 * Class DeliveryInformation
 * @package Mageplaza\DeliveryTime\Magewire\Checkout
 */
class DeliveryInformation extends Component
{
    /**
     * @var string|null
     */
    public ?string $deliveryDate = null;

    /**
     * @var string|null
     */
    public ?string $deliveryTime = null;

    /**
     * @var string|null
     */
    public ?string $houseSecurityCode = null;

    /**
     * @var string|null
     */
    public ?string $deliveryComment = null;

    /**
     * @var SessionCheckout
     */
    protected $sessionCheckout;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @param SessionCheckout $sessionCheckout
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->sessionCheckout = $sessionCheckout;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function mount(): void
    {
        $quote = $this->sessionCheckout->getQuote();
        if ($quote->getData('mp_delivery_information')) {
            $deliveryInfo            = Data::jsonDecode($quote->getData('mp_delivery_information'));
            $this->deliveryDate      = $deliveryInfo['deliveryDate'];
            $this->deliveryTime      = $deliveryInfo['deliveryTime'];
            $this->houseSecurityCode = $deliveryInfo['houseSecurityCode'];
            $this->deliveryComment   = $deliveryInfo['deliveryComment'];
        }
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function updating($value, string $name): mixed
    {
        if ($name == 'deliveryDate') {
            $this->deliveryDate = $value;
        }
        if ($name == 'deliveryTime') {
            $this->deliveryTime = $value;
        }
        if ($name == 'houseSecurityCode') {
            $this->houseSecurityCode = $value;
        }
        if ($name == 'deliveryComment') {
            $this->deliveryComment = $value;
        }

        try {
            $quote               = $this->sessionCheckout->getQuote();
            $deliveryInformation = [
                'deliveryDate'      => $this->deliveryDate,
                'deliveryTime'      => $this->deliveryTime,
                'houseSecurityCode' => $this->houseSecurityCode,
                'deliveryComment'   => $this->deliveryComment
            ];
            $quote->setData('mp_delivery_information', Data::jsonEncode($deliveryInformation));
            $this->quoteRepository->save($quote);
        } catch (LocalizedException|Exception) {
            $this->dispatchErrorMessage('Something went wrong while saving. Please try again.');
        }

        return $value;
    }
}
