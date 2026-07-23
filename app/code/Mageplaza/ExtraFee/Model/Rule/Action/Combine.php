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

namespace Mageplaza\ExtraFee\Model\Rule\Action;

use Magento\Framework\App\RequestInterface;
use Magento\Rule\Model\Condition\Context;
use Magento\SalesRule\Model\Rule\Condition\Product;

/**
 * Class Combine
 * @package Mageplaza\ExtraFee\Model\Rule\Condition
 */
class Combine extends \Magento\SalesRule\Model\Rule\Condition\Product\Combine
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Quote\Model\Quote\ItemFactory
     */
    protected $itemFactory;

    /**
     * @param Context $context
     * @param Product $ruleConditionProduct
     * @param RequestInterface $request
     * @param \Magento\Quote\Model\Quote\ItemFactory $itemFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Rule\Model\Condition\Context $context,
        \Magento\SalesRule\Model\Rule\Condition\Product $ruleConditionProduct,
        RequestInterface $request,
        \Magento\Quote\Model\Quote\ItemFactory $itemFactory,
        array $data = []
    ) {
        $this->request     = $request;
        $this->itemFactory = $itemFactory;
        parent::__construct($context, $ruleConditionProduct, $data);
    }

    /** Validate Product Cart Condition
     *
     * @inheritdoc
     * @since 101.0.6
     */
    protected function _isValid($entity)
    {
        if ($entity instanceof \Magento\Catalog\Model\Product) {
            // Get QuoteItem from Request for validation
            $quoteItem = $this->itemFactory->create();
            $quoteItem->setProduct($entity);
            $quoteItem->setQty($this->request->getParam('qty') ?? 1);
            $entity = $quoteItem;
        }

        return parent::_isValid($entity);
    }
}
