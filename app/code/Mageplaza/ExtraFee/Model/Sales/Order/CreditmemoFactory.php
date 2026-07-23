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
namespace Mageplaza\ExtraFee\Model\Sales\Order;

use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Sales\Model\Convert\OrderFactory;
use Magento\Sales\Model\Order\CreditmemoFactory as OrderCreditmemoFactory;
use Magento\Tax\Model\Config;
use Mageplaza\ExtraFee\Helper\Data;

/**
 *
 */
class CreditmemoFactory extends OrderCreditmemoFactory
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @param Data $helper
     * @param OrderFactory $convertOrderFactory
     * @param Config $taxConfig
     * @param JsonSerializer|null $serializer
     * @param FormatInterface|null $localeFormat
     */
    public function __construct(
        Data $helper,
        OrderFactory $convertOrderFactory,
        Config $taxConfig,
        ?JsonSerializer $serializer = null,
        ?FormatInterface $localeFormat = null
    ) {
        $this->helper = $helper;

        parent::__construct($convertOrderFactory, $taxConfig, $serializer, $localeFormat);
    }

    /**
     * @param $creditmemo
     * @param $data
     *
     * @return void
     */
    protected function initData($creditmemo, $data)
    {
        $extraFeeTotals = $this->helper->getObjectExtraFeeTotals($creditmemo, $creditmemo->getOrder());

        foreach ($extraFeeTotals as $fee) {
            if (isset($data[$fee['code']])) {
                $creditmemo->setData($fee['code'], $data[$fee['code']]);
            }
        }

        parent::initData($creditmemo, $data);
    }
}
