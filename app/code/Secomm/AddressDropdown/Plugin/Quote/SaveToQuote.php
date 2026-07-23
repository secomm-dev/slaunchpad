<?php

namespace Secomm\AddressDropdown\Plugin\Quote;

use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;
class SaveToQuote
{
    protected $quoteRepository;

    public function __construct(
        QuoteRepository $quoteRepository,
        protected RequestInterface $request,
        protected CartRepositoryInterface $cartRepository,
        protected AddressDropdownHelper $addressDropdownHelper
    ) {
        $this->quoteRepository = $quoteRepository;
    }

    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
                                                              $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        $quote = $this->cartRepository->getActive($cartId);
        $extensionAttributes = $addressInformation->getShippingAddress()->getExtensionAttributes();

        if (!$extensionAttributes || !$this->addressDropdownHelper->isAddressDropdownModuleEnabled($quote->getStoreId())) {
            return [$cartId, $addressInformation];
        }

        $addressInformation->setData('sub_city', $extensionAttributes->getSubCity());
        $addressInformation->getShippingAddress()->setSubCity((int)$extensionAttributes->getSubCity());
        return [$cartId, $addressInformation];
    }
}
